<?php
/**
 * vender.php — Módulo de punto de venta (POS).
 * Carga el catálogo desde BD; el carrito se gestiona en JS del lado cliente.
 * Al confirmar el pago, envía los datos a procesar_venta.php.
 */
require_once 'auth.php';
requerirPermiso('vender');

$dbError      = null;
$catalogo     = [];
$errorVenta   = htmlspecialchars($_GET['error'] ?? '');  // Mensaje de error desde procesar_venta.php

try {
    require_once 'db.php';

    // Cargamos solo productos con stock disponible, incluido Aplica_ITBIS
    $stmt = $conn->query("
        SELECT Id, Nombre, Categoria, Talla, Color, Estado, Precio_Venta, Stock, Aplica_ITBIS
        FROM   Productos
        WHERE  Stock > 0
        ORDER  BY Nombre ASC
    ");
    $catalogo = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $msg = $e->getMessage();
    // 42S22 = columna inválida  |  42S02 = tabla inválida
    if (str_contains($msg, '42S22') || str_contains($msg, 'Invalid column name') ||
        str_contains($msg, '42S02') || str_contains($msg, 'Invalid object name')) {
        $dbError = 'schema_error';
    } else {
        $dbError = $msg;
    }
}

// SQL de migración — se muestra en pantalla cuando hay error de esquema
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

// Serializa el catálogo a JSON de forma segura para inserción en HTML
$catalogoJson = json_encode($catalogo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

define('ITBIS_RATE_POS', 0.18);

$paginaActiva = 'vender';
require 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Venta — Tienda Niños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f4f8; }

        /* Encabezados de tablas */
        .table thead th {
            background-color: #4a90d9;
            color: #fff;
            font-weight: 600;
            white-space: nowrap;
        }
        .table tbody tr:hover { background-color: #eaf3ff; }

        /* Panel derecho fijo en scroll */
        .panel-venta {
            position: sticky;
            top: 1.5rem;
        }

        /* Área de resultados del buscador */
        #resultadosBusqueda {
            max-height: 420px;
            overflow-y: auto;
        }
        .producto-card { transition: background-color .15s; }
        .producto-card:hover { background-color: #eaf3ff; }

        /* Resumen económico */
        .resumen-linea {
            display: flex;
            justify-content: space-between;
            padding: .35rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .resumen-total {
            display: flex;
            justify-content: space-between;
            padding: .5rem 0;
            font-size: 1.2rem;
            font-weight: 700;
            color: #4a90d9;
        }

        /* Input de cantidad en el carrito */
        .qty-input { width: 70px; text-align: center; }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">
                <i class="bi bi-cart3 me-2 text-primary"></i>Nueva Venta
            </h4>
            <small class="text-muted">Sistema de Facturación — Tienda Niños</small>
        </div>
    </div>

    <?php if ($errorVenta): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error al procesar la venta:</strong> <?= $errorVenta ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($dbError === 'schema_error'): ?>
    <!-- ══ GUÍA DE CONFIGURACIÓN PASO A PASO ══════════════════ -->
    <div class="row justify-content-center">
        <div class="col-xl-9">

            <div class="card shadow border-0">
                <!-- Encabezado -->
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

                    <!-- ── Lista de pasos ──────────────────────── -->
                    <div class="list-group list-group-flush mb-4">

                        <!-- Paso 1 -->
                        <div class="list-group-item px-0 py-3 border-0 border-bottom">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                      style="background:#4a90d9; width:2rem; height:2rem; font-size:.9rem; flex-shrink:0;">1</span>
                                <div>
                                    <div class="fw-semibold">Abre SQL Server Management Studio (SSMS)</div>
                                    <div class="text-muted small mt-1">
                                        Búscalo en el menú Inicio como <strong>"SQL Server Management Studio"</strong>
                                        o ejecuta <code>ssms</code> desde el Ejecutar (<kbd>Win+R</kbd>).
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 2 -->
                        <div class="list-group-item px-0 py-3 border-0 border-bottom">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                      style="background:#4a90d9; width:2rem; height:2rem; font-size:.9rem; flex-shrink:0;">2</span>
                                <div>
                                    <div class="fw-semibold">Conéctate a tu servidor SQL Server</div>
                                    <div class="text-muted small mt-1">
                                        <div class="row g-2 mt-1">
                                            <div class="col-auto">
                                                <span class="badge bg-light text-dark border">Servidor:</span>
                                                <code>localhost</code> o el nombre de tu instancia
                                            </div>
                                            <div class="col-auto">
                                                <span class="badge bg-light text-dark border">Base de datos:</span>
                                                <code>SistemaFacturacion</code>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 3 -->
                        <div class="list-group-item px-0 py-3 border-0 border-bottom">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                      style="background:#4a90d9; width:2rem; height:2rem; font-size:.9rem; flex-shrink:0;">3</span>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold mb-2">
                                        Abre una <strong>Nueva Consulta</strong> (<kbd>Ctrl</kbd>+<kbd>N</kbd>)
                                        y pega el siguiente SQL
                                    </div>

                                    <!-- Bloque de código con botón copiar -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Copia todo el bloque de una vez:
                                        </span>
                                        <button
                                            id="btnCopiarSQL"
                                            class="btn btn-sm btn-outline-secondary"
                                            onclick="copiarSQL()">
                                            <i class="bi bi-clipboard me-1"></i>Copiar SQL
                                        </button>
                                    </div>

                                    <pre id="bloqueSQL"
                                         class="rounded p-3 small mb-0"
                                         style="background:#1e1e1e; color:#d4d4d4; max-height:320px; overflow-y:auto; white-space:pre; font-family:'Consolas','Courier New',monospace; line-height:1.5;"><?= htmlspecialchars($sqlMigracion) ?></pre>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 4 -->
                        <div class="list-group-item px-0 py-3 border-0 border-bottom">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                      style="background:#4a90d9; width:2rem; height:2rem; font-size:.9rem; flex-shrink:0;">4</span>
                                <div>
                                    <div class="fw-semibold">
                                        Presiona <kbd>F5</kbd> para ejecutar
                                        <span class="badge bg-secondary ms-1">o el botón Execute ▶</span>
                                    </div>
                                    <div class="text-muted small mt-1">
                                        Al finalizar, en la pestaña <strong>"Messages"</strong> deberías ver:
                                        <code class="text-success ms-1">Listo. Recarga el sistema.</code>
                                    </div>
                                    <div class="alert alert-info py-2 px-3 mt-2 mb-0 small">
                                        <i class="bi bi-lightbulb me-1"></i>
                                        <strong>Normal:</strong> Si alguna columna ya existía verás un aviso de error
                                        para esa línea — ignóralo y continúa. Lo importante es que diga
                                        <em>"Listo"</em> al final.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Paso 5 -->
                        <div class="list-group-item px-0 py-3 border-0">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                      style="background:#198754; width:2rem; height:2rem; font-size:.9rem; flex-shrink:0;">5</span>
                                <div>
                                    <div class="fw-semibold mb-2">Regresa aquí y recarga la página</div>
                                    <button
                                        class="btn btn-success px-4"
                                        onclick="location.reload()">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Ya ejecuté el SQL — Recargar ahora
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div><!-- /list-group -->

                    <!-- Alternativa rápida -->
                    <div class="alert alert-secondary py-2 small mb-0">
                        <i class="bi bi-file-earmark-code me-1"></i>
                        <strong>Alternativa:</strong> Abre directamente el archivo
                        <code>SRC/setup_ventas.sql</code> en SSMS y ejecútalo —
                        contiene exactamente las mismas instrucciones con verificaciones adicionales.
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
            // Fallback: seleccionar el texto del bloque
            const pre = document.getElementById('bloqueSQL');
            const range = document.createRange();
            range.selectNode(pre);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            btn.innerHTML = '<i class="bi bi-check me-1"></i>Seleccionado — Ctrl+C para copiar';
        });
    }
    </script>

    <?php elseif ($dbError): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error de conexión:</strong> <?= htmlspecialchars($dbError) ?>
        </div>
    <?php else: ?>

    <!-- ══ LAYOUT DOS COLUMNAS ════════════════════════════════ -->
    <div class="row g-4">

        <!-- ── COLUMNA IZQUIERDA: Buscador + Resultados ──────── -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-search me-2 text-primary"></i>Buscar Productos
                    </h5>
                </div>
                <div class="card-body">

                    <!-- Campo de búsqueda -->
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input
                            type="text"
                            id="inputBusqueda"
                            class="form-control border-start-0 ps-0"
                            placeholder="Buscar por nombre, categoría, talla, color..."
                            autocomplete="off"
                        >
                        <button class="btn btn-outline-secondary" type="button" id="btnLimpiarBusqueda" title="Limpiar búsqueda">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Tabla de resultados (generada por JS) -->
                    <div id="resultadosBusqueda">
                        <table class="table table-sm align-middle mb-0" id="tablaResultados">
                            <thead>
                                <tr>
                                    <th class="ps-3">Producto</th>
                                    <th>Categoría</th>
                                    <th>Talla</th>
                                    <th>Estado</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center">Agregar</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoResultados">
                                <!-- Generado por JS -->
                            </tbody>
                        </table>
                        <p id="sinResultados" class="text-muted text-center py-4" style="display:none;">
                            <i class="bi bi-emoji-frown me-1"></i>No se encontraron productos.
                        </p>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── COLUMNA DERECHA: Carrito + Resumen ────────────── -->
        <div class="col-lg-5">
            <div class="panel-venta">

                <!-- Carrito de compras -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-semibold">
                            <i class="bi bi-cart-check me-2 text-primary"></i>Lista de Compra
                        </h5>
                        <button class="btn btn-outline-danger btn-sm" id="btnVaciarCarrito" disabled>
                            <i class="bi bi-trash3 me-1"></i>Vaciar
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="tablaCarrito">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Producto</th>
                                        <th class="text-center">Cant.</th>
                                        <th class="text-end">Precio U.</th>
                                        <th class="text-end">Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="cuerpoCarrito">
                                    <!-- Generado por JS -->
                                </tbody>
                            </table>
                        </div>
                        <!-- Estado vacío -->
                        <div id="carritoVacio" class="text-center py-4 text-muted">
                            <i class="bi bi-cart-x fs-2 d-block mb-2"></i>
                            <span class="small">Agrega productos desde el buscador</span>
                        </div>
                    </div>
                </div>

                <!-- Resumen económico -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="resumen-linea">
                            <span class="text-muted">Subtotal</span>
                            <span id="resumenSubtotal">RD$ 0.00</span>
                        </div>
                        <div class="resumen-linea">
                            <span class="text-muted">ITBIS (18%)</span>
                            <span id="resumenITBIS">RD$ 0.00</span>
                        </div>
                        <div class="resumen-total mt-2">
                            <span>TOTAL</span>
                            <span id="resumenTotal">RD$ 0.00</span>
                        </div>

                        <div class="d-grid mt-3">
                            <button
                                class="btn btn-primary btn-lg fw-bold"
                                id="btnProcesar"
                                data-bs-toggle="modal"
                                data-bs-target="#modalCobro"
                                disabled
                            >
                                <i class="bi bi-cash-coin me-2"></i>PROCESAR VENTA
                            </button>
                        </div>
                    </div>
                </div>

            </div><!-- /panel-venta -->
        </div>

    </div><!-- /row -->
    <?php endif; ?>
</div><!-- /container-fluid -->


<!-- ══ MODAL DE COBRO ════════════════════════════════════════ -->
<div class="modal fade" id="modalCobro" tabindex="-1" aria-labelledby="modalCobroLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header" style="background-color:#4a90d9;">
                <h5 class="modal-title text-white fw-bold" id="modalCobroLabel">
                    <i class="bi bi-cash-register me-2"></i>Cobrar Venta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Formulario que envía los datos al backend -->
            <form method="POST" action="procesar_venta.php" id="formCobro">

                <!-- Carrito serializado como JSON -->
                <input type="hidden" name="carrito_json" id="carritoJson">

                <div class="modal-body">

                    <!-- Resumen de la venta -->
                    <div class="alert alert-light border mb-4 p-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Subtotal</span>
                            <span id="modalSubtotal">RD$ 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>ITBIS (18%)</span>
                            <span id="modalITBIS">RD$ 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold mt-2 pt-2 border-top" style="font-size:1.1rem;">
                            <span>Total a cobrar</span>
                            <span id="modalTotal" class="text-primary">RD$ 0.00</span>
                        </div>
                    </div>

                    <!-- Método de pago -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Método de Pago</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metodo_pago" id="pagoEfectivo" value="Efectivo" checked>
                                <label class="form-check-label" for="pagoEfectivo">
                                    <i class="bi bi-cash me-1"></i>Efectivo
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metodo_pago" id="pagoTarjeta" value="Tarjeta">
                                <label class="form-check-label" for="pagoTarjeta">
                                    <i class="bi bi-credit-card me-1"></i>Tarjeta
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metodo_pago" id="pagoTransferencia" value="Transferencia">
                                <label class="form-check-label" for="pagoTransferencia">
                                    <i class="bi bi-bank me-1"></i>Transferencia
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Efectivo recibido (solo visible si el método es Efectivo) -->
                    <div id="divEfectivo">
                        <div class="mb-3">
                            <label for="efectivoRecibido" class="form-label fw-semibold">
                                Efectivo Recibido (RD$)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">RD$</span>
                                <input
                                    type="number"
                                    id="efectivoRecibido"
                                    name="efectivo_recibido"
                                    class="form-control"
                                    min="0" step="0.01"
                                    placeholder="0.00"
                                >
                            </div>
                        </div>

                        <!-- Cambio / devuelta -->
                        <div id="divCambio" class="alert alert-success d-flex justify-content-between align-items-center py-2" style="display:none;">
                            <span><i class="bi bi-arrow-return-left me-1"></i>Cambio / Devuelta</span>
                            <strong id="valorCambio" class="text-success">RD$ 0.00</strong>
                        </div>

                        <!-- Monto faltante -->
                        <div id="divFaltante" class="alert alert-danger d-flex justify-content-between align-items-center py-2" style="display:none;">
                            <span><i class="bi bi-exclamation-circle me-1"></i>Falta por cobrar</span>
                            <strong id="valorFaltante" class="text-danger">RD$ 0.00</strong>
                        </div>
                    </div>

                </div><!-- /modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-success fw-bold px-4" id="btnConfirmar">
                        <i class="bi bi-check-circle me-1"></i>Confirmar Pago
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ════════════════════════════════════════════════════════════
   DATOS DEL SERVIDOR
════════════════════════════════════════════════════════════ */
const CATALOGO   = <?= $catalogoJson ?>;
const ITBIS_RATE = <?= ITBIS_RATE_POS ?>;

/* ════════════════════════════════════════════════════════════
   ESTADO DEL CARRITO
   Cada item: { id, nombre, precio, stock, cantidad }
════════════════════════════════════════════════════════════ */
let carrito = [];

/* ════════════════════════════════════════════════════════════
   UTILIDADES
════════════════════════════════════════════════════════════ */
/** Formatea número como "RD$ 1,250.00" */
function fmtRD(n) {
    return 'RD$ ' + Number(n).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/** Escapa HTML para evitar XSS al insertar cadenas en innerHTML */
function escHTML(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* ════════════════════════════════════════════════════════════
   BUSCADOR
════════════════════════════════════════════════════════════ */
const inputBusqueda    = document.getElementById('inputBusqueda');
const cuerpoResultados = document.getElementById('cuerpoResultados');
const sinResultados    = document.getElementById('sinResultados');
const btnLimpiar       = document.getElementById('btnLimpiarBusqueda');

/** Renderiza la tabla de resultados con la lista recibida */
function renderResultados(lista) {
    cuerpoResultados.innerHTML = '';

    if (lista.length === 0) {
        sinResultados.style.display = '';
        return;
    }
    sinResultados.style.display = 'none';

    lista.forEach(p => {
        const agotado    = p.Stock === 0;
        const esNuevo    = p.Estado.toLowerCase().trim() === 'nuevo';
        const badgeClass = esNuevo ? 'bg-success' : 'bg-warning text-dark';
        const stockClass = p.Stock < 5 ? 'text-danger fw-bold' : '';

        const tr = document.createElement('tr');
        tr.className = 'producto-card';
        tr.innerHTML = `
            <td class="ps-3 fw-semibold">${escHTML(p.Nombre)}</td>
            <td class="small">${escHTML(p.Categoria || '—')}</td>
            <td class="small">${escHTML(p.Talla || '—')}</td>
            <td><span class="badge ${badgeClass}">${escHTML(p.Estado)}</span></td>
            <td class="text-end">${fmtRD(p.Precio_Venta)}</td>
            <td class="text-center ${stockClass}">${p.Stock}</td>
            <td class="text-center">
                <button
                    class="btn btn-sm btn-primary py-0 px-2"
                    onclick="agregarAlCarrito(${p.Id})"
                    ${agotado ? 'disabled title="Sin stock"' : ''}
                >
                    <i class="bi bi-plus-lg"></i>
                </button>
            </td>`;
        cuerpoResultados.appendChild(tr);
    });
}

/** Filtra el catálogo según el término de búsqueda */
function filtrarCatalogo(termino) {
    if (!termino.trim()) return CATALOGO;
    const t = termino.toLowerCase();
    return CATALOGO.filter(p =>
        (p.Nombre    || '').toLowerCase().includes(t) ||
        (p.Categoria || '').toLowerCase().includes(t) ||
        (p.Talla     || '').toLowerCase().includes(t) ||
        (p.Color     || '').toLowerCase().includes(t) ||
        (p.Estado    || '').toLowerCase().includes(t)
    );
}

inputBusqueda.addEventListener('input', () => {
    renderResultados(filtrarCatalogo(inputBusqueda.value));
});

btnLimpiar.addEventListener('click', () => {
    inputBusqueda.value = '';
    renderResultados(CATALOGO);
    inputBusqueda.focus();
});

// Carga inicial con todos los productos
renderResultados(CATALOGO);

/* ════════════════════════════════════════════════════════════
   CARRITO
════════════════════════════════════════════════════════════ */
function agregarAlCarrito(id) {
    const producto = CATALOGO.find(p => p.Id === id);
    if (!producto) return;

    const existente = carrito.find(c => c.id === id);
    if (existente) {
        // Incrementa si no supera el stock
        if (existente.cantidad < producto.Stock) {
            existente.cantidad++;
        }
    } else {
        carrito.push({
            id:          producto.Id,
            nombre:      producto.Nombre,
            precio:      parseFloat(producto.Precio_Venta),
            stock:       producto.Stock,
            aplica_itbis: producto.Aplica_ITBIS == 1,
            cantidad:    1
        });
    }

    renderCarrito();
    renderResultados(filtrarCatalogo(inputBusqueda.value));
}

function quitarDelCarrito(id) {
    carrito = carrito.filter(c => c.id !== id);
    renderCarrito();
    renderResultados(filtrarCatalogo(inputBusqueda.value));
}

function cambiarCantidad(id, valor) {
    const item = carrito.find(c => c.id === id);
    if (!item) return;

    const nuevaCant = parseInt(valor, 10);
    if (isNaN(nuevaCant) || nuevaCant < 1) {
        item.cantidad = 1;
    } else if (nuevaCant > item.stock) {
        item.cantidad = item.stock;
    } else {
        item.cantidad = nuevaCant;
    }

    renderCarrito();
}

function renderCarrito() {
    const cuerpo       = document.getElementById('cuerpoCarrito');
    const carritoVacio = document.getElementById('carritoVacio');
    const btnProcesar  = document.getElementById('btnProcesar');
    const btnVaciar    = document.getElementById('btnVaciarCarrito');

    cuerpo.innerHTML = '';

    if (carrito.length === 0) {
        carritoVacio.style.display = '';
        btnProcesar.disabled = true;
        btnVaciar.disabled   = true;
        actualizarResumen(0, 0);
        return;
    }

    carritoVacio.style.display = 'none';
    btnProcesar.disabled = false;
    btnVaciar.disabled   = false;

    let subtotal   = 0;
    let totalItbis = 0;

    carrito.forEach(item => {
        const lineaSubtotal = item.precio * item.cantidad;
        subtotal += lineaSubtotal;
        if (item.aplica_itbis) {
            totalItbis += lineaSubtotal * ITBIS_RATE;
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="ps-3 fw-semibold small">${escHTML(item.nombre)}</td>
            <td class="text-center">
                <input
                    type="number"
                    class="form-control form-control-sm qty-input"
                    value="${item.cantidad}"
                    min="1"
                    max="${item.stock}"
                    onchange="cambiarCantidad(${item.id}, this.value)"
                    onblur="this.value = carrito.find(c => c.id === ${item.id})?.cantidad ?? 1"
                >
            </td>
            <td class="text-end small">${fmtRD(item.precio)}</td>
            <td class="text-end small fw-semibold">${fmtRD(lineaSubtotal)}</td>
            <td class="text-center">
                <button
                    class="btn btn-sm btn-outline-danger py-0 px-1"
                    onclick="quitarDelCarrito(${item.id})"
                    title="Quitar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>`;
        cuerpo.appendChild(tr);
    });

    actualizarResumen(subtotal, totalItbis);
}

function actualizarResumen(subtotal, itbis) {
    const total = subtotal + itbis;
    document.getElementById('resumenSubtotal').textContent = fmtRD(subtotal);
    document.getElementById('resumenITBIS').textContent    = fmtRD(itbis);
    document.getElementById('resumenTotal').textContent    = fmtRD(total);
}

document.getElementById('btnVaciarCarrito').addEventListener('click', () => {
    if (carrito.length === 0) return;
    if (confirm('¿Vaciar el carrito?')) {
        carrito = [];
        renderCarrito();
        renderResultados(filtrarCatalogo(inputBusqueda.value));
    }
});

/* ════════════════════════════════════════════════════════════
   MODAL DE COBRO
════════════════════════════════════════════════════════════ */
const modalCobro = document.getElementById('modalCobro');

modalCobro.addEventListener('show.bs.modal', () => {
    // Sincronizar totales del panel al modal
    document.getElementById('modalSubtotal').textContent = document.getElementById('resumenSubtotal').textContent;
    document.getElementById('modalITBIS').textContent    = document.getElementById('resumenITBIS').textContent;
    document.getElementById('modalTotal').textContent    = document.getElementById('resumenTotal').textContent;

    // Serializar el carrito para enviarlo al servidor
    document.getElementById('carritoJson').value = JSON.stringify(carrito);

    // Resetear campo de efectivo
    document.getElementById('efectivoRecibido').value = '';
    ocultarCambio();
});

// Mostrar/ocultar sección de efectivo según el método de pago
document.querySelectorAll('input[name="metodo_pago"]').forEach(radio => {
    radio.addEventListener('change', () => {
        document.getElementById('divEfectivo').style.display =
            radio.value === 'Efectivo' ? '' : 'none';
        if (radio.value !== 'Efectivo') ocultarCambio();
    });
});

// Calcular cambio en tiempo real
document.getElementById('efectivoRecibido').addEventListener('input', calcularCambio);

function totalVenta() {
    const txt = document.getElementById('resumenTotal').textContent;
    return parseFloat(txt.replace('RD$ ', '').replace(/,/g, '')) || 0;
}

function calcularCambio() {
    const recibido = parseFloat(document.getElementById('efectivoRecibido').value) || 0;
    const total    = totalVenta();
    const diff     = recibido - total;

    if (recibido <= 0) { ocultarCambio(); return; }

    const divCambio   = document.getElementById('divCambio');
    const divFaltante = document.getElementById('divFaltante');

    if (diff >= 0) {
        divCambio.style.display   = '';
        divFaltante.style.display = 'none';
        document.getElementById('valorCambio').textContent = fmtRD(diff);
    } else {
        divCambio.style.display   = 'none';
        divFaltante.style.display = '';
        document.getElementById('valorFaltante').textContent = fmtRD(Math.abs(diff));
    }
}

function ocultarCambio() {
    document.getElementById('divCambio').style.display   = 'none';
    document.getElementById('divFaltante').style.display = 'none';
}

// Validar el formulario antes de enviarlo al servidor
document.getElementById('formCobro').addEventListener('submit', function (e) {
    if (carrito.length === 0) {
        e.preventDefault();
        alert('El carrito está vacío.');
        return;
    }

    const metodo   = document.querySelector('input[name="metodo_pago"]:checked').value;
    const recibido = parseFloat(document.getElementById('efectivoRecibido').value) || 0;
    const total    = totalVenta();

    if (metodo === 'Efectivo' && recibido < total) {
        e.preventDefault();
        alert('El efectivo recibido (' + fmtRD(recibido) + ') es menor al total a cobrar (' + fmtRD(total) + ').');
    }
});
</script>

</body>
</html>
