<?php
/**
 * procesar_venta.php — Backend del punto de venta.
 *
 * Recibe POST desde vender.php con:
 *   - carrito_json      : array JSON con los items del carrito (id, cantidad)
 *   - metodo_pago       : Efectivo | Tarjeta | Transferencia
 *   - efectivo_recibido : solo requerido si metodo_pago = Efectivo
 *
 * Pasos:
 *   1. Valida y decodifica el carrito.
 *   2. Re-consulta los precios y Aplica_ITBIS desde BD (no confiamos en el cliente).
 *   3. Verifica que haya stock suficiente para cada ítem.
 *   4. Calcula subtotal, ITBIS (por producto) y total en el servidor.
 *   5. Abre una transacción:
 *        a. Inserta la cabecera en Facturas.
 *        b. Genera el NumeroFactura único (FAC-YYYYMMDD-ID).
 *        c. Inserta cada línea en Detalle_Facturas.
 *        d. Descuenta el stock de Productos.
 *   6. Confirma (COMMIT) o revierte (ROLLBACK) según el resultado.
 *   7. Redirige al historial con un mensaje de éxito o error.
 */
require_once 'auth.php';
requerirPermiso('vender');
require_once 'db.php';

define('ITBIS_RATE_PROC', 0.18);

/* ─────────────────────────────────────────────────────────────
   1. Solo aceptar POST
───────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: vender.php');
    exit;
}

/* ─────────────────────────────────────────────────────────────
   2. Leer y validar los datos del POST
───────────────────────────────────────────────────────────── */
$carritoRaw  = $_POST['carrito_json']      ?? '';
$metodoPago  = trim($_POST['metodo_pago']  ?? '');
$efectivoRec = (float) ($_POST['efectivo_recibido'] ?? 0);

$metodosValidos = ['Efectivo', 'Tarjeta', 'Transferencia'];

if (!in_array($metodoPago, $metodosValidos, true)) {
    redirigirError('Método de pago inválido.');
}

// Decodificar el JSON del carrito
$carritoCliente = json_decode($carritoRaw, true);

if (!is_array($carritoCliente) || empty($carritoCliente)) {
    redirigirError('El carrito está vacío o los datos son inválidos.');
}

/* ─────────────────────────────────────────────────────────────
   3. Re-consultar precios, stock y Aplica_ITBIS desde la BD
      (Nunca confiamos en los precios enviados por el cliente)
───────────────────────────────────────────────────────────── */
$idsCarrito = array_map(fn($item) => (int)($item['id'] ?? 0), $carritoCliente);
$idsCarrito = array_filter($idsCarrito, fn($id) => $id > 0);
$idsCarrito = array_values(array_unique($idsCarrito));

if (empty($idsCarrito)) {
    redirigirError('Los IDs de producto son inválidos.');
}

// Construimos la cláusula IN de forma segura con placeholders
$placeholders = implode(',', array_fill(0, count($idsCarrito), '?'));
$stmtProd     = $conn->prepare("
    SELECT Id, Nombre, Precio_Venta, Stock, Aplica_ITBIS
    FROM   Productos
    WHERE  Id IN ($placeholders)
");
$stmtProd->execute($idsCarrito);
$productosDB = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

// Indexar por ID para acceso rápido
$productosMap = [];
foreach ($productosDB as $p) {
    $productosMap[(int)$p['Id']] = $p;
}

/* ─────────────────────────────────────────────────────────────
   4. Construir el carrito validado y calcular totales
      El ITBIS se aplica por producto según la columna Aplica_ITBIS
───────────────────────────────────────────────────────────── */
$carritoValidado = [];
$subtotal        = 0.0;
$itbisTotal      = 0.0;

foreach ($carritoCliente as $item) {
    $idItem   = (int) ($item['id']       ?? 0);
    $cantItem = (int) ($item['cantidad'] ?? 0);

    if ($idItem <= 0 || $cantItem <= 0) {
        redirigirError("Ítem del carrito inválido (ID: $idItem).");
    }

    if (!isset($productosMap[$idItem])) {
        redirigirError("Producto ID $idItem no encontrado en el catálogo.");
    }

    $prod = $productosMap[$idItem];

    // Verificar stock suficiente
    if ($cantItem > (int)$prod['Stock']) {
        redirigirError("Stock insuficiente para «{$prod['Nombre']}». Disponible: {$prod['Stock']}, solicitado: $cantItem.");
    }

    $precioUnitario = (float) $prod['Precio_Venta'];
    $subtotalLinea  = $precioUnitario * $cantItem;
    $aplicaItbis    = (bool)  $prod['Aplica_ITBIS'];
    $itbisLinea     = $aplicaItbis ? round($subtotalLinea * ITBIS_RATE_PROC, 2) : 0.0;

    $subtotal   += $subtotalLinea;
    $itbisTotal += $itbisLinea;

    $carritoValidado[] = [
        'id'             => $idItem,
        'nombre'         => $prod['Nombre'],
        'cantidad'       => $cantItem,
        'precioUnitario' => $precioUnitario,
        'subtotal'       => $subtotalLinea,
    ];
}

$subtotal = round($subtotal, 2);
$itbis    = round($itbisTotal, 2);
$total    = round($subtotal + $itbis, 2);

// Validar pago en efectivo
if ($metodoPago === 'Efectivo' && $efectivoRec < $total) {
    redirigirError('El efectivo recibido es insuficiente para cubrir el total.');
}

$cambio = ($metodoPago === 'Efectivo') ? round($efectivoRec - $total, 2) : null;

/* ─────────────────────────────────────────────────────────────
   5. Persistir en la base de datos (transacción)
───────────────────────────────────────────────────────────── */
try {
    $conn->beginTransaction();

    // ── 5a. Insertar cabecera de la factura ──────────────────
    $stmtFac = $conn->prepare("
        INSERT INTO Facturas (NumeroFactura, Subtotal, ITBIS, Total, MetodoPago, EfectivoRecibido, Cambio)
        VALUES ('PENDIENTE', ?, ?, ?, ?, ?, ?)
    ");
    $stmtFac->execute([
        $subtotal,
        $itbis,
        $total,
        $metodoPago,
        ($metodoPago === 'Efectivo') ? $efectivoRec : null,
        $cambio,
    ]);

    // ── 5b. Obtener el ID generado y calcular el número de factura ──
    $facturaId     = (int) $conn->query("SELECT SCOPE_IDENTITY() AS Id")->fetchColumn();
    $numeroFactura = 'FAC-' . date('Ymd') . '-' . str_pad($facturaId, 4, '0', STR_PAD_LEFT);

    $stmtNum = $conn->prepare("UPDATE Facturas SET NumeroFactura = ? WHERE Id = ?");
    $stmtNum->execute([$numeroFactura, $facturaId]);

    // ── 5c. Insertar cada línea en Detalle_Facturas ──────────
    $stmtDet = $conn->prepare("
        INSERT INTO Detalle_Facturas (FacturaId, ProductoId, NombreProducto, Cantidad, PrecioUnitario, Subtotal)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    // ── 5d. Descontar stock de Productos ─────────────────────
    $stmtStock = $conn->prepare("
        UPDATE Productos
        SET    Stock = Stock - ?
        WHERE  Id    = ?
          AND  Stock >= ?
    ");

    foreach ($carritoValidado as $linea) {
        // Insertar detalle
        $stmtDet->execute([
            $facturaId,
            $linea['id'],
            $linea['nombre'],
            $linea['cantidad'],
            $linea['precioUnitario'],
            $linea['subtotal'],
        ]);

        // Descontar stock (la condición Stock >= cantidad protege contra carreras)
        $stmtStock->execute([$linea['cantidad'], $linea['id'], $linea['cantidad']]);

        if ($stmtStock->rowCount() === 0) {
            // Stock insuficiente al momento de grabar (concurrencia)
            throw new RuntimeException("Stock agotado para el producto ID {$linea['id']} durante el procesamiento.");
        }
    }

    $conn->commit();

    // ── 6. Redirigir al historial con mensaje de éxito ───────
    header('Location: historial.php?exito=1&factura=' . urlencode($numeroFactura));
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    redirigirError('Error al procesar la venta: ' . $e->getMessage());
}

/* ─────────────────────────────────────────────────────────────
   Helper: redirigirError()
   Redirige a vender.php con el mensaje de error en la URL.
───────────────────────────────────────────────────────────── */
function redirigirError(string $mensaje): never {
    header('Location: vender.php?error=' . urlencode($mensaje));
    exit;
}
?>
