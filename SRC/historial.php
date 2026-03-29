<?php
/**
 * historial.php — Historial completo de ventas realizadas.
 * Muestra todas las facturas con opción de expandir el detalle de cada una.
 */
require_once 'auth.php';
requerirPermiso('historial');
require_once 'db.php';

$error    = null;
$facturas = [];

// Parámetros de URL (mensajes desde procesar_venta.php)
$exito         = isset($_GET['exito']);
$nuevaFactura  = htmlspecialchars($_GET['factura'] ?? '');

try {
    // Cargar todas las facturas, más reciente primero
    $stmtFac = $conn->query("
        SELECT Id, NumeroFactura, FechaVenta, Subtotal, ITBIS, Total,
               MetodoPago, EfectivoRecibido, Cambio
        FROM   Facturas
        ORDER  BY FechaVenta DESC
    ");
    $facturas = $stmtFac->fetchAll(PDO::FETCH_ASSOC);

    // Cargar los detalles de TODAS las facturas de una sola consulta
    $stmtDet = $conn->query("
        SELECT FacturaId, NombreProducto, Cantidad, PrecioUnitario, Subtotal
        FROM   Detalle_Facturas
        ORDER  BY FacturaId, Id
    ");
    $todosDetalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    // Indexar detalles por FacturaId para acceso rápido en el template
    $detallesPorFactura = [];
    foreach ($todosDetalles as $d) {
        $detallesPorFactura[$d['FacturaId']][] = $d;
    }

} catch (PDOException $e) {
    $error = $e->getMessage();
}

/** Formatea un número como moneda dominicana */
function formatRD(float $v): string {
    return 'RD$ ' . number_format($v, 2, '.', ',');
}

$paginaActiva = 'historial';
require 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ventas — Tienda Niños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f4f8; }
        .table thead th { background-color: #4a90d9; color: #fff; font-weight: 600; white-space: nowrap; }
        .table tbody tr:hover { background-color: #eaf3ff; }

        /* Filas de detalle anidadas */
        .fila-detalle td { background-color: #f8f9fa; }
        .tabla-detalle thead th { background-color: #5a6268; color: #fff; font-size: .8rem; }
        .tabla-detalle td { font-size: .85rem; }

        /* Botón de expandir */
        .btn-toggle[aria-expanded="true"] .bi-chevron-down { transform: rotate(180deg); }
        .bi-chevron-down { transition: transform .2s; }
    </style>
</head>
<body>


<div class="container-fluid py-4 px-4">

    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">
                <i class="bi bi-clock-history me-2 text-primary"></i>Historial de Ventas
            </h4>
            <small class="text-muted">Todas las transacciones registradas</small>
        </div>
        <a href="vender.php" class="btn btn-primary">
            <i class="bi bi-cart3 me-1"></i>Nueva Venta
        </a>
    </div>

    <!-- Alerta de venta exitosa (redirigido desde procesar_venta.php) -->
    <?php if ($exito): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>¡Venta procesada exitosamente!</strong>
            <?php if ($nuevaFactura): ?>
                Factura <strong><?= $nuevaFactura ?></strong> registrada.
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php elseif (empty($facturas)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-receipt fs-1 d-block mb-3"></i>
            <h5>Sin ventas registradas aún</h5>
            <p class="mb-3 text-muted">Cuando proceses tu primera venta aparecerá aquí.</p>
            <a href="vender.php" class="btn btn-primary">
                <i class="bi bi-cart3 me-1"></i>Ir al punto de venta
            </a>
        </div>
    <?php else: ?>

    <!-- Tabla de facturas -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaHistorial">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width:2rem;"></th>
                            <th># Factura</th>
                            <th>Fecha y Hora</th>
                            <th>Método de Pago</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">ITBIS (18%)</th>
                            <th class="text-end">Total</th>
                            <th class="text-end pe-3">Efectivo / Cambio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($facturas as $f):
                            $facId      = (int)$f['Id'];
                            $detalles   = $detallesPorFactura[$facId] ?? [];
                            $numItems   = count($detalles);

                            $iconos = [
                                'Efectivo'      => 'bi-cash',
                                'Tarjeta'       => 'bi-credit-card',
                                'Transferencia' => 'bi-bank',
                            ];
                            $icono = $iconos[$f['MetodoPago']] ?? 'bi-wallet2';

                            // Resaltar la factura recién creada
                            $esNueva = ($f['NumeroFactura'] === $nuevaFactura && $exito);
                        ?>

                        <!-- Fila principal de la factura -->
                        <tr class="<?= $esNueva ? 'table-success' : '' ?>">
                            <td class="ps-3">
                                <?php if ($numItems > 0): ?>
                                    <button
                                        class="btn btn-sm btn-outline-secondary py-0 px-1 btn-toggle"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#detalle-<?= $facId ?>"
                                        aria-expanded="false"
                                        title="Ver detalle (<?= $numItems ?> ítem(s))"
                                    >
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                <?php endif; ?>
                            </td>

                            <td class="fw-bold text-primary">
                                <?= htmlspecialchars($f['NumeroFactura']) ?>
                                <?php if ($esNueva): ?>
                                    <span class="badge bg-success ms-1">Nueva</span>
                                <?php endif; ?>
                            </td>

                            <td class="small text-muted">
                                <?= htmlspecialchars(substr((string)$f['FechaVenta'], 0, 16)) ?>
                            </td>

                            <td>
                                <i class="bi <?= $icono ?> me-1"></i>
                                <?= htmlspecialchars($f['MetodoPago']) ?>
                            </td>

                            <td class="text-end small text-muted">
                                <?= formatRD((float)$f['Subtotal']) ?>
                            </td>

                            <td class="text-end small text-success">
                                <?= formatRD((float)$f['ITBIS']) ?>
                            </td>

                            <td class="text-end fw-bold">
                                <?= formatRD((float)$f['Total']) ?>
                            </td>

                            <td class="text-end pe-3 small">
                                <?php if ($f['MetodoPago'] === 'Efectivo' && $f['EfectivoRecibido'] !== null): ?>
                                    <span class="text-muted">Rec: <?= formatRD((float)$f['EfectivoRecibido']) ?></span>
                                    <?php if ($f['Cambio'] !== null): ?>
                                        <br>
                                        <span class="text-success">Cambio: <?= formatRD((float)$f['Cambio']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Fila colapsable con el detalle de productos -->
                        <?php if ($numItems > 0): ?>
                        <tr class="fila-detalle">
                            <td colspan="8" class="p-0">
                                <div class="collapse" id="detalle-<?= $facId ?>">
                                    <div class="p-3">
                                        <table class="table tabla-detalle table-bordered table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="ps-3">Producto</th>
                                                    <th class="text-center">Cantidad</th>
                                                    <th class="text-end">Precio Unit.</th>
                                                    <th class="text-end pe-3">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($detalles as $d): ?>
                                                <tr>
                                                    <td class="ps-3"><?= htmlspecialchars($d['NombreProducto']) ?></td>
                                                    <td class="text-center"><?= (int)$d['Cantidad'] ?></td>
                                                    <td class="text-end"><?= formatRD((float)$d['PrecioUnitario']) ?></td>
                                                    <td class="text-end pe-3 fw-semibold"><?= formatRD((float)$d['Subtotal']) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Resumen totales al pie -->
    <?php
        $totalGeneral   = array_sum(array_column($facturas, 'Total'));
        $totalITBIS     = array_sum(array_column($facturas, 'ITBIS'));
        $totalFacturas  = count($facturas);
    ?>
    <div class="row g-3 mt-2 justify-content-end">
        <div class="col-auto">
            <div class="card shadow-sm px-4 py-2 text-end">
                <div class="small text-muted"><?= $totalFacturas ?> factura(s) en total</div>
                <div class="small text-success">ITBIS total: <?= formatRD($totalITBIS) ?></div>
                <div class="fw-bold fs-5">Total vendido: <?= formatRD($totalGeneral) ?></div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ── Búsqueda en la tabla del historial ───────────────────
    // (filtra por número de factura y método de pago)
    const buscador = document.getElementById('buscadorHistorial');
    // Nota: si deseas agregar un input de búsqueda, añade:
    // <input id="buscadorHistorial" ...> y descomenta el listener.

    // ── Auto-expandir la factura recién creada ───────────────
    <?php if ($exito && $nuevaFactura): ?>
    document.addEventListener('DOMContentLoaded', () => {
        // Buscar el botón de la fila con la nueva factura y expandirlo
        document.querySelectorAll('.btn-toggle').forEach(btn => {
            const row = btn.closest('tr');
            if (row && row.classList.contains('table-success')) {
                btn.click();
            }
        });
    });
    <?php endif; ?>
</script>

</body>
</html>
