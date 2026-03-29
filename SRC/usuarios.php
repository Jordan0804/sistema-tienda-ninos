<?php
/**
 * usuarios.php — Gestión de usuarios del sistema.
 * Solo accesible para el rol Admin.
 */
require_once 'auth.php';
requerirAdmin();
require_once 'db.php';

$msgExito = null;
$msgError = null;

// Mensajes redirigidos desde guardar_usuario.php
$accion = $_GET['accion'] ?? '';
if ($accion === 'creado')     $msgExito = 'Usuario creado correctamente.';
if ($accion === 'actualizado') $msgExito = 'Usuario actualizado correctamente.';
if ($accion === 'desactivado') $msgExito = 'Estado del usuario cambiado.';
if ($accion === 'error')      $msgError  = 'Ocurrió un error al procesar la solicitud.';
if ($accion === 'duplicado')  $msgError  = 'Ese nombre de usuario ya está en uso.';

// Cargar lista de usuarios
$usuarios = [];
try {
    $stmt = $conn->query("
        SELECT u.Id, u.Nombre, u.Usuario, u.Rol, u.Activo, u.FechaCreacion,
               c.Nombre AS CreadoPorNombre
        FROM   Usuarios u
        LEFT JOIN Usuarios c ON c.Id = u.CreadoPor
        ORDER  BY u.Id ASC
    ");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $msgError = 'Error al cargar usuarios: ' . $e->getMessage();
}

// Usuario a editar (modal pre-relleno)
$editUsuario = null;
if (isset($_GET['editar'])) {
    $editId = (int) $_GET['editar'];
    foreach ($usuarios as $u) {
        if ($u['Id'] === $editId) { $editUsuario = $u; break; }
    }
}

$paginaActiva = 'usuarios';
require 'navbar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios — Tienda Niños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f4f8; }
        .table thead th { background-color: #4a90d9; color: #fff; }
        .table tbody tr:hover { background-color: #eaf3ff; }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bi bi-people me-2 text-primary"></i>Gestión de Usuarios
        </h4>
        <button class="btn btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#modalUsuario">
            <i class="bi bi-person-plus me-2"></i>Nuevo Usuario
        </button>
    </div>

    <?php if ($msgExito): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msgExito) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($msgError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($msgError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ── Tabla de usuarios ──────────────────────────────────── -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Creado el</th>
                            <th>Creado por</th>
                            <th class="text-center pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-people fs-2 d-block mb-2"></i>
                                No hay usuarios registrados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $u):
                            $badgeRol = match ($u['Rol']) {
                                'Admin'      => 'bg-warning text-dark',
                                'Cajero'     => 'bg-success',
                                'Inventario' => 'bg-info text-dark',
                                default      => 'bg-secondary',
                            };
                            $esSelf = ((int)$u['Id'] === (int)$_SESSION['usuario_id']);
                        ?>
                        <tr class="<?= $u['Activo'] ? '' : 'table-secondary text-muted' ?>">
                            <td class="ps-3 text-muted small"><?= $u['Id'] ?></td>
                            <td class="fw-semibold">
                                <?= htmlspecialchars($u['Nombre']) ?>
                                <?php if ($esSelf): ?>
                                    <span class="badge bg-secondary ms-1 small">Tú</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted">@<?= htmlspecialchars($u['Usuario']) ?></td>
                            <td><span class="badge <?= $badgeRol ?>"><?= $u['Rol'] ?></span></td>
                            <td>
                                <?php if ($u['Activo']): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= htmlspecialchars(substr((string)$u['FechaCreacion'], 0, 10)) ?>
                            </td>
                            <td class="small text-muted">
                                <?= $u['CreadoPorNombre'] ? htmlspecialchars($u['CreadoPorNombre']) : '—' ?>
                            </td>
                            <td class="text-center pe-3">
                                <div class="d-flex gap-1 justify-content-center">
                                    <!-- Editar -->
                                    <a href="?editar=<?= $u['Id'] ?>#modalUsuario"
                                       class="btn btn-sm btn-outline-primary"
                                       title="Editar"
                                       onclick="abrirEditar(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>); return false;">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <!-- Toggle Activo/Inactivo (no aplica a sí mismo) -->
                                    <?php if (!$esSelf): ?>
                                    <form method="POST" action="guardar_usuario.php" class="d-inline">
                                        <input type="hidden" name="accion" value="toggle_activo">
                                        <input type="hidden" name="id"     value="<?= $u['Id'] ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?= $u['Activo'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                title="<?= $u['Activo'] ? 'Desactivar' : 'Activar' ?>">
                                            <i class="bi <?= $u['Activo'] ? 'bi-person-x' : 'bi-person-check' ?>"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /container -->

<!-- ══ Modal Crear / Editar Usuario ══════════════════════════ -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="modalUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="guardar_usuario.php" class="needs-validation" novalidate id="formUsuario">

                <div class="modal-header" style="background-color:#4a90d9;">
                    <h5 class="modal-title text-white fw-bold" id="modalUsuarioLabel">
                        <i class="bi bi-person-plus me-2"></i>
                        <span id="modalTitulo">Nuevo Usuario</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Campo oculto para edición -->
                    <input type="hidden" name="accion" id="campoAccion" value="crear">
                    <input type="hidden" name="id"     id="campoId"     value="">

                    <!-- Nombre Completo -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            Nombre Completo <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="nombre" id="campoNombre" class="form-control"
                               placeholder="Ej: Juan Pérez" maxlength="100" required>
                        <div class="invalid-feedback">El nombre es obligatorio.</div>
                    </div>

                    <!-- Nombre de Usuario -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            Nombre de Usuario <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">@</span>
                            <input type="text" name="usuario" id="campoUsuario" class="form-control"
                                   placeholder="ej: cajero1" maxlength="50"
                                   pattern="[a-zA-Z0-9_\-]+" required>
                        </div>
                        <div class="form-text">Solo letras, números, guiones y guiones bajos.</div>
                    </div>

                    <!-- Rol -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            Rol <span class="text-danger">*</span>
                        </label>
                        <select name="rol" id="campoRol" class="form-select" required>
                            <option value="Cajero">Cajero — Ventas e inventario</option>
                            <option value="Inventario">Inventario — Solo inventario</option>
                            <option value="Admin">Admin — Acceso total</option>
                        </select>
                    </div>

                    <!-- Contraseña -->
                    <div class="mb-3" id="bloquePassword">
                        <label class="form-label fw-semibold small">
                            Contraseña <span class="text-danger" id="passRequerido">*</span>
                            <span class="text-muted fw-normal" id="passOpcional" style="display:none;">(dejar vacío para no cambiar)</span>
                        </label>
                        <input type="password" name="password" id="campoPassword" class="form-control"
                               placeholder="Mínimo 6 caracteres" minlength="6">
                        <div class="invalid-feedback">Mínimo 6 caracteres.</div>
                    </div>

                    <!-- Confirmar Contraseña -->
                    <div class="mb-3" id="bloqueConfirmar">
                        <label class="form-label fw-semibold small">
                            Confirmar Contraseña <span class="text-danger" id="confirmRequerido">*</span>
                        </label>
                        <input type="password" name="confirmar" id="campoConfirmar" class="form-control"
                               placeholder="Repite la contraseña">
                        <div class="invalid-feedback">Las contraseñas no coinciden.</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-semibold">
                        <i class="bi bi-save me-2"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Abrir modal en modo Editar ───────────────────────────────
function abrirEditar(u) {
    document.getElementById('modalTitulo').textContent   = 'Editar Usuario';
    document.getElementById('campoAccion').value         = 'editar';
    document.getElementById('campoId').value             = u.Id;
    document.getElementById('campoNombre').value         = u.Nombre;
    document.getElementById('campoUsuario').value        = u.Usuario;
    document.getElementById('campoRol').value            = u.Rol;
    document.getElementById('campoPassword').value       = '';
    document.getElementById('campoConfirmar').value      = '';
    // Contraseña es opcional al editar
    document.getElementById('passRequerido').style.display = 'none';
    document.getElementById('passOpcional').style.display  = 'inline';
    document.getElementById('campoPassword').removeAttribute('required');
    const modal = new bootstrap.Modal(document.getElementById('modalUsuario'));
    modal.show();
}

// Restablecer modal a modo Crear al cerrarlo
document.getElementById('modalUsuario').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitulo').textContent  = 'Nuevo Usuario';
    document.getElementById('campoAccion').value        = 'crear';
    document.getElementById('campoId').value            = '';
    document.getElementById('formUsuario').reset();
    document.getElementById('formUsuario').classList.remove('was-validated');
    document.getElementById('passRequerido').style.display = 'inline';
    document.getElementById('passOpcional').style.display  = 'none';
    document.getElementById('campoPassword').setAttribute('required', '');
});

// ── Validación del formulario ────────────────────────────────
document.getElementById('formUsuario').addEventListener('submit', function (e) {
    const pwd1    = document.getElementById('campoPassword').value;
    const pwd2    = document.getElementById('campoConfirmar').value;
    const accion  = document.getElementById('campoAccion').value;

    // Si hay contraseña, debe tener mínimo 6 caracteres
    if (pwd1 && pwd1.length < 6) {
        document.getElementById('campoPassword').setCustomValidity('Mínimo 6 caracteres.');
    } else {
        document.getElementById('campoPassword').setCustomValidity('');
    }

    // Las contraseñas deben coincidir (solo si se ingresó alguna)
    if (pwd1 !== pwd2) {
        document.getElementById('campoConfirmar').setCustomValidity('No coinciden.');
    } else {
        document.getElementById('campoConfirmar').setCustomValidity('');
    }

    // Al crear, contraseña es obligatoria
    if (accion === 'crear' && !pwd1) {
        document.getElementById('campoPassword').setCustomValidity('La contraseña es obligatoria.');
    }

    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});
</script>

</body>
</html>
