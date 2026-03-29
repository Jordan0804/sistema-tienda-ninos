<?php
/**
 * index.php — Formulario para registrar un nuevo producto.
 * Requiere permiso de gestión de inventario.
 */
require_once 'auth.php';
requerirPermiso('inventario');

$paginaActiva = 'nuevo_producto';
require 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Producto — Tienda Niños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f4f8; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">

            <div class="card shadow">
                <div class="card-header py-3" style="background-color:#4a90d9;">
                    <h4 class="mb-0 text-white fw-bold">
                        <i class="bi bi-plus-circle me-2"></i>Registrar Nuevo Producto
                    </h4>
                </div>

                <div class="card-body p-4">
                    <!-- novalidate permite que Bootstrap maneje los mensajes de error -->
                    <form action="guardar.php" method="POST" class="needs-validation" novalidate>

                        <!-- ── Nombre ──────────────────────────── -->
                        <div class="mb-3">
                            <label for="nombre" class="form-label fw-semibold">
                                Nombre de la Prenda <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                name="nombre"
                                id="nombre"
                                class="form-control"
                                placeholder="Ej: Vestido floral manga corta"
                                maxlength="100"
                                required
                            >
                            <div class="invalid-feedback">El nombre es obligatorio.</div>
                        </div>

                        <!-- ── Categoría + Talla ───────────────── -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="categoria" class="form-label fw-semibold">
                                    Categoría <span class="text-danger">*</span>
                                </label>
                                <select name="categoria" id="categoria" class="form-select" required>
                                    <option value="" disabled selected>Seleccione...</option>
                                    <option value="Camisetas">Camisetas</option>
                                    <option value="Pantalones">Pantalones</option>
                                    <option value="Vestidos">Vestidos</option>
                                    <option value="Faldas">Faldas</option>
                                    <option value="Conjuntos">Conjuntos</option>
                                    <option value="Pijamas">Pijamas</option>
                                    <option value="Ropa Interior">Ropa Interior</option>
                                    <option value="Abrigos/Chaquetas">Abrigos/Chaquetas</option>
                                    <option value="Zapatos">Zapatos</option>
                                    <option value="Accesorios">Accesorios</option>
                                    <option value="General">General</option>
                                </select>
                                <div class="invalid-feedback">Selecciona una categoría.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="talla" class="form-label fw-semibold">Talla</label>
                                <select name="talla" id="talla" class="form-select">
                                    <option value="">— Sin especificar —</option>
                                    <optgroup label="Bebé">
                                        <option value="0-3M">0-3 Meses</option>
                                        <option value="3-6M">3-6 Meses</option>
                                        <option value="6-9M">6-9 Meses</option>
                                        <option value="9-12M">9-12 Meses</option>
                                    </optgroup>
                                    <optgroup label="Niños">
                                        <option value="1-2A">1-2 Años</option>
                                        <option value="2-3A">2-3 Años</option>
                                        <option value="3-4A">3-4 Años</option>
                                        <option value="4-5A">4-5 Años</option>
                                        <option value="5-6A">5-6 Años</option>
                                        <option value="6-7A">6-7 Años</option>
                                        <option value="7-8A">7-8 Años</option>
                                        <option value="8-10A">8-10 Años</option>
                                        <option value="10-12A">10-12 Años</option>
                                    </optgroup>
                                    <optgroup label="Adolescentes">
                                        <option value="XS">XS</option>
                                        <option value="S">S</option>
                                        <option value="M">M</option>
                                        <option value="L">L</option>
                                        <option value="XL">XL</option>
                                    </optgroup>
                                    <optgroup label="Calzado (número)">
                                        <option value="16">16</option>
                                        <option value="18">18</option>
                                        <option value="20">20</option>
                                        <option value="22">22</option>
                                        <option value="24">24</option>
                                        <option value="26">26</option>
                                        <option value="28">28</option>
                                        <option value="30">30</option>
                                        <option value="32">32</option>
                                        <option value="34">34</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>

                        <!-- ── Color + Estado ─────────────────── -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="color" class="form-label fw-semibold">Color</label>
                                <input
                                    type="text"
                                    name="color"
                                    id="color"
                                    class="form-control"
                                    placeholder="Ej: Azul marino, Rosado, Multicolor"
                                    maxlength="30"
                                >
                            </div>

                            <div class="col-md-6">
                                <label for="estado" class="form-label fw-semibold">
                                    Estado <span class="text-danger">*</span>
                                </label>
                                <select name="estado" id="estado" class="form-select" required>
                                    <option value="" disabled selected>Seleccione...</option>
                                    <option value="Nuevo">Nuevo</option>
                                    <option value="Seminuevo">Seminuevo</option>
                                </select>
                                <div class="invalid-feedback">Selecciona el estado.</div>
                            </div>
                        </div>

                        <!-- ── Precio de Costo + Precio de Venta ─ -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="precio_costo" class="form-label fw-semibold">
                                    Precio de Costo (RD$) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">RD$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="precio_costo"
                                        id="precio_costo"
                                        class="form-control"
                                        placeholder="0.00"
                                        required
                                    >
                                    <div class="invalid-feedback">Ingresa el precio de costo.</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="precio_venta" class="form-label fw-semibold">
                                    Precio de Venta (RD$) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">RD$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="precio_venta"
                                        id="precio_venta"
                                        class="form-control"
                                        placeholder="0.00"
                                        required
                                    >
                                    <div class="invalid-feedback">Ingresa el precio de venta.</div>
                                </div>
                                <!-- Muestra el precio con ITBIS en tiempo real -->
                                <div class="form-text" id="precioConItbis"></div>
                            </div>
                        </div>

                        <!-- ── Stock ──────────────────────────── -->
                        <div class="mb-3">
                            <label for="stock" class="form-label fw-semibold">
                                Cantidad Inicial (Stock) <span class="text-danger">*</span>
                            </label>
                            <input
                                type="number"
                                name="stock"
                                id="stock"
                                class="form-control"
                                value="1"
                                min="0"
                                required
                            >
                            <div class="invalid-feedback">Ingresa la cantidad inicial.</div>
                        </div>

                        <!-- ── Aplica ITBIS ────────────────────── -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                    name="aplica_itbis"
                                    id="aplica_itbis"
                                    checked
                                >
                                <label class="form-check-label fw-semibold" for="aplica_itbis">
                                    Aplicar ITBIS (18%) a este producto
                                </label>
                            </div>
                            <div class="form-text">
                                Actívalo si este producto está sujeto al impuesto ITBIS del 18%.
                            </div>
                        </div>

                        <!-- ── Descripción ────────────────────── -->
                        <div class="mb-4">
                            <label for="descripcion" class="form-label fw-semibold">
                                Descripción <span class="text-muted small fw-normal">(Opcional)</span>
                            </label>
                            <textarea
                                name="descripcion"
                                id="descripcion"
                                class="form-control"
                                rows="3"
                                maxlength="500"
                                placeholder="Detalles adicionales: material, diseño, marca..."
                            ></textarea>
                        </div>

                        <!-- ── Botones ─────────────────────────── -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success flex-grow-1 fw-bold">
                                <i class="bi bi-save me-2"></i>Guardar Producto
                            </button>
                            <a href="inventario.php" class="btn btn-outline-secondary">
                                <i class="bi bi-box-seam me-1"></i>Ver Inventario
                            </a>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Validación nativa de Bootstrap ──────────────────────────
document.querySelector('.needs-validation').addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});

// ── Mostrar precio con ITBIS en tiempo real ──────────────────
const ITBIS_RATE = 0.18;

function actualizarItbisPreview() {
    const precio     = parseFloat(document.getElementById('precio_venta').value) || 0;
    const aplicaItbis = document.getElementById('aplica_itbis').checked;
    const span        = document.getElementById('precioConItbis');

    if (precio > 0 && aplicaItbis) {
        const conItbis = precio * (1 + ITBIS_RATE);
        span.textContent = 'Precio con ITBIS (18%): RD$ ' + conItbis.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        span.className = 'form-text text-success fw-semibold';
    } else if (precio > 0) {
        span.textContent = 'Sin ITBIS — precio final: RD$ ' + precio.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        span.className = 'form-text text-muted';
    } else {
        span.textContent = '';
    }
}

document.getElementById('precio_venta').addEventListener('input', actualizarItbisPreview);
document.getElementById('aplica_itbis').addEventListener('change', actualizarItbisPreview);
</script>

</body>
</html>
