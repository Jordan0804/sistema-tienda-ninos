<?php
/**
 * login.php — Página de inicio de sesión.
 * - Si ya hay sesión activa, redirige al dashboard.
 * - Si la tabla Usuarios no existe o está vacía, redirige al primer arranque.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// Ya autenticado → dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

// Verificar que la tabla Usuarios existe y tiene al menos un usuario
try {
    require_once 'db.php';

    $tablaExiste = (bool) $conn->query("
        SELECT COUNT(1) FROM sys.tables WHERE name = 'Usuarios'
    ")->fetchColumn();

    if (!$tablaExiste || (int)$conn->query("SELECT COUNT(*) FROM Usuarios")->fetchColumn() === 0) {
        header('Location: primer_arranque.php');
        exit;
    }

} catch (PDOException) {
    // BD no configurada aún
    header('Location: primer_arranque.php');
    exit;
}

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario    = trim($_POST['usuario']    ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if ($usuario === '' || $contrasena === '') {
        $error = 'Ingresa tu usuario y contraseña.';
    } else {
        try {
            $stmt = $conn->prepare("
                SELECT Id, Nombre, Usuario, Contrasena, Rol, Activo
                FROM   Usuarios
                WHERE  Usuario = ?
            ");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($contrasena, $user['Contrasena'])) {
                $error = 'Usuario o contraseña incorrectos.';
            } elseif (!(bool)$user['Activo']) {
                $error = 'Tu cuenta está desactivada. Contacta al administrador.';
            } else {
                // Login correcto → crear sesión
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = (int)  $user['Id'];
                $_SESSION['nombre']     =        $user['Nombre'];
                $_SESSION['usuario']    =        $user['Usuario'];
                $_SESSION['rol']        =        $user['Rol'];

                $destino = $_SESSION['redirigir_a'] ?? 'dashboard.php';
                unset($_SESSION['redirigir_a']);
                header('Location: ' . $destino);
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Error de base de datos: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — Tienda Niños</title>
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

<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-9 col-md-6 col-lg-5 col-xl-4">

            <div class="card shadow-lg rounded-4 overflow-hidden">

                <!-- Encabezado de la tarjeta -->
                <div class="text-center py-4 px-4" style="background-color:#4a90d9;">
                    <i class="bi bi-shop text-white" style="font-size:3rem;"></i>
                    <h4 class="text-white fw-bold mt-2 mb-0">Tienda Niños</h4>
                    <small class="text-white opacity-75">Sistema de Facturación</small>
                </div>

                <!-- Formulario -->
                <div class="card-body p-4">
                    <h5 class="fw-semibold text-center mb-4">Iniciar Sesión</h5>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small">
                            <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">

                        <div class="mb-3">
                            <label for="usuario" class="form-label fw-semibold small">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                <input
                                    type="text"
                                    name="usuario"
                                    id="usuario"
                                    class="form-control"
                                    placeholder="Nombre de usuario"
                                    value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
                                    autocomplete="username"
                                    autofocus
                                    required
                                >
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="contrasena" class="form-label fw-semibold small">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                                <input
                                    type="password"
                                    name="contrasena"
                                    id="contrasena"
                                    class="form-control"
                                    placeholder="••••••••"
                                    autocomplete="current-password"
                                    required
                                >
                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    onclick="togglePwd()"
                                    title="Mostrar / ocultar">
                                    <i class="bi bi-eye" id="iconoPwd"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
                            </button>
                        </div>

                    </form>
                </div>

            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
    const input = document.getElementById('contrasena');
    const icon  = document.getElementById('iconoPwd');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>

</body>
</html>
