<?php
/**
 * editar_producto.php — Formulario para modificar un producto existente.
 * Recibe el ID por GET, carga el producto y presenta el form pre-relleno.
 */
require_once 'auth.php';
requerirPermiso('inventario');
require_once 'db.php';

$id       = (int) ($_GET['id'] ?? 0);
$error    = null;
$producto = null;

if ($id <= 0) {
    header('Location: inventario.php');
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT Id, Nombre, Categoria, Talla, Color, Estado,
               Precio_Costo, Precio_Venta, Stock, Aplica_ITBIS, Descripcion
        FROM   Productos
        WHERE  Id = ?
    ");
    $stmt->execute([$id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        header('Location: inventario.php');
        exit;
    }

} catch (PDOException $e) {
    $error = $e->getMessage();
}

// Lista de categorías disponibles (debe coincidir con index.php)
$categorias = [
    'Camisetas', 'Pantalones', 'Vestidos', 'Faldas', 'Conjuntos',
    'Pijamas', 'Ropa Interior', 'Abrigos/Chaquetas', 'Zapatos', 'Accesorios', 'General',
];

$paginaActiva = 'inventario';
require 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto — Tienda Niños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>body { background-color: #f0f4f8; }</style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php else: ?>

            <div class="card shadow">
                <div class="card-header py-3" style="background-color:#4a90d9;">
                    <h4 class="mb-0 text-white fw-bold">
                        <i class="bi bi-pencil-square me-2"></i>Editar Producto
                        <span class="small fw-normal opacity-75">— #<?= $id ?></span>
                    </h4>
                </div>

                <div class="card-body p-4">
                    <form action="actualizar_producto.php" method="POST" class="needs-validation" novalidate>

                        <!-- Campo oculto con el ID del producto -->
                        <input type="hidden" name="id" value="<?= (int)$producto['Id'] ?>">

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
                                value="<?= htmlspecialchars($producto['Nombre']) ?>"
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
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>"
                                            <?= ($producto['Categoria'] === $cat) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="talla" class="form-label fw-semibold">Talla</label>
                                <select name="talla" id="talla" class="form-select">
                                    <option value="">— Sin especificar —</option>
                                    <?php
                                    $tallas = [
                                        'Bebé'          => ['0-3M','3-6M','6-9M','9-12M'],
                                        'Niños'         => ['1-2A','2-3A','3-4A','4-5A','5-6A','6-7A','7-8A','8-10A','10-12A'],
                                        'Adolescentes'  => ['XS','S','M','L','XL'],
                                        'Calzado'       => ['16','18','20','22','24','26','28','30','32','34'],
                                    ];
                                    foreach ($tallas as $grupo => $lista): ?>
                                        <optgroup label="<?= $grupo ?>">
                                            <?php foreach ($lista as $t): ?>
                                                <option value="<?= $t ?>" <?= ($producto['Talla'] === $t) ? 'selected' : '' ?>>
                                                    <?= $t ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
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
                                    value="<?= htmlspecialchars($producto['Color'] ?? '') ?>"
                                    maxlength="30"
                                >
                            </div>

                            <div class="col-md-6">
                                <label for="estado" class="form-label fw-semibold">
                                    Estado <span class="text-danger">*</span>
                                </label>
                                <select name="estado" id="estado" class="form-select" required>
                                    <option value="Nuevo"     <?= ($producto['Estado'] === 'Nuevo')     ? 'selected' : '' ?>>Nuevo</option>
                                    <option value="Seminuevo" <?= ($producto['Estado'] === 'Seminuevo') ? 'selected' : '' ?>>Seminuevo</option>
                                </select>
                            </div>
                        </div>

                        <!-- ── Precios ────────────────────────── -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="precio_costo" class="form-label fw-semibold">
                                    Precio de Costo (RD$) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">RD$</span>
                                    <input
                                        type="number" step="0.01" min="0"
                                        name="precio_costo" id="precio_costo"
                                        class="form-control"
                                        value="<?= number_format((float)$producto['Precio_Costo'], 2, '.', '') ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="precio_venta" class="form-label fw-semibold">
                                    Precio de Venta (RD$) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">RD$</span>
                                    <input
                                        type="number" step="0.01" min="0"
                                        name="precio_venta" id="precio_venta"
                                        class="form-control"
                                        value="<?= number_format((float)$producto['Precio_Venta'], 2, '.', '') ?>"
                                        required
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- ── Stock ──────────────────────────── -->
                        <div class="mb-3">
                            <label for="stock" class="form-label fw-semibold">
                                Stock (Unidades) <span class="text-danger">*</span>
                            </label>
                            <input
                                type="number" min="0"
                                name="stock" id="stock"
                                class="form-control"
                                value="<?= (int)$producto['Stock'] ?>"
                                required
                            >
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
                                    <?= $producto['Aplica_ITBIS'] ? 'checked' : '' ?>
                                >
                                <label class="form-check-label fw-semibold" for="aplica_itbis">
                                    Aplicar ITBIS (18%) a este producto
                                </label>
                            </div>
                        </div>

                        <!-- ── Descripción ────────────────────── -->
                        <div class="mb-4">
                            <label for="descripcion" class="form-label fw-semibold">Descripción</label>
                            <textarea
                                name="descripcion" id="descripcion"
                                class="form-control" rows="3" maxlength="500"
                            ><?= htmlspecialchars($producto['Descripcion'] ?? '') ?></textarea>
                        </div>

                        <!-- ── Botones ─────────────────────────── -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success flex-grow-1 fw-bold">
                                <i class="bi bi-save me-2"></i>Guardar Cambios
                            </button>
                            <a href="inventario.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i>Cancelar
                            </a>
                        </div>

                    </form>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelector('.needs-validation')?.addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});
</script>

</body>
</html>
