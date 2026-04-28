<?php
// =========================================================================================
// 1. INICIALIZACIÓN DEL ESTADO Y CONTROL DE ACCESO
// =========================================================================================
// Arrancamos el motor de sesiones en la primera línea para evitar errores de envío de cabeceras (Headers already sent).
session_start();
require 'includes/db.php';

$error = '';

// Patrón Bouncer: Si el cliente ya posee un token de sesión válido, carece de sentido 
// mostrarle el formulario de login. Lo redirigimos transparentemente a la raíz del catálogo.
if (isset($_SESSION['usuario'])) {
    header("Location: /");
    exit();
}

// =========================================================================================
// 2. PROCESAMIENTO DEL PAYLOAD DE AUTENTICACIÓN
// =========================================================================================
// Solo evaluamos la lógica de autenticación si la petición llega por el método POST.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // CSRF: verificar token antes de procesar nada
    csrf_verify('/login');

    // Rate limiting: bloquear tras 5 intentos fallidos (5 min)
    if (rate_limit_login()) {
        $mins = rate_limit_minutos_restantes();
        $error = "Demasiados intentos fallidos. Espera {$mins} minuto(s) e inténtalo de nuevo.";
    } else {
        $user = trim($_POST['usuario']);
        $pass = trim($_POST['password']);

        $stmt = $conn->prepare("SELECT nombre, password, rol, foto FROM usuarios WHERE nombre = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $fila = $resultado->fetch_assoc();
            if (password_verify($pass, $fila['password']) || $pass === $fila['password']) {
                session_regenerate_id(true);
                $_SESSION['usuario'] = $fila['nombre'];
                $_SESSION['rol']     = $fila['rol'];
                $_SESSION['foto']    = $fila['foto'];
                rate_limit_reset();
                header("Location: /");
                exit();
            } else {
                rate_limit_fail();
                // Mensaje genérico: no revelamos si el usuario existe (anti-enumeration)
                $error = "Credenciales incorrectas.";
            }
        } else {
            rate_limit_fail();
            $error = "Credenciales incorrectas.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - IoriScans</title>
    
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/logo/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/logo/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/logo/favicon/favicon-16x16.png">
    
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css?v=<?php echo time(); ?>">

    <script>
        const savedTheme = localStorage.getItem('ioriscans_theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
</head>
<body class="login-body-wrapper">

    <div class="login-card p-4 p-sm-5 shadow-lg border-0">
        
        <div class="mb-4 text-center">
            <a href="/" class="d-inline-block text-decoration-none">
                <img src="/assets/img/logo/logo-ioriscans-horizontal.svg" alt="IoriScans" class="login-brand-logo" onerror="this.style.display='none';">
            </a>
            <p class="text-muted mt-2 fw-semibold">¡Hola de nuevo, lector!</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger py-2 px-3 text-start shadow-sm border-danger rounded-3" style="font-size: 0.9rem;">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <div class="mb-4 text-start">
                <label class="form-label fw-bold text-secondary small text-uppercase">Nombre de Usuario</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted px-3" style="border-radius: 10px 0 0 10px;">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="text" name="usuario" class="form-control bg-light border-start-0 login-input-style" placeholder="Ej: Admin" required style="border-radius: 0 10px 10px 0;">
                </div>
            </div>

            <div class="mb-4 text-start">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fw-bold text-secondary small text-uppercase m-0">Contraseña</label>
                    <a href="/recuperar_password" class="text-decoration-none text-iori fw-semibold" style="font-size: 0.85rem;">¿La olvidaste?</a>
                </div>
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted px-3" style="border-radius: 10px 0 0 10px;">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" name="password" class="form-control bg-light border-start-0 login-input-style" placeholder="Tu contraseña segura" required style="border-radius: 0 10px 10px 0;">
                </div>
            </div>
            
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-iori btn-lg fw-bold shadow-sm rounded-pill">
                    <i class="fas fa-sign-in-alt me-2"></i>Entrar
                </button>
            </div>
        </form>

        <div class="mt-4 pt-3 text-center border-top">
            <span class="text-muted fw-semibold">¿No tienes cuenta?</span> 
            <a href="/registro" class="text-iori text-decoration-none fw-bold ms-1">Regístrate gratis</a>
        </div>
        
    </div>

</body>
</html>