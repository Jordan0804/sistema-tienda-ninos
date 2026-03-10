<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Ropa - Tienda de Niños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center">
                    <h3>Registro de Mercancía</h3>
                </div>
                <div class="card-body">
                    <form action="guardar.php" method="POST">
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre de la Prenda</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Ej: Vestido de flores" required>
                        </div>

                        <div class="mb-3">
                            <label for="precio" class="form-label">Precio (RD$)</label>
                            <input type="number" step="0.01" name="precio" id="precio" class="form-control" placeholder="0.00" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Estado de la Ropa</label>
                            <select name="estado" class="form-select" required>
                                <option value="" selected disabled>Seleccione una opción</option>
                                <option value="Nuevo">Nueva</option>
                                <option value="Seminuevo">Seminueva</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="stock" class="form-label">Cantidad Inicial</label>
                            <input type="number" name="stock" id="stock" class="form-control" value="1" required>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción (Opcional)</label>
                            <textarea name="descripcion" id="descripcion" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">Guardar Producto</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
