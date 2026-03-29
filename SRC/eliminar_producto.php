<?php
/**
 * eliminar_producto.php — Elimina un producto del inventario.
 * Recibe POST desde el formulario de confirmación en inventario.php.
 *
 * NOTA: No se puede eliminar un producto que tenga ventas registradas
 *       (la FK de Detalle_Facturas → Productos lo impide).
 */
require_once 'auth.php';
requerirPermiso('inventario');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inventario.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: inventario.php');
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM Productos WHERE Id = ?");
    $stmt->execute([$id]);

    header('Location: inventario.php?msg=eliminado');
    exit;

} catch (PDOException $e) {
    // El error más común aquí es una violación de FK (producto con ventas)
    header('Location: inventario.php?msg=error-eliminar');
    exit;
}
?>
