<?php
// =========================================================================================
// 1. INICIALIZACIÓN Y CONFIGURACIÓN DEL ENTORNO
// =========================================================================================
session_start();
require 'includes/db.php';

$mensaje = '';
$tipo_mensaje = '';
$token_valido = false; // Bandera booleana para controlar el renderizado condicional de la UI
$email_usuario = '';

// =========================================================================================
// 2. MIDDLEWARE DE AUTORIZACIÓN: VALIDACIÓN CRIPTOGRÁFICA DEL TOKEN (MÉTODO GET)
// =========================================================================================
// El usuario accede a este script mediante el enlace enviado por correo.
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // --- TIME-WINDOW VALIDATION (TTL) ---
    // Consulta crítica: Buscamos el token en la base de datos, pero añadimos una 
    // restricción temporal a nivel de motor SQL (reset_expira > NOW()). 
    // Esto garantiza que si han pasado más de 60 minutos, la consulta no devuelva filas,
    // invalidando automáticamente el token sin necesidad de lógica extra en PHP.
    $sql = "SELECT email FROM usuarios WHERE reset_token = ? AND reset_expira > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $token_valido = true;
        // Hidratamos la variable con el email asociado al token para usarlo después en el POST
        $email_usuario = $resultado->fetch_assoc()['email'];
    } else {
        // Fallback genérico: No revelamos si el fallo es por token inexistente o por expiración.
        $mensaje = "Restricción de Seguridad: El enlace ha caducado o no es válido. Solicita uno nuevo.";
        $tipo_mensaje = "danger";
    }
} else {
    // Si se accede al script sin token, se deniega el acceso y se redirige a la zona de autenticación.
    header("Location: /login");
    exit();
}

// =========================================================================================
// 3. PROCESAMIENTO DE MUTACIÓN: ACTUALIZACIÓN DE CREDENCIALES (MÉTODO POST)
// =========================================================================================
// Verificamos doblemente: Debe ser una petición POST Y el token debe haber sido validado 
// previamente en el bloque GET. Esto evita ataques de inyección forzada de formularios.
if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valido) {
    $pass = trim($_POST['password']);
    $confirm_pass = trim($_POST['confirm_password']);

    // --- REGLAS DE NEGOCIO Y POLÍTICAS DE SEGURIDAD ---
    if (strlen($pass) < 6) {
        $mensaje = "Política de Seguridad: La contraseña debe tener una longitud mínima de 6 caracteres.";
        $tipo_mensaje = "danger";
    } elseif ($pass !== $confirm_pass) {
        $mensaje = "Error de validación: Las contraseñas proporcionadas difieren.";
        $tipo_mensaje = "danger";
    } else {
        
        // --- CRIPTOGRAFÍA DE CONTRASEÑAS ---
        // Generamos el Hash BCrypt. De nuevo, el texto plano de la contraseña 
        // nunca se almacena ni se expone.
        $pass_hash = password_hash($pass, PASSWORD_DEFAULT);
        
        // --- INVALIDACIÓN DEL TOKEN (PREVENCIÓN DE REPLAY ATTACKS) ---
        // Arquitectura fundamental: Al mismo tiempo que actualizamos el Hash de la contraseña, 
        // seteamos explícitamente las columnas `reset_token` y `reset_expira` a NULL. 
        // Esto transforma nuestro token en un verdadero "One-Time Token", garantizando que, 
        // si un atacante obtiene el enlace del correo a posteriori, no pueda reutilizarlo.
        $sql_update = "UPDATE usuarios SET password = ?, reset_token = NULL, reset_expira = NULL WHERE email = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ss", $pass_hash, $email_usuario);
        
        if ($stmt_update->execute()) {
            $mensaje = "¡Protocolo completado con éxito! Tu credencial ha sido actualizada de forma segura.";
            $tipo_mensaje = "success";
            // Mutamos el estado visual para ocultar el formulario y evitar envíos duplicados por accidente
            $token_valido = false; 
        } else {
            $mensaje = "Error transaccional en el motor relacional al actualizar la credencial.";
            $tipo_mensaje = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nueva Contraseña - IoriScans</title>

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
            <h4 class="fw-bold text-dark mt-3 mb-2">Restablecimiento Criptográfico</h4>
            <?php if($token_valido): ?>
                <p class="text-muted small fw-semibold">Define una nueva credencial segura para tu identidad.</p>
            <?php endif; ?>
        </div>
        
        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> py-2 px-3 text-start shadow-sm rounded-3 auth-alert border-<?php echo $tipo_mensaje; ?>">
                <?php if($tipo_mensaje === 'success'): ?>
                    <i class="fas fa-check-circle me-2"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-triangle me-2"></i>
                <?php endif; ?>
                <?php echo $mensaje; ?>
            </div>
            
            <?php if($tipo_mensaje === 'success'): ?>
                <div class="d-grid mt-4">
                    <a href="/login" class="btn btn-iori btn-lg fw-bold shadow-sm rounded-pill">
                        <i class="fas fa-sign-in-alt me-2"></i> Retornar al Gateway de Acceso
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if($token_valido): ?>
            <form method="POST" action="" id="formReset">
                <div class="mb-4 text-start">
                    <label class="form-label fw-bold text-secondary small text-uppercase">Nueva Contraseña</label>
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
                            <i class="fas fa-check-double"></i>
                        </span>
                        <input type="password" name="confirm_password" class="form-control bg-light border-start-0 login-input-style auth-input-round" placeholder="Repite la contraseña" required>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" id="btnGuardar" class="btn btn-iori btn-lg fw-bold shadow-sm rounded-pill">
                        <i class="fas fa-save me-2"></i>Persistir Contraseña
                    </button>
                </div>
            </form>
        <?php endif; ?>
        
        <?php if(!$token_valido && $tipo_mensaje === 'danger'): ?>
            <div class="mt-4 pt-3 text-center border-top">
                <a href="/recuperar_password" class="text-muted text-decoration-none fw-bold hover-iori transition-colors">
                    <i class="fas fa-redo-alt me-1"></i> Solicitar un nuevo Token de Acceso
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('formReset');
            if(form) {
                form.addEventListener('submit', function () {
                    const btn = document.getElementById('btnGuardar');
                    btn.classList.add('disabled');
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando cifrado...';
                });
            }
        });
    </script>
</body>
</html>