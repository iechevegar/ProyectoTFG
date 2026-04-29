<?php
// =========================================================================================
// 1. INICIALIZACIÓN DEL ESTADO DE SESIÓN
// =========================================================================================
// Arrancamos el motor de sesiones de PHP antes de emitir cualquier cabecera HTTP.
session_start();
require 'includes/db.php';

// Control de flujo (Bouncer): Si el usuario ya posee un token de sesión válido, 
// no tiene sentido que vea la pantalla de registro. Lo enrutamos a la raíz.
if (isset($_SESSION['usuario'])) {
    header("Location: /");
    exit();
}

$error = '';

// =========================================================================================
// 2. PROCESAMIENTO DEL PAYLOAD DE REGISTRO (MÉTODO POST)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify('/registro');

    $nombre       = trim($_POST['usuario']);
    $email        = trim($_POST['email']);
    $pass         = trim($_POST['password']);
    $confirm_pass = trim($_POST['confirm_password']);

    if (empty($nombre) || empty($email) || empty($pass)) {
        $error = "Todos los campos son obligatorios.";
    } elseif (!validar_email($email)) {
        $error = "El formato del correo electrónico no es válido.";
    } elseif (strlen($nombre) < 3 || strlen($nombre) > 50) {
        $error = "El nombre de usuario debe tener entre 3 y 50 caracteres.";
    } elseif ($pass !== $confirm_pass) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($pass) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        
        // --- FASE B: DETECCIÓN DE COLISIONES (UNIQUE CONSTRAINTS) ---
        // Comprobamos si el nombre de usuario o el correo electrónico ya existen en el sistema.
        // Utilizamos Prepared Statements de forma estricta para evitar Inyección SQL (SQLi).
        $sql = "SELECT id FROM usuarios WHERE nombre = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $nombre, $email);
        $stmt->execute();
        $stmt->store_result(); // Volcamos el resultado en memoria para poder contar las filas

        if ($stmt->num_rows > 0) {
            $error = "Ese nombre de usuario o email ya se encuentra registrado en la plataforma.";
        } else {
            
            // --- FASE C: CRIPTOGRAFÍA Y PREPARACIÓN DEL ESTADO ---
            // Cifrado unidireccional (Hashing): Aplicamos Bcrypt (PASSWORD_DEFAULT) para proteger 
            // la credencial en la base de datos. En caso de brecha de seguridad, las contraseñas son irrecuperables.
            $pass_hash = password_hash($pass, PASSWORD_DEFAULT);
            $rol = 'lector'; // Hardcodeamos el Rol Base (RBAC) para nuevos registros por seguridad.

            // --- FASE D: PERSISTENCIA DE DATOS ---
            $sql_insert = "INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("ssss", $nombre, $email, $pass_hash, $rol);

            if ($stmt_insert->execute()) {
                
                // =========================================================================================
                // 3. SECUENCIA DE AUTO-LOGIN (MEJORA DE UX)
                // =========================================================================================
                // En lugar de obligar al usuario a volver a escribir sus datos en el login, 
                // hidratamos la sesión inmediatamente.
                
                // MITIGACIÓN: Regeneramos el ID de la sesión anónima actual para prevenir ataques de Fijación de Sesión.
                session_regenerate_id(true); 
                
                $_SESSION['usuario'] = $nombre;
                $_SESSION['rol'] = $rol;
                $_SESSION['foto'] = null; // Instanciamos la variable visual a null (Fallback a iniciales en UI)
                
                // Enrutamiento semántico PRG (Post/Redirect/Get)
                header("Location: /");
                exit();
                // ----------------------------
            } else {
                // Fallback en caso de que el motor de base de datos falle durante la transacción
                $error = "Error transaccional al registrar la entidad. Inténtalo de nuevo.";
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
    <title>Registro - IoriScans</title>
    
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
        
        <div class="text-center mb-4">
            <a href="/" class="d-inline-block text-decoration-none">
                <img src="/assets/img/logo/logo-ioriscans-horizontal.svg" alt="IoriScans" class="login-brand-logo" onerror="this.style.display='none';">
            </a>
            <p class="text-muted mt-2 fw-semibold">Crea tu cuenta gratis</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger py-2 px-3 text-start shadow-sm border-danger rounded-3 auth-alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <div class="mb-4 text-start">
                <label class="form-label fw-bold text-secondary small text-uppercase">Nombre de Usuario</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted px-3 auth-icon-bg">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="text" name="usuario" class="form-control bg-light border-start-0 login-input-style auth-input-round" placeholder="Ej: LectorAnonimo" required>
                </div>
            </div>

            <div class="mb-4 text-start">
                <label class="form-label fw-bold text-secondary small text-uppercase">Email</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted px-3 auth-icon-bg">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input type="email" name="email" class="form-control bg-light border-start-0 login-input-style auth-input-round" placeholder="correo@ejemplo.com" required>
                </div>
            </div>

            <div class="mb-4 text-start">
                <label class="form-label fw-bold text-secondary small text-uppercase">Contraseña</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted px-3 auth-icon-bg">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" name="password" class="form-control bg-light border-start-0 login-input-style auth-input-round" placeholder="Mínimo 6 caracteres" required>
                </div>
            </div>

            <div class="mb-4 text-start">
                <label class="form-label fw-bold text-secondary small text-uppercase">Confirmar Contraseña</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted px-3 auth-icon-bg">
                        <i class="fas fa-check-circle"></i>
                    </span>
                    <input type="password" name="confirm_password" class="form-control bg-light border-start-0 login-input-style auth-input-round" placeholder="Repite la contraseña" required>
                </div>
            </div>
            
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-iori btn-lg fw-bold shadow-sm rounded-pill">
                    <i class="fas fa-user-plus me-2"></i>Registrarse y Entrar
                </button>
            </div>
        </form>

        <div class="mt-4 pt-3 text-center border-top">
            <span class="text-muted fw-semibold">¿Ya tienes cuenta?</span> 
            <a href="/login" class="text-iori text-decoration-none fw-bold ms-1 hover-iori transition-colors">Inicia Sesión</a>
        </div>
    </div>

</body>
</html>