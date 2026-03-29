<?php
/**
 * actualizar_producto.php — Procesa el formulario de edición.
 * Recibe POST desde editar_producto.php y ejecuta el UPDATE.
 */
require_once 'auth.php';
requerirPermiso('inventario');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inventario.php');
    exit;
}

// ── Recoger y limpiar los datos ──────────────────────────────
$id           = (int)   ($_POST['id']           ?? 0);
$nombre       = trim($_POST['nombre']           ?? '');
$categoria    = trim($_POST['categoria']        ?? 'General');
$talla        = trim($_POST['talla']            ?? '');
$color        = trim($_POST['color']            ?? '');
$estado       = trim($_POST['estado']           ?? '');
$precio_costo = (float) ($_POST['precio_costo'] ?? 0);
$precio_venta = (float) ($_POST['precio_venta'] ?? 0);
$stock        = (int)   ($_POST['stock']        ?? 0);
$aplica_itbis = isset($_POST['aplica_itbis']) ? 1 : 0;
$descripcion  = trim($_POST['descripcion']      ?? '');

// ── Validación básica ────────────────────────────────────────
if ($id <= 0 || $nombre === '' || !in_array($estado, ['Nuevo','Seminuevo'], true) || $precio_venta <= 0) {
    header('Location: inventario.php?msg=error');
    exit;
}

try {
    $sql = "
        UPDATE Productos
        SET    Nombre       = ?,
               Categoria    = ?,
               Talla        = ?,
               Color        = ?,
               Estado       = ?,
               Precio_Costo = ?,
               Precio_Venta = ?,
               Stock        = ?,
               Aplica_ITBIS = ?,
               Descripcion  = ?
        WHERE  Id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $nombre,
        $categoria,
        $talla       ?: null,
        $color       ?: null,
        $estado,
        $precio_costo,
        $precio_venta,
        $stock,
        $aplica_itbis,
        $descripcion ?: null,
        $id,
    ]);

    header('Location: inventario.php?msg=editado');
    exit;

} catch (PDOException $e) {
    header('Location: inventario.php?msg=error');
    exit;
}
?>
