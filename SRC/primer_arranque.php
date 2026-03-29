<?php
/**
 * primer_arranque.php — Configuración inicial del sistema.
 * Solo accesible cuando NO existe ningún usuario en la base de datos.
 * Permite crear el primer usuario Administrador.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// Si ya está autenticado, no necesita estar aquí
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error       = null;
$exito       = false;
$puedeSetup  = false;
$tablaOk     = false;

try {
    require_once 'db.php';

    // Verificar que la tabla Usuarios existe
    $tablaOk = (bool) $conn->query("
        SELECT COUNT(1) FROM sys.tables WHERE name = 'Usuarios'
    ")->fetchColumn();

    if (!$tablaOk) {
        $error = 'La tabla Usuarios aún no existe. Ejecuta setup_ventas.sql primero.';
    } else {
        $totalUsuarios = (int) $conn->query("SELECT COUNT(*) FROM Usuarios")->fetchColumn();
        if ($totalUsuarios > 0) {
            // Ya hay usuarios registrados → ir al login
            header('Location: login.php');
            exit;
        }
        $puedeSetup = true;
    }

} catch (PDOException $e) {
    $error = 'Error de base de datos: ' . $e->getMessage();
}

// Procesar el formulario de creación del admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puedeSetup) {
    $nombre    = trim($_POST['nombre']    ?? '');
    $usuario   = trim($_POST['usuario']   ?? '');
    $password  = trim($_POST['password']  ?? '');
    $confirmar = trim($_POST['confirmar'] ?? '');

    if ($nombre === '' || $usuario === '' || $password === '') {
        $error = 'Todos los campos son obligatorios.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO Usuarios (Nombre, Usuario, Contrasena, Rol, Activo)
                VALUES (?, ?, ?, 'Admin', 1)
            ");
            $stmt->execute([$nombre, $usuario, $hash]);
            $exito = true;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UQ_Usuario')) {
                $error = 'Ese nombre de usuario ya está en uso.';
            } else {
                $error = 'Error al crear el usuario: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Inicial — Tienda Niños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #4a90d9 0%, #2c5f9e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">

            <div class="card shadow-lg rounded-4 overflow-hidden">

                <!-- Encabezado -->
                <div class="text-center py-4 px-4" style="background-color:#4a90d9;">
                    <i class="bi bi-person-gear text-white" style="font-size:3rem;"></i>
                    <h4 class="text-white fw-bold mt-2 mb-0">Primer Arranque</h4>
                    <small class="text-white opacity-75">Crea el Administrador del sistema</small>
                </div>

                <div class="card-body p-4">

                    <?php if ($exito): ?>
                    <!-- ── Éxito ──────────────────────────── -->
                    <div class="text-center py-3">
                        <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem;"></i>
                        <h5 class="fw-bold mt-3">¡Administrador creado!</h5>
                        <p class="text-muted small">Ya puedes iniciar sesión con tus credenciales.</p>
                        <a href="login.php" class="btn btn-primary px-5 mt-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Ir al Login
                        </a>
                    </div>

                    <?php elseif ($error && !$puedeSetup): ?>
                    <!-- ── Error de BD (tabla no existe) ──── -->
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                    <p class="small text-muted">
                        Necesitas ejecutar <code>setup_ventas.sql</code> en SSMS antes de continuar.
                    </p>

                    <?php else: ?>
                    <!-- ── Formulario de creación ─────────── -->
                    <p class="text-muted small mb-4">
                        <i class="bi bi-info-circle me-1 text-primary"></i>
                        Este formulario solo aparece una vez. El usuario que crees tendrá
                        <strong>acceso total</strong> al sistema y podrá crear a los demás usuarios.
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small">
                            <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">
                                Nombre Completo <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text" name="nombre" class="form-control"
                                placeholder="Ej: María García"
                                value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                                maxlength="100" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">
                                Nombre de Usuario <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">@</span>
                                <input
                                    type="text" name="usuario" class="form-control"
                                    placeholder="ej: admin"
                                    value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
                                    maxlength="50" pattern="[a-zA-Z0-9_\-]+" required>
                            </div>
                            <div class="form-text">Solo letras, números, guiones y guiones bajos.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">
                                Contraseña <span class="text-danger">*</span>
                            </label>
                            <input
                                type="password" name="password" id="pwd1" class="form-control"
                                placeholder="Mínimo 6 caracteres"
                                minlength="6" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small">
                                Confirmar Contraseña <span class="text-danger">*</span>
                            </label>
                            <input
                                type="password" name="confirmar" id="pwd2" class="form-control"
                                placeholder="Repite la contraseña" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg fw-bold">
                                <i class="bi bi-person-check me-2"></i>Crear Administrador
                            </button>
                        </div>

                    </form>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelector('form')?.addEventListener('submit', function (e) {
    const pwd1 = document.getElementById('pwd1')?.value;
    const pwd2 = document.getElementById('pwd2')?.value;
    if (pwd1 && pwd2 && pwd1 !== pwd2) {
        e.preventDefault();
        alert('Las contraseñas no coinciden.');
        return;
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
