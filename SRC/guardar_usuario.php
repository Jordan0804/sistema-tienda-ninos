<?php
/**
 * guardar_usuario.php — Backend para crear, editar y toggle de usuarios.
 * Solo accesible para el rol Admin.
 * Recibe POST desde usuarios.php.
 */
require_once 'auth.php';
requerirAdmin();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit;
}

$accion = trim($_POST['accion'] ?? '');

/* ─────────────────────────────────────────────────────────────
   ACCIÓN: toggle_activo — Activar / desactivar un usuario
─────────────────────────────────────────────────────────────── */
if ($accion === 'toggle_activo') {
    $id = (int) ($_POST['id'] ?? 0);

    // No puede desactivarse a sí mismo
    if ($id <= 0 || $id === (int) $_SESSION['usuario_id']) {
        header('Location: usuarios.php?accion=error');
        exit;
    }

    try {
        $stmt = $conn->prepare("
            UPDATE Usuarios
            SET    Activo = CASE WHEN Activo = 1 THEN 0 ELSE 1 END
            WHERE  Id = ?
        ");
        $stmt->execute([$id]);
        header('Location: usuarios.php?accion=desactivado');
        exit;
    } catch (PDOException $e) {
        header('Location: usuarios.php?accion=error');
        exit;
    }
}

/* ─────────────────────────────────────────────────────────────
   ACCIÓN: crear — Nuevo usuario
─────────────────────────────────────────────────────────────── */
if ($accion === 'crear') {
    $nombre    = trim($_POST['nombre']    ?? '');
    $usuario   = trim($_POST['usuario']   ?? '');
    $rol       = trim($_POST['rol']       ?? '');
    $password  = $_POST['password']  ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    // Validación
    if ($nombre === '' || $usuario === '' || $password === '' || strlen($password) < 6
        || $password !== $confirmar
        || !in_array($rol, ['Admin', 'Cajero', 'Inventario'], true)) {
        header('Location: usuarios.php?accion=error');
        exit;
    }

    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO Usuarios (Nombre, Usuario, Contrasena, Rol, Activo, CreadoPor)
            VALUES (?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([$nombre, $usuario, $hash, $rol, (int)$_SESSION['usuario_id']]);
        header('Location: usuarios.php?accion=creado');
        exit;
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UQ_Usuario')) {
            header('Location: usuarios.php?accion=duplicado');
        } else {
            header('Location: usuarios.php?accion=error');
        }
        exit;
    }
}

/* ─────────────────────────────────────────────────────────────
   ACCIÓN: editar — Actualizar datos de un usuario existente
─────────────────────────────────────────────────────────────── */
if ($accion === 'editar') {
    $id      = (int) ($_POST['id']      ?? 0);
    $nombre  = trim($_POST['nombre']    ?? '');
    $usuario = trim($_POST['usuario']   ?? '');
    $rol     = trim($_POST['rol']       ?? '');
    $password  = $_POST['password']  ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    // Validación básica
    if ($id <= 0 || $nombre === '' || $usuario === ''
        || !in_array($rol, ['Admin', 'Cajero', 'Inventario'], true)) {
        header('Location: usuarios.php?accion=error');
        exit;
    }

    // Si se escribió contraseña, debe ser válida y coincidir
    if ($password !== '') {
        if (strlen($password) < 6 || $password !== $confirmar) {
            header('Location: usuarios.php?accion=error');
            exit;
        }
    }

    try {
        if ($password !== '') {
            // Actualizar con nueva contraseña
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                UPDATE Usuarios
                SET    Nombre     = ?,
                       Usuario    = ?,
                       Rol        = ?,
                       Contrasena = ?
                WHERE  Id = ?
            ");
            $stmt->execute([$nombre, $usuario, $rol, $hash, $id]);
        } else {
            // Actualizar sin cambiar contraseña
            $stmt = $conn->prepare("
                UPDATE Usuarios
                SET    Nombre  = ?,
                       Usuario = ?,
                       Rol     = ?
                WHERE  Id = ?
            ");
            $stmt->execute([$nombre, $usuario, $rol, $id]);
        }

        header('Location: usuarios.php?accion=actualizado');
        exit;

    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UQ_Usuario')) {
            header('Location: usuarios.php?accion=duplicado');
        } else {
            header('Location: usuarios.php?accion=error');
        }
        exit;
    }
}

// Si la acción no es reconocida, volver a usuarios
header('Location: usuarios.php');
exit;
?>
