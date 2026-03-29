<?php
/**
 * dashboard.php — Panel principal del sistema.
 * Muestra KPIs (ventas del día, mes, stock bajo) y accesos rápidos.
 */
require_once 'auth.php';
require_once 'db.php';

$error = null;
$stats = [
    'ventas_hoy'      => 0,
    'facturas_hoy'    => 0,
    'ventas_mes'      => 0,
    'total_productos' => 0,
    'stock_bajo'      => 0,
];
$ventasRecientes = [];

try {
    // Ventas e importe del día de hoy
    $stmt = $conn->query("
        SELECT ISNULL(SUM(Total), 0) AS VentasHoy,
               COUNT(*)              AS FacturasHoy
        FROM   Facturas
        WHERE  CAST(FechaVenta AS DATE) = CAST(GETDATE() AS DATE)
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['ventas_hoy']   = (float) $row['VentasHoy'];
    $stats['facturas_hoy'] = (int)   $row['FacturasHoy'];

    // Ventas del mes en curso
    $stmt = $conn->query("
        SELECT ISNULL(SUM(Total), 0) AS VentasMes
        FROM   Facturas
        WHERE  YEAR(FechaVenta)  = YEAR(GETDATE())
          AND  MONTH(FechaVenta) = MONTH(GETDATE())
    ");
    $stats['ventas_mes'] = (float) $stmt->fetchColumn();

    // Total de referencias en inventario
    $stats['total_productos'] = (int) $conn->query("SELECT COUNT(*) FROM Productos")->fetchColumn();

    // Productos con stock crítico (menos de 5 unidades)
    $stats['stock_bajo'] = (int) $conn->query("SELECT COUNT(*) FROM Productos WHERE Stock < 5")->fetchColumn();

    // Últimas 5 facturas registradas
    $stmt = $conn->query("
        SELECT TOP 5 NumeroFactura, FechaVenta, Total, MetodoPago
        FROM   Facturas
        ORDER  BY FechaVenta DESC
    ");
    $ventasRecientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $msgError = $e->getMessage();
    // Detectar si el error es por tablas inexistentes (42S02 = "Invalid object name")
    if (str_contains($msgError, '42S02') || str_contains($msgError, 'Invalid object name')) {
        $error = 'setup_requerido';
    } else {
        $error = $msgError;
    }
}

/** Formatea un número como moneda dominicana: RD$ 1,250.00 */
function formatRD(float $v): string {
    return 'RD$ ' . number_format($v, 2, '.', ',');
}

$paginaActiva = 'dashboard';
require 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Tienda Niños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f4f8; }

        /* Tarjetas KPI con borde izquierdo de color */
        .kpi-card { border-left: 4px solid; border-radius: .5rem; transition: transform .15s; }
        .kpi-card:hover { transform: translateY(-3px); }
        .kpi-ventas-hoy { border-color: #4a90d9; }
        .kpi-ventas-mes { border-color: #198754; }
        .kpi-productos  { border-color: #6f42c1; }
        .kpi-stock-bajo { border-color: #dc3545; }
        .kpi-icon { font-size: 2.2rem; opacity: .75; }

        /* Tarjetas de módulos */
        .module-card { border-radius: .75rem; transition: transform .15s, box-shadow .15s; }
        .module-card:hover { transform: translateY(-4px); box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.15) !important; }
        .module-icon { font-size: 2.5rem; }

        /* Tabla */
        .table thead th { background-color: #4a90d9; color: #fff; }
        .table tbody tr:hover { background-color: #eaf3ff; }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">

    <h4 class="fw-bold mb-4">
        <i class="bi bi-speedometer2 me-2 text-primary"></i>Panel Principal
    </h4>

    <?php if ($error === 'setup_requerido'): ?>
        <!-- ── Alerta de configuración inicial ──────────────── -->
        <div class="alert alert-warning border-start border-warning border-4 shadow-sm" role="alert">
            <h5 class="alert-heading fw-bold">
                <i class="bi bi-database-exclamation me-2"></i>Configuración inicial requerida
            </h5>
            <p class="mb-2">
                Las tablas <code>Facturas</code> y <code>Detalle_Facturas</code> no existen
                todavía en SQL Server. Necesitas ejecutar el script de instalación una sola vez.
            </p>
            <hr>
            <p class="mb-1 fw-semibold">Pasos para solucionarlo:</p>
            <ol class="mb-2">
                <li>Abre <strong>SQL Server Management Studio (SSMS)</strong></li>
                <li>Conéctate a tu servidor SQL Server</li>
                <li>Ve a <strong>File → Open → File</strong></li>
                <li>Selecciona el archivo <code>SRC/setup_ventas.sql</code></li>
                <li>Presiona <kbd>F5</kbd> para ejecutar</li>
                <li>Recarga esta página</li>
            </ol>
            <p class="mb-0 small text-muted">
                El script es seguro: detecta qué tablas/columnas ya existen y solo crea las que faltan.
            </p>
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error de conexión:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php else: ?>

    <!-- ── KPIs ────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm kpi-card kpi-ventas-hoy h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-calendar-check kpi-icon text-primary"></i>
                    <div>
                        <div class="small text-muted fw-semibold text-uppercase">Ventas de Hoy</div>
                        <div class="fs-4 fw-bold"><?= formatRD($stats['ventas_hoy']) ?></div>
                        <div class="small text-muted"><?= $stats['facturas_hoy'] ?> factura(s)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm kpi-card kpi-ventas-mes h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-graph-up-arrow kpi-icon text-success"></i>
                    <div>
                        <div class="small text-muted fw-semibold text-uppercase">Ventas del Mes</div>
                        <div class="fs-4 fw-bold"><?= formatRD($stats['ventas_mes']) ?></div>
                        <div class="small text-muted"><?= date('F Y') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm kpi-card kpi-productos h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-bag kpi-icon" style="color:#6f42c1;"></i>
                    <div>
                        <div class="small text-muted fw-semibold text-uppercase">Total Productos</div>
                        <div class="fs-4 fw-bold"><?= $stats['total_productos'] ?></div>
                        <div class="small text-muted">en inventario</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm kpi-card kpi-stock-bajo h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-exclamation-triangle kpi-icon text-danger"></i>
                    <div>
                        <div class="small text-muted fw-semibold text-uppercase">Stock Bajo</div>
                        <div class="fs-4 fw-bold text-danger"><?= $stats['stock_bajo'] ?></div>
                        <div class="small text-muted">producto(s) &lt; 5 unid.</div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /KPIs -->

    <!-- ── ACCESOS RÁPIDOS + ÚLTIMAS VENTAS ─────────────────── -->
    <div class="row g-4">

        <!-- Módulos rápidos -->
        <div class="col-lg-4">
            <h6 class="fw-bold mb-3 text-muted text-uppercase small">Accesos Rápidos</h6>
            <div class="row g-3">

                <?php if (puedeVender()): ?>
                <div class="col-6">
                    <a href="vender.php" class="text-decoration-none">
                        <div class="card shadow-sm module-card text-center p-3 h-100">
                            <i class="bi bi-cart3 module-icon text-primary"></i>
                            <div class="small fw-semibold mt-2">Nueva Venta</div>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (puedeGestionarInventario()): ?>
                <div class="col-6">
                    <a href="index.php" class="text-decoration-none">
                        <div class="card shadow-sm module-card text-center p-3 h-100">
                            <i class="bi bi-plus-circle module-icon text-success"></i>
                            <div class="small fw-semibold mt-2">Nuevo Producto</div>
                        </div>
                    </a>
                </div>

                <div class="col-6">
                    <a href="inventario.php" class="text-decoration-none">
                        <div class="card shadow-sm module-card text-center p-3 h-100">
                            <i class="bi bi-box-seam module-icon" style="color:#6f42c1;"></i>
                            <div class="small fw-semibold mt-2">Inventario</div>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (puedeVerHistorial()): ?>
                <div class="col-6">
                    <a href="historial.php" class="text-decoration-none">
                        <div class="card shadow-sm module-card text-center p-3 h-100">
                            <i class="bi bi-clock-history module-icon text-warning"></i>
                            <div class="small fw-semibold mt-2">Historial</div>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (esAdmin()): ?>
                <div class="col-6">
                    <a href="usuarios.php" class="text-decoration-none">
                        <div class="card shadow-sm module-card text-center p-3 h-100">
                            <i class="bi bi-people module-icon text-info"></i>
                            <div class="small fw-semibold mt-2">Usuarios</div>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Últimas ventas -->
        <div class="col-lg-8">
            <h6 class="fw-bold mb-3 text-muted text-uppercase small">Últimas Ventas</h6>
            <div class="card shadow-sm">
                <div class="card-body p-0">

                    <?php if (empty($ventasRecientes)): ?>
                        <p class="text-muted text-center py-5 mb-0">
                            <i class="bi bi-receipt fs-2 d-block mb-2"></i>
                            Aún no hay ventas registradas.
                        </p>
                    <?php else: ?>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3"># Factura</th>
                                    <th>Fecha</th>
                                    <th>Método de Pago</th>
                                    <th class="text-end pe-3">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ventasRecientes as $v):
                                    $iconos = [
                                        'Efectivo'       => 'bi-cash',
                                        'Tarjeta'        => 'bi-credit-card',
                                        'Transferencia'  => 'bi-bank',
                                    ];
                                    $icono = $iconos[$v['MetodoPago']] ?? 'bi-wallet2';
                                ?>
                                <tr>
                                    <td class="ps-3 fw-semibold text-primary">
                                        <?= htmlspecialchars($v['NumeroFactura']) ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars(substr((string)$v['FechaVenta'], 0, 16)) ?>
                                    </td>
                                    <td>
                                        <i class="bi <?= $icono ?> me-1"></i>
                                        <?= htmlspecialchars($v['MetodoPago']) ?>
                                    </td>
                                    <td class="text-end pe-3 fw-bold">
                                        <?= formatRD((float)$v['Total']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end p-2 border-top">
                        <a href="historial.php" class="small text-primary text-decoration-none">
                            <i class="bi bi-arrow-right me-1"></i>Ver historial completo
                        </a>
                    </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /row módulos -->

    <?php endif; ?>
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
