<?php
// Conexión a SQL Server (mismas credenciales que db.php)
$serverName = "host.docker.internal";
$database   = "SistemaFacturacion";
$uid        = "usuario_tienda";
$pwd        = "Tienda123*";

$error = null;
$productos = [];

try {
    $conn = new PDO(
        "sqlsrv:server=$serverName;Database=$database;Encrypt=true;TrustServerCertificate=true",
        $uid, $pwd
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->query("SELECT Id, Nombre, Precio, Estado, Stock, Descripcion FROM Productos ORDER BY Id DESC");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = $e->getMessage();
}

/**
 * Formatea un número como moneda dominicana: RD$ 1,250.00
 */
function formatRD(float $amount): string {
    return 'RD$ ' . number_format($amount, 2, '.', ',');
}

const ITBIS = 0.18;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario — Tienda Niños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }

        .table thead th {
            background-color: #4a90d9;
            color: #fff;
            font-weight: 600;
            white-space: nowrap;
        }

        .table tbody tr:hover { background-color: #eaf3ff; }

        .stock-bajo {
            color: #dc3545;
            font-weight: 700;
        }

        .precio-itbis {
            font-weight: 600;
            color: #198754;
        }
    </style>
</head>
<body>

<div class="container py-5">

    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Inventario de Productos</h2>
            <small class="text-muted">Sistema de Facturación — Tienda Niños</small>
        </div>
        <a href="index.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Nuevo Producto
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error de conexión:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php else: ?>

        <!-- Barra de búsqueda -->
        <div class="card shadow-sm mb-4">
            <div class="card-body py-3">
                <div class="row align-items-center g-2">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input
                                type="text"
                                id="buscador"
                                class="form-control border-start-0 ps-0"
                                placeholder="Buscar por nombre, estado o descripción..."
                                autocomplete="off"
                            >
                        </div>
                    </div>
                    <div class="col-md-auto ms-auto">
                        <span class="text-muted small">
                            Total de productos: <strong id="totalVisible"><?= count($productos) ?></strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de inventario -->
        <?php if (empty($productos)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle me-2"></i>No hay productos registrados todavía.
            </div>
        <?php else: ?>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaProductos">
                        <thead>
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th class="text-end">Precio Base</th>
                                <th class="text-end">Precio + ITBIS (18%)</th>
                                <th class="text-center">Stock</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $p): ?>
                            <?php
                                $precioBase  = (float) $p['Precio'];
                                $precioITBIS = $precioBase * (1 + ITBIS);
                                $stockBajo   = (int) $p['Stock'] < 5;
                                $esNuevo     = strtolower(trim($p['Estado'])) === 'nuevo';
                            ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= (int) $p['Id'] ?></td>

                                <td class="fw-semibold"><?= htmlspecialchars($p['Nombre']) ?></td>

                                <td>
                                    <?php if ($esNuevo): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-star-fill me-1"></i>Nuevo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-arrow-repeat me-1"></i>Seminuevo
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end"><?= formatRD($precioBase) ?></td>

                                <td class="text-end precio-itbis"><?= formatRD($precioITBIS) ?></td>

                                <td class="text-center">
                                    <?php if ($stockBajo): ?>
                                        <span class="stock-bajo" title="Stock bajo">
                                            <i class="bi bi-exclamation-circle me-1"></i><?= (int) $p['Stock'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span><?= (int) $p['Stock'] ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-muted small">
                                    <?= $p['Descripcion'] ? htmlspecialchars($p['Descripcion']) : '<em>—</em>' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Leyenda -->
        <div class="d-flex gap-4 mt-3 small text-muted">
            <span><i class="bi bi-exclamation-circle text-danger me-1"></i>Stock menor a 5 unidades</span>
            <span><span class="badge bg-success me-1">Nuevo</span> Producto sin uso</span>
            <span><span class="badge bg-warning text-dark me-1">Seminuevo</span> Producto usado</span>
        </div>

        <?php endif; ?>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const buscador     = document.getElementById('buscador');
    const tabla        = document.getElementById('tablaProductos');
    const totalVisible = document.getElementById('totalVisible');

    buscador.addEventListener('input', function () {
        const termino = this.value.toLowerCase().trim();
        const filas   = tabla ? tabla.querySelectorAll('tbody tr') : [];
        let visibles  = 0;

        filas.forEach(fila => {
            const texto   = fila.textContent.toLowerCase();
            const mostrar = texto.includes(termino);
            fila.style.display = mostrar ? '' : 'none';
            if (mostrar) visibles++;
        });

        totalVisible.textContent = visibles;
    });
</script>

</body>
</html>
