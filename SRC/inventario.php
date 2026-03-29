<?php
/**
 * inventario.php — Listado completo de productos con búsqueda y CRUD.
 * Permite editar y eliminar productos desde esta misma vista.
 */
require_once 'auth.php';
requerirPermiso('inventario');
require_once 'db.php';

$error     = null;
$productos = [];
$mensaje   = $_GET['msg'] ?? '';   // Mensaje de éxito/error desde redirects

try {
    $stmt = $conn->query("
        SELECT Id, Nombre, Categoria, Talla, Color, Estado,
               Precio_Costo, Precio_Venta, Stock, Aplica_ITBIS, Descripcion
        FROM   Productos
        ORDER  BY Id DESC
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (str_contains($msg, '42S22') || str_contains($msg, 'Invalid column name') ||
        str_contains($msg, '42S02') || str_contains($msg, 'Invalid object name')) {
        $error = 'schema_error';
    } else {
        $error = $msg;
    }
}

// SQL de migración mostrado en pantalla si hay error de esquema
$sqlMigracion = <<<'SQL'
USE SistemaFacturacion;
GO

-- ── 1. Columnas nuevas en Productos (se omiten si ya existen) ──────
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name='Categoria'    AND Object_ID=OBJECT_ID('Productos'))
    ALTER TABLE Productos ADD Categoria    VARCHAR(50)   NOT NULL DEFAULT 'General';

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name='Talla'        AND Object_ID=OBJECT_ID('Productos'))
    ALTER TABLE Productos ADD Talla        VARCHAR(20)   NULL;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name='Color'        AND Object_ID=OBJECT_ID('Productos'))
    ALTER TABLE Productos ADD Color        VARCHAR(30)   NULL;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name='Precio_Costo' AND Object_ID=OBJECT_ID('Productos'))
    ALTER TABLE Productos ADD Precio_Costo DECIMAL(10,2) NOT NULL DEFAULT 0.00;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name='Precio_Venta' AND Object_ID=OBJECT_ID('Productos'))
    ALTER TABLE Productos ADD Precio_Venta DECIMAL(10,2) NOT NULL DEFAULT 0.00;
GO

-- ── 2. Copiar precio anterior → Precio_Venta (solo si existía Precio) ──
IF EXISTS (SELECT 1 FROM sys.columns WHERE Name='Precio' AND Object_ID=OBJECT_ID('Productos'))
    EXEC('UPDATE Productos SET Precio_Venta = Precio WHERE Precio_Venta = 0');
GO

-- ── 3. Tabla Facturas ──────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name='Facturas')
BEGIN
    CREATE TABLE Facturas (
        Id               INT            PRIMARY KEY IDENTITY(1,1),
        NumeroFactura    VARCHAR(25)    NOT NULL,
        FechaVenta       DATETIME       NOT NULL DEFAULT GETDATE(),
        Subtotal         DECIMAL(10,2)  NOT NULL,
        ITBIS            DECIMAL(10,2)  NOT NULL,
        Total            DECIMAL(10,2)  NOT NULL,
        MetodoPago       VARCHAR(20)    NOT NULL DEFAULT 'Efectivo',
        EfectivoRecibido DECIMAL(10,2)  NULL,
        Cambio           DECIMAL(10,2)  NULL,
        FechaRegistro    DATETIME       NOT NULL DEFAULT GETDATE()
    );
END
GO

-- ── 4. Tabla Detalle_Facturas ──────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name='Detalle_Facturas')
BEGIN
    CREATE TABLE Detalle_Facturas (
        Id             INT            PRIMARY KEY IDENTITY(1,1),
        FacturaId      INT            NOT NULL,
        ProductoId     INT            NOT NULL,
        NombreProducto VARCHAR(100)   NOT NULL,
        Cantidad       INT            NOT NULL,
        PrecioUnitario DECIMAL(10,2)  NOT NULL,
        Subtotal       DECIMAL(10,2)  NOT NULL,
        CONSTRAINT FK_Det_Factura  FOREIGN KEY (FacturaId)
            REFERENCES Facturas(Id) ON DELETE CASCADE,
        CONSTRAINT FK_Det_Producto FOREIGN KEY (ProductoId)
            REFERENCES Productos(Id)
    );
END
GO

PRINT 'Listo. Recarga el sistema.';
GO
SQL;

/** Formatea un número como moneda dominicana */
function formatRD(float $v): string {
    return 'RD$ ' . number_format($v, 2, '.', ',');
}

define('ITBIS_RATE', 0.18);

$paginaActiva = 'inventario';
require 'navbar.php';
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
        body { background-color: #f0f4f8; }
        .table thead th { background-color: #4a90d9; color: #fff; font-weight: 600; white-space: nowrap; }
        .table tbody tr:hover { background-color: #eaf3ff; }
        .stock-bajo { color: #dc3545; font-weight: 700; }
        .precio-venta { font-weight: 600; color: #198754; }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">

    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">
                <i class="bi bi-box-seam me-2 text-primary"></i>Inventario de Productos
            </h4>
            <small class="text-muted">Sistema de Facturación — Tienda Niños</small>
        </div>
        <a href="index.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Nuevo Producto
        </a>
    </div>

    <!-- Mensaje de resultado (tras editar o eliminar) -->
    <?php if ($mensaje === 'editado'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>Producto actualizado correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($mensaje === 'eliminado'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-trash3 me-2"></i>Producto eliminado del inventario.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($mensaje === 'error-eliminar'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            No se puede eliminar: el producto tiene ventas registradas.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error === 'schema_error'): ?>
    <!-- ══ GUÍA DE CONFIGURACIÓN PASO A PASO ══════════════════ -->
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <div class="card shadow border-0">
                <div class="card-header py-3" style="background-color:#ffc107;">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-wrench-adjustable-circle me-2"></i>
                        Actualización de base de datos requerida
                    </h5>
                    <small class="text-dark opacity-75">
                        La tabla <code>Productos</code> tiene el esquema antiguo — le faltan columnas nuevas.
                    </small>
                </div>
                <div class="card-body p-4">
                    <div class="list-group list-group-flush mb-4">

                        <div class="list-group-item px-0 py-3 border-0 border-bottom">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                      style="background:#4a90d9;width:2rem;height:2rem;font-size:.9rem;flex-shrink:0;">1</span>
                                <div>
                                    <div class="fw-semibold">Abre SQL Server Management Studio (SSMS)</div>
                                    <div class="text-muted small mt-1">Búscalo en el menú Inicio o ejecuta <code>ssms</code> con <kbd>Win+R</kbd>.</div>
                                </div>
                            </div>
                        </div>

                        <div class="list-group-item px-0 py-3 border-0 border-bottom">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                      style="background:#4a90d9;width:2rem;height:2rem;font-size:.9rem;flex-shrink:0;">2</span>
                                <div>
                                    <div class="fw-semibold">Conéctate a tu servidor</div>
                                    <div class="text-muted small mt-1">
                                        Servidor: <code>localhost</code> &nbsp;|&nbsp; Base de datos: <code>SistemaFacturacion</code>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="list-group-item px-0 py-3 border-0 border-bottom">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                      style="background:#4a90d9;width:2rem;height:2rem;font-size:.9rem;flex-shrink:0;">3</span>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold mb-2">Abre una Nueva Consulta (<kbd>Ctrl</kbd>+<kbd>N</kbd>) y pega el SQL</div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Copia todo el bloque:</small>
                                        <button id="btnCopiarSQL" class="btn btn-sm btn-outline-secondary" onclick="copiarSQL()">
                                            <i class="bi bi-clipboard me-1"></i>Copiar SQL
                                        </button>
                                    </div>
                                    <pre id="bloqueSQL"
                                         class="rounded p-3 small mb-0"
                                         style="background:#1e1e1e;color:#d4d4d4;max-height:320px;overflow-y:auto;white-space:pre;font-family:'Consolas','Courier New',monospace;line-height:1.5;"><?= htmlspecialchars($sqlMigracion) ?></pre>
                                </div>
                            </div>
                        </div>

                        <div class="list-group-item px-0 py-3 border-0 border-bottom">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                      style="background:#4a90d9;width:2rem;height:2rem;font-size:.9rem;flex-shrink:0;">4</span>
                                <div>
                                    <div class="fw-semibold">Presiona <kbd>F5</kbd> para ejecutar</div>
                                    <div class="text-muted small mt-1">
                                        Al finalizar verás en la pestaña "Messages":
                                        <code class="text-success ms-1">Listo. Recarga el sistema.</code>
                                    </div>
                                    <div class="alert alert-info py-2 px-3 mt-2 mb-0 small">
                                        <i class="bi bi-lightbulb me-1"></i>
                                        <strong>Normal:</strong> Si una columna ya existía aparece un aviso de error para esa línea — ignóralo. Lo importante es que diga <em>"Listo"</em> al final.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="list-group-item px-0 py-3 border-0">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                      style="background:#198754;width:2rem;height:2rem;font-size:.9rem;flex-shrink:0;">5</span>
                                <div>
                                    <div class="fw-semibold mb-2">Regresa aquí y recarga la página</div>
                                    <button class="btn btn-success px-4" onclick="location.reload()">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Ya ejecuté el SQL — Recargar ahora
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="alert alert-secondary py-2 small mb-0">
                        <i class="bi bi-file-earmark-code me-1"></i>
                        <strong>Alternativa:</strong> Abre <code>SRC/setup_ventas.sql</code> en SSMS y ejecútalo directamente.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function copiarSQL() {
        const sql = <?= json_encode($sqlMigracion) ?>;
        const btn = document.getElementById('btnCopiarSQL');
        navigator.clipboard.writeText(sql).then(() => {
            btn.innerHTML = '<i class="bi bi-clipboard-check me-1"></i>¡Copiado!';
            btn.classList.replace('btn-outline-secondary', 'btn-success');
            setTimeout(() => {
                btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copiar SQL';
                btn.classList.replace('btn-success', 'btn-outline-secondary');
            }, 2500);
        }).catch(() => {
            const pre = document.getElementById('bloqueSQL');
            const range = document.createRange();
            range.selectNode(pre);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            btn.innerHTML = '<i class="bi bi-check me-1"></i>Seleccionado — Ctrl+C para copiar';
        });
    }
    </script>

    <?php elseif ($error): ?>
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
                                placeholder="Buscar por nombre, categoría, talla, color..."
                                autocomplete="off"
                            >
                        </div>
                    </div>
                    <div class="col-auto ms-auto">
                        <span class="text-muted small">
                            Mostrando <strong id="totalVisible"><?= count($productos) ?></strong>
                            de <?= count($productos) ?> producto(s)
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($productos)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle me-2"></i>
                No hay productos registrados todavía.
                <a href="index.php" class="alert-link ms-1">Agrega el primero.</a>
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
                                <th>Categoría</th>
                                <th>Talla</th>
                                <th>Color</th>
                                <th>Estado</th>
                                <th class="text-end">Costo</th>
                                <th class="text-end">Precio Venta</th>
                                <th class="text-end">P. con ITBIS</th>
                                <th class="text-center">ITBIS</th>
                                <th class="text-center">Stock</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $p):
                                $precioVenta  = (float) $p['Precio_Venta'];
                                $aplicaItbis  = (bool)  $p['Aplica_ITBIS'];
                                $precioItbis  = $aplicaItbis ? $precioVenta * (1 + ITBIS_RATE) : $precioVenta;
                                $stockBajo    = (int) $p['Stock'] < 5;
                                $esNuevo      = strtolower(trim($p['Estado'])) === 'nuevo';
                            ?>
                            <tr>
                                <td class="ps-3 text-muted small"><?= (int)$p['Id'] ?></td>

                                <td class="fw-semibold"><?= htmlspecialchars($p['Nombre']) ?></td>

                                <td class="small"><?= htmlspecialchars($p['Categoria'] ?? '—') ?></td>

                                <td class="small">
                                    <?= $p['Talla'] ? htmlspecialchars($p['Talla']) : '<span class="text-muted">—</span>' ?>
                                </td>

                                <td class="small">
                                    <?= $p['Color'] ? htmlspecialchars($p['Color']) : '<span class="text-muted">—</span>' ?>
                                </td>

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

                                <td class="text-end small text-muted">
                                    <?= formatRD((float)$p['Precio_Costo']) ?>
                                </td>

                                <td class="text-end fw-semibold">
                                    <?= formatRD($precioVenta) ?>
                                </td>

                                <td class="text-end precio-venta">
                                    <?= $aplicaItbis ? formatRD($precioItbis) : '<span class="text-muted small">—</span>' ?>
                                </td>

                                <td class="text-center">
                                    <?php if ($aplicaItbis): ?>
                                        <span class="badge bg-primary" title="Aplica ITBIS 18%">Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" title="No aplica ITBIS">No</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <?php if ($stockBajo): ?>
                                        <span class="stock-bajo" title="Stock bajo">
                                            <i class="bi bi-exclamation-circle me-1"></i><?= (int)$p['Stock'] ?>
                                        </span>
                                    <?php else: ?>
                                        <?= (int)$p['Stock'] ?>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <!-- Editar -->
                                        <a href="editar_producto.php?id=<?= (int)$p['Id'] ?>"
                                           class="btn btn-sm btn-outline-primary py-0 px-2"
                                           title="Editar producto">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <!-- Eliminar (formulario POST para evitar eliminaciones por URL) -->
                                        <form method="POST" action="eliminar_producto.php"
                                              onsubmit="return confirm('¿Eliminar «<?= htmlspecialchars(addslashes($p['Nombre'])) ?>»? Esta acción no se puede deshacer.')">
                                            <input type="hidden" name="id" value="<?= (int)$p['Id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger py-0 px-2"
                                                    title="Eliminar producto">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Leyenda -->
        <div class="d-flex flex-wrap gap-4 mt-3 small text-muted">
            <span><i class="bi bi-exclamation-circle text-danger me-1"></i>Stock menor a 5 unidades</span>
            <span><span class="badge bg-success me-1">Nuevo</span>Sin uso previo</span>
            <span><span class="badge bg-warning text-dark me-1">Seminuevo</span>Usado</span>
            <span><i class="bi bi-tag text-success me-1"></i>P. con ITBIS incluye el 18% (solo si aplica)</span>
        </div>

        <?php endif; ?>
    <?php endif; ?>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ── Filtro de búsqueda en tiempo real ───────────────────
    const buscador     = document.getElementById('buscador');
    const tabla        = document.getElementById('tablaProductos');
    const totalVisible = document.getElementById('totalVisible');

    buscador.addEventListener('input', function () {
        const termino = this.value.toLowerCase().trim();
        const filas   = tabla ? tabla.querySelectorAll('tbody tr') : [];
        let visibles  = 0;

        filas.forEach(fila => {
            const texto   = fila.textContent.toLowerCase();
            const mostrar = !termino || texto.includes(termino);
            fila.style.display = mostrar ? '' : 'none';
            if (mostrar) visibles++;
        });

        totalVisible.textContent = visibles;
    });
</script>

</body>
</html>
