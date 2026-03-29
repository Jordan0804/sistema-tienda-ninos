<?php
/**
 * auth.php — Middleware de autenticación y control de acceso.
 *
 * Incluir al INICIO de cada página protegida, antes de cualquier HTML.
 * Gestiona la sesión y expone funciones de verificación de rol.
 *
 * Roles disponibles:
 *   Admin      — Acceso total, gestión de usuarios.
 *   Cajero     — Ventas e inventario.
 *   Inventario — Solo inventario (sin ventas ni historial).
 */

// Iniciar sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirigir al login si no hay sesión
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['redirigir_a'] = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
    header('Location: login.php');
    exit;
}

/* ─────────────────────────────────────────────────────────────
   Funciones de consulta de rol
───────────────────────────────────────────────────────────── */

/** Retorna el rol del usuario activo */
function rolActual(): string {
    return $_SESSION['rol'] ?? '';
}

/** ¿El usuario activo es Administrador? */
function esAdmin(): bool {
    return rolActual() === 'Admin';
}

/** ¿Puede realizar ventas en el POS? */
function puedeVender(): bool {
    return in_array(rolActual(), ['Admin', 'Cajero'], true);
}

/** ¿Puede ver y gestionar el inventario? */
function puedeGestionarInventario(): bool {
    return in_array(rolActual(), ['Admin', 'Cajero', 'Inventario'], true);
}

/** ¿Puede ver el historial de ventas? */
function puedeVerHistorial(): bool {
    return in_array(rolActual(), ['Admin', 'Cajero'], true);
}

/* ─────────────────────────────────────────────────────────────
   Funciones de restricción de acceso
   Redirigen con mensaje si el usuario no tiene permiso.
───────────────────────────────────────────────────────────── */

/** Exige rol Admin; si no, redirige al dashboard. */
function requerirAdmin(): void {
    if (!esAdmin()) {
        header('Location: dashboard.php?msg=sin_permiso');
        exit;
    }
}

/**
 * Exige un permiso específico.
 * @param string $permiso  'admin' | 'vender' | 'inventario' | 'historial'
 */
function requerirPermiso(string $permiso): void {
    $ok = match ($permiso) {
        'admin'      => esAdmin(),
        'vender'     => puedeVender(),
        'inventario' => puedeGestionarInventario(),
        'historial'  => puedeVerHistorial(),
        default      => false,
    };
    if (!$ok) {
        header('Location: dashboard.php?msg=sin_permiso');
        exit;
    }
}
?>
