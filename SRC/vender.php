<?php
/**
 * vender.php — Módulo de punto de venta.
 * Carga el catálogo desde BD; el carrito se gestiona en JS del lado cliente.
 * Al confirmar el pago, envía los datos a procesar_venta.php.
 */

$dbError  = null;
$catalogo = [];

try {
    require_once 'db.php';

    $stmt = $conn->query(
        "SELECT Id, Nombre, Precio, Estado, Stock
         FROM   Productos
         WHERE  Stock > 0
         ORDER  BY Nombre ASC"
    );
    $catalogo = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

// Serializa el catálogo a JSON para consumo en JS (escapado para inserción segura en HTML)
$catalogoJson = json_encode($catalogo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

const ITBIS_RATE = 0.18;
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
        body { background-color: #f8f9fa; }

        /* ── Encabezado de tablas (mismo azul que inventario.php) ── */
        .table thead th {
            background-color: #4a90d9;
            color: #fff;
            font-weight: 600;
            white-space: nowrap;
        }
        .table tbody tr:hover { background-color: #eaf3ff; }

        /* ── Panel derecho sticky ── */
        .panel-venta {
            position: sticky;
            top: 1.5rem;
        }

        /* ── Resultados del buscador ── */
        #resultadosBusqueda {
            max-height: 420px;
            overflow-y: auto;
        }
        .producto-card {
            cursor: pointer;
            transition: background-color .15s;
        }
        .producto-card:hover { background-color: #eaf3ff; }

        /* ── Resumen ── */
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

        /* ── Cambio / devuelta ── */
        #divCambio { display: none; }
        .cambio-positivo { color: #198754; font-weight: 700; }
        .cambio-negativo { color: #dc3545; font-weight: 700; }

        /* ── Input qty en carrito ── */
        .qty-input {
            width: 70px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">

    <!-- ══ ENCABEZADO ══════════════════════════════════════════════ -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold">
                <i class="bi bi-cart3 me-2 text-primary"></i>Nueva Venta
            </h2>
            <small class="text-muted">Sistema de Facturación — Tienda Niños</small>
        </div>
        <div class="d-flex gap-2">
            <a href="inventario.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-box-seam me-1"></i>Inventario
            </a>
            <a href="index.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Nuevo Producto
            </a>
        </div>
    </div>

    <?php if ($dbError): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error de conexión:</strong> <?= htmlspecialchars($dbError) ?>
        </div>
    <?php else: ?>

    <!-- ══ LAYOUT DOS COLUMNAS ═════════════════════════════════════ -->
    <div class="row g-4">

        <!-- ── COLUMNA IZQUIERDA: Buscador + Resultados ──────────── -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-search me-2 text-primary"></i>Buscar Productos
                    </h5>
                </div>
                <div class="card-body">

                    <!-- Buscador -->
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input
                            type="text"
                            id="inputBusqueda"
                            class="form-control border-start-0 ps-0"
                            placeholder="Escribe para buscar un producto..."
                            autocomplete="off"
                        >
                        <button class="btn btn-outline-secondary" type="button" id="btnLimpiarBusqueda" title="Limpiar">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Tabla de resultados -->
                    <div id="resultadosBusqueda">
                        <table class="table table-sm align-middle mb-0" id="tablaResultados">
                            <thead>
                                <tr>
                                    <th class="ps-3">Producto</th>
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

        <!-- ── COLUMNA DERECHA: Carrito + Resumen ─────────────────── -->
        <div class="col-lg-5">
            <div class="panel-venta">

                <!-- Carrito -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-semibold">
                            <i class="bi bi-cart-check me-2 text-primary"></i>Lista de Compra
                        </h5>
                        <button class="btn btn-outline-danger btn-sm" id="btnVaciarCarrito" title="Vaciar carrito">
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

                        <!-- Botón procesar -->
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
        </div><!-- /col-lg-5 -->

    </div><!-- /row -->

    <?php endif; ?>
</div><!-- /container-fluid -->


<!-- ══ MODAL DE COBRO ══════════════════════════════════════════════ -->
<div class="modal fade" id="modalCobro" tabindex="-1" aria-labelledby="modalCobroLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header" style="background-color:#4a90d9;">
                <h5 class="modal-title text-white fw-bold" id="modalCobroLabel">
                    <i class="bi bi-cash-register me-2"></i>Cobrar Venta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Formulario que envía a procesar_venta.php -->
            <form method="POST" action="procesar_venta.php" id="formCobro">

                <!-- Campo oculto con el carrito serializado -->
                <input type="hidden" name="carrito_json" id="carritoJson">

                <div class="modal-body">

                    <!-- Resumen compacto de la venta -->
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

                    <!-- Efectivo recibido + cambio (solo en pago en efectivo) -->
                    <div id="divEfectivo">
                        <div class="mb-3">
                            <label for="efectivoRecibido" class="form-label fw-semibold">Efectivo Recibido (RD$)</label>
                            <div class="input-group">
                                <span class="input-group-text">RD$</span>
                                <input
                                    type="number"
                                    id="efectivoRecibido"
                                    name="efectivo_recibido"
                                    class="form-control"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                >
                            </div>
                        </div>

                        <div id="divCambio" class="alert alert-success d-flex justify-content-between align-items-center py-2">
                            <span><i class="bi bi-arrow-return-left me-1"></i>Cambio / Devuelta</span>
                            <strong id="valorCambio" class="cambio-positivo">RD$ 0.00</strong>
                        </div>

                        <div id="divFaltante" class="alert alert-danger d-flex justify-content-between align-items-center py-2" style="display:none !important;">
                            <span><i class="bi bi-exclamation-circle me-1"></i>Falta por cobrar</span>
                            <strong id="valorFaltante" class="cambio-negativo">RD$ 0.00</strong>
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
<!-- /modal -->


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════════════
   DATOS DEL SERVIDOR
══════════════════════════════════════════════════════════════ */
const CATALOGO   = <?= $catalogoJson ?>;
const ITBIS_RATE = <?= ITBIS_RATE ?>;

/* ══════════════════════════════════════════════════════════════
   ESTADO DEL CARRITO
══════════════════════════════════════════════════════════════ */
let carrito = []; // [{ id, nombre, precio, stock, cantidad }]

/* ══════════════════════════════════════════════════════════════
   UTILIDADES
══════════════════════════════════════════════════════════════ */
function fmtRD(n) {
    return 'RD$ ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ══════════════════════════════════════════════════════════════
   BUSCADOR
══════════════════════════════════════════════════════════════ */
const inputBusqueda   = document.getElementById('inputBusqueda');
const cuerpoResultados = document.getElementById('cuerpoResultados');
const sinResultados   = document.getElementById('sinResultados');
const btnLimpiar      = document.getElementById('btnLimpiarBusqueda');

function renderResultados(lista) {
    cuerpoResultados.innerHTML = '';

    if (lista.length === 0) {
        sinResultados.style.display = '';
        return;
    }
    sinResultados.style.display = 'none';

    lista.forEach(p => {
        const enCarrito  = carrito.find(c => c.id === p.Id);
        const agotado    = p.Stock === 0;
        const esNuevo    = p.Estado.toLowerCase().trim() === 'nuevo';
        const badgeClass = esNuevo ? 'bg-success' : 'bg-warning text-dark';
        const stockClass = p.Stock < 5 ? 'text-danger fw-bold' : '';

        const tr = document.createElement('tr');
        tr.className = 'producto-card';
        tr.innerHTML = `
            <td class="ps-3 fw-semibold">${escHTML(p.Nombre)}</td>
            <td><span class="badge ${badgeClass}">${escHTML(p.Estado)}</span></td>
            <td class="text-end">${fmtRD(p.Precio)}</td>
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

function filtrarCatalogo(termino) {
    if (!termino.trim()) return CATALOGO;
    const t = termino.toLowerCase();
    return CATALOGO.filter(p =>
        p.Nombre.toLowerCase().includes(t) ||
        p.Estado.toLowerCase().includes(t)
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

// Render inicial con todos los productos
renderResultados(CATALOGO);

/* ══════════════════════════════════════════════════════════════
   CARRITO
══════════════════════════════════════════════════════════════ */
function agregarAlCarrito(id) {
    const producto = CATALOGO.find(p => p.Id === id);
    if (!producto) return;

    const existente = carrito.find(c => c.id === id);
    if (existente) {
        if (existente.cantidad < producto.Stock) {
            existente.cantidad++;
        }
    } else {
        carrito.push({
            id:       producto.Id,
            nombre:   producto.Nombre,
            precio:   parseFloat(producto.Precio),
            stock:    producto.Stock,
            cantidad: 1
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

    let subtotal = 0;

    carrito.forEach(item => {
        const lineaSubtotal = item.precio * item.cantidad;
        subtotal += lineaSubtotal;

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
                    onblur="this.value = carrito.find(c=>c.id===${item.id})?.cantidad ?? 1"
                >
            </td>
            <td class="text-end small">${fmtRD(item.precio)}</td>
            <td class="text-end small fw-semibold">${fmtRD(lineaSubtotal)}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="quitarDelCarrito(${item.id})" title="Quitar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>`;
        cuerpo.appendChild(tr);
    });

    const itbis = subtotal * ITBIS_RATE;
    actualizarResumen(subtotal, itbis);
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

/* ══════════════════════════════════════════════════════════════
   MODAL DE COBRO
══════════════════════════════════════════════════════════════ */
const modalCobro = document.getElementById('modalCobro');

modalCobro.addEventListener('show.bs.modal', () => {
    // Copiar totales al modal
    document.getElementById('modalSubtotal').textContent = document.getElementById('resumenSubtotal').textContent;
    document.getElementById('modalITBIS').textContent    = document.getElementById('resumenITBIS').textContent;
    document.getElementById('modalTotal').textContent    = document.getElementById('resumenTotal').textContent;

    // Serializar el carrito para el campo oculto
    document.getElementById('carritoJson').value = JSON.stringify(carrito);

    // Reset campo efectivo
    document.getElementById('efectivoRecibido').value = '';
    ocultarCambio();
});

// Mostrar / ocultar sección de efectivo según método de pago
document.querySelectorAll('input[name="metodo_pago"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const divEfectivo = document.getElementById('divEfectivo');
        divEfectivo.style.display = radio.value === 'Efectivo' ? '' : 'none';
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

    const divCambio   = document.getElementById('divCambio');
    const divFaltante = document.getElementById('divFaltante');

    if (recibido <= 0) {
        ocultarCambio();
        return;
    }

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

// Validar antes de enviar el formulario
document.getElementById('formCobro').addEventListener('submit', function (e) {
    const metodo    = document.querySelector('input[name="metodo_pago"]:checked').value;
    const recibido  = parseFloat(document.getElementById('efectivoRecibido').value) || 0;
    const total     = totalVenta();

    if (metodo === 'Efectivo' && recibido < total) {
        e.preventDefault();
        alert('El efectivo recibido (RD$ ' + recibido.toFixed(2) + ') es menor al total a cobrar (' + fmtRD(total) + ').');
        return;
    }

    if (carrito.length === 0) {
        e.preventDefault();
        alert('El carrito está vacío.');
    }
});

/* ══════════════════════════════════════════════════════════════
   HELPERS
══════════════════════════════════════════════════════════════ */
function escHTML(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>

</body>
</html>
