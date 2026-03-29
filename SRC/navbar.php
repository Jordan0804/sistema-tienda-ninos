<?php
/**
 * navbar.php — Barra de navegación reutilizable.
 *
 * Requisito: auth.php incluido previamente en la misma página.
 * Uso: definir $paginaActiva y luego require 'navbar.php';
 *
 * Valores de $paginaActiva:
 *   dashboard | inventario | nuevo_producto | vender | historial | usuarios
 */
$_pAct    = $paginaActiva ?? '';
$_nombre  = htmlspecialchars($_SESSION['nombre']  ?? 'Usuario');
$_usuario = htmlspecialchars($_SESSION['usuario'] ?? '');
$_rol     = htmlspecialchars($_SESSION['rol']     ?? '');

// Color del badge de rol
$_badgeRol = match ($_SESSION['rol'] ?? '') {
    'Admin'      => 'bg-warning text-dark',
    'Cajero'     => 'bg-success',
    'Inventario' => 'bg-info text-dark',
    default      => 'bg-secondary',
};
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:#4a90d9;">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-shop me-2"></i>Tienda Niños
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">

            <!-- ── Links principales ──────────────────────── -->
            <ul class="navbar-nav me-auto gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= $_pAct === 'dashboard' ? 'active fw-semibold' : '' ?>"
                       href="dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>

                <?php if (puedeGestionarInventario()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $_pAct === 'inventario' ? 'active fw-semibold' : '' ?>"
                       href="inventario.php">
                        <i class="bi bi-box-seam me-1"></i>Inventario
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $_pAct === 'nuevo_producto' ? 'active fw-semibold' : '' ?>"
                       href="index.php">
                        <i class="bi bi-plus-circle me-1"></i>Nuevo Producto
                    </a>
                </li>
                <?php endif; ?>

                <?php if (puedeVender()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $_pAct === 'vender' ? 'active fw-semibold' : '' ?>"
                       href="vender.php">
                        <i class="bi bi-cart3 me-1"></i>Nueva Venta
                    </a>
                </li>
                <?php endif; ?>

                <?php if (puedeVerHistorial()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $_pAct === 'historial' ? 'active fw-semibold' : '' ?>"
                       href="historial.php">
                        <i class="bi bi-clock-history me-1"></i>Historial
                    </a>
                </li>
                <?php endif; ?>

                <?php if (esAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $_pAct === 'usuarios' ? 'active fw-semibold' : '' ?>"
                       href="usuarios.php">
                        <i class="bi bi-people me-1"></i>Usuarios
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- ── Usuario activo + logout ────────────────── -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 py-2"
                       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-5"></i>
                        <span class="d-none d-lg-inline"><?= $_nombre ?></span>
                        <span class="badge <?= $_badgeRol ?> ms-1"><?= $_rol ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li>
                            <span class="dropdown-item-text">
                                <div class="fw-semibold"><?= $_nombre ?></div>
                                <div class="small text-muted">@<?= $_usuario ?></div>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <?php if (esAdmin()): ?>
                        <li>
                            <a class="dropdown-item" href="usuarios.php">
                                <i class="bi bi-people me-2 text-primary"></i>Gestionar Usuarios
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

        </div>
    </div>
</nav>
