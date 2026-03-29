<?php
/**
 * guardar.php — Recibe el POST del formulario index.php
 * e inserta el nuevo producto en la base de datos.
 */
require_once 'auth.php';
requerirPermiso('inventario');
require_once 'db.php';

// Solo aceptamos solicitudes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ── Recoger y limpiar los datos del formulario ───────────────
$nombre       = trim($_POST['nombre']       ?? '');
$categoria    = trim($_POST['categoria']    ?? 'General');
$talla        = trim($_POST['talla']        ?? '');
$color        = trim($_POST['color']        ?? '');
$estado       = trim($_POST['estado']       ?? '');
$precio_costo = (float) ($_POST['precio_costo'] ?? 0);
$precio_venta = (float) ($_POST['precio_venta'] ?? 0);
$stock        = (int)   ($_POST['stock']        ?? 0);
$aplica_itbis = isset($_POST['aplica_itbis']) ? 1 : 0;
$descripcion  = trim($_POST['descripcion']  ?? '');

// ── Validación básica en el servidor ────────────────────────
$errores = [];

if ($nombre === '') {
    $errores[] = 'El nombre del producto es obligatorio.';
}
if (!in_array($estado, ['Nuevo', 'Seminuevo'], true)) {
    $errores[] = 'El estado debe ser Nuevo o Seminuevo.';
}
if ($precio_costo < 0) {
    $errores[] = 'El precio de costo no puede ser negativo.';
}
if ($precio_venta <= 0) {
    $errores[] = 'El precio de venta debe ser mayor a cero.';
}
if ($stock < 0) {
    $errores[] = 'El stock no puede ser negativo.';
}

// Si hay errores de validación, los mostramos y detenemos
if (!empty($errores)) {
    echo renderPagina('error', $errores);
    exit;
}

// ── Insertar en la base de datos ─────────────────────────────
try {
    $sql = "
        INSERT INTO Productos
               (Nombre, Categoria, Talla, Color, Estado, Precio_Costo, Precio_Venta, Stock, Aplica_ITBIS, Descripcion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $nombre,
        $categoria,
        $talla        ?: null,
        $color        ?: null,
        $estado,
        $precio_costo,
        $precio_venta,
        $stock,
        $aplica_itbis,
        $descripcion  ?: null,
    ]);

    echo renderPagina('exito', $nombre);

} catch (PDOException $e) {
    $msg = $e->getMessage();

    // Detectar error de esquema (columna o tabla inexistente)
    if (str_contains($msg, '42S22') || str_contains($msg, 'Invalid column name')
     || str_contains($msg, '42S02') || str_contains($msg, 'Invalid object name')) {
        echo renderPagina('schema_error', []);
    } else {
        echo renderPagina('error', ['Error al guardar en la base de datos: ' . $msg]);
    }
}

/* ─────────────────────────────────────────────────────────────
 * renderPagina()
 * Genera la página de respuesta con Bootstrap.
 * $tipo   => 'exito' | 'error' | 'schema_error'
 * $datos  => string (nombre) | array (errores)
 * ─────────────────────────────────────────────────────────────*/
function renderPagina(string $tipo, $datos): string
{
    if ($tipo === 'schema_error') {
        $sqlGuia = htmlspecialchars(
            "USE SistemaFacturacion;\nGO\n-- Ejecuta el archivo SRC/setup_ventas.sql en SSMS"
        );
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de Esquema — Tienda Niños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>body { background-color: #f0f4f8; }</style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="alert alert-warning border-start border-warning border-4 shadow-sm">
                <h5 class="alert-heading fw-bold">
                    <i class="bi bi-database-exclamation me-2"></i>Esquema de base de datos desactualizado
                </h5>
                <p>La tabla <code>Productos</code> no tiene todas las columnas requeridas
                   (ej: <code>Aplica_ITBIS</code>, <code>Precio_Venta</code>).
                   Necesitas ejecutar el script de migración.</p>
                <hr>
                <p class="fw-semibold mb-1">Pasos para solucionarlo:</p>
                <ol>
                    <li>Abre <strong>SQL Server Management Studio (SSMS)</strong></li>
                    <li>Conéctate a tu servidor y selecciona la base <code>SistemaFacturacion</code></li>
                    <li>Ve a <strong>File → Open → File</strong></li>
                    <li>Selecciona <code>SRC/setup_ventas.sql</code></li>
                    <li>Presiona <kbd>F5</kbd> para ejecutar</li>
                    <li>Vuelve e intenta guardar el producto nuevamente</li>
                </ol>
                <div class="d-flex gap-2 mt-3">
                    <a href="index.php"     class="btn btn-warning fw-semibold">
                        <i class="bi bi-arrow-left me-1"></i>Volver al formulario
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
    }

    $esExito = $tipo === 'exito';
    $titulo  = $esExito ? 'Producto guardado exitosamente' : 'Error al guardar';
    $icono   = $esExito ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger';
    $colorH  = $esExito ? 'text-success' : 'text-danger';

    if ($esExito) {
        $cuerpo = '<p class="mb-0">El producto <strong>' . htmlspecialchars($datos) . '</strong> fue registrado correctamente en el inventario.</p>';
    } else {
        $items  = array_map(fn($e) => '<li>' . htmlspecialchars($e) . '</li>', (array)$datos);
        $cuerpo = '<ul class="mb-0">' . implode('', $items) . '</ul>';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titulo} — Tienda Niños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>body { background-color: #f0f4f8; }</style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow text-center p-4">
                <i class="bi {$icono} mb-3" style="font-size:3rem;"></i>
                <h4 class="fw-bold {$colorH} mb-3">{$titulo}</h4>
                <div class="text-muted mb-4">{$cuerpo}</div>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="index.php"      class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Agregar otro</a>
                    <a href="inventario.php" class="btn btn-outline-secondary"><i class="bi bi-box-seam me-1"></i>Ver inventario</a>
                    <a href="dashboard.php"  class="btn btn-outline-secondary"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
HTML;
}
?>
