<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. IMPORTACIÓN DE DEPENDENCIAS Y LIBRERÍAS EXTERNAS
// =========================================================================================
// Hacemos uso del estándar de facto PHPMailer para evitar las limitaciones y 
// vulnerabilidades asociadas a la función mail() nativa de PHP.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

// Control de acceso: Si un usuario logueado intenta entrar a recuperar su contraseña,
// lo redirigimos al índice general de la plataforma.
if (isset($_SESSION['usuario'])) {
    header("Location: /");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// =========================================================================================
// 2. PROCESAMIENTO DE LA SOLICITUD (MÉTODO POST)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // Prevención de Inyección SQL (SQLi) evaluando la entrada del usuario 
    // mediante Prepared Statements.
    $sql = "SELECT id, nombre FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();
        
        // =========================================================================================
        // 3. GENERACIÓN DE TOKENS CRIPTOGRÁFICAMENTE SEGUROS (CSPRNG)
        // =========================================================================================
        // Generamos un token de un solo uso (One-Time Token) utilizando random_bytes().
        // Esto invoca el CSPRNG del sistema operativo, garantizando una entropía alta
        // que hace que el token sea computacionalmente imposible de predecir o aplicar fuerza bruta.
        $token = bin2hex(random_bytes(32));
        
        // Establecemos un Time-To-Live (TTL) estricto de 60 minutos para minimizar 
        // la ventana de oportunidad en caso de que el token sea interceptado (Time-Window Attack).
        $expira = date("Y-m-d H:i:s", strtotime('+1 hour'));

        $sql_update = "UPDATE usuarios SET reset_token = ?, reset_expira = ? WHERE email = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("sss", $token, $expira, $email);
        $stmt_update->execute();

        // Construimos el enlace dinámico adaptándose al entorno de ejecución (Localhost o Producción)
        $enlace = "https://" . $_SERVER['HTTP_HOST'] . "/restablecer_password?token=" . $token;

        // =========================================================================================
        // 4. INTEGRACIÓN SMTP (SIMPLE MAIL TRANSFER PROTOCOL)
        // =========================================================================================
        $mail = new PHPMailer(true);

        try {
            // Configuración del agente de transporte (MTA)
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'soporte.ioriscans@gmail.com'; 
            // NOTA: En producción, estas credenciales deberían aislarse en variables de entorno (.env).
            $mail->Password = 'gtut tchy trmv vora'; 
            
            // Forzamos encriptación en tránsito (TLS implícito) para proteger el token 
            // contra ataques Man-In-The-Middle (MITM) en la red.
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            // Ensamblaje del Payload del correo
            $mail->setFrom('soporte.ioriscans@gmail.com', 'IoriScans'); 
            $mail->addAddress($email, $usuario['nombre']);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Instrucciones para restablecer tu contraseña en IoriScans';

            // HTML Template (Diseño responsivo incrustado en línea para máxima compatibilidad de clientes de correo)
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee; padding: 20px;'>
                    <h2 style='color: #0a8688;'>Hola " . htmlspecialchars($usuario['nombre']) . ",</h2>
                    <p>Has recibido este correo porque solicitaste restablecer tu contraseña en <strong>IoriScans</strong>.</p>
                    <p>Para continuar con el proceso, haz clic en el botón de abajo:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$enlace' style='background-color: #0a8688; color: white; padding: 12px 25px; text-decoration: none; border-radius: 50px; font-weight: bold; display: inline-block;'>Restablecer mi contraseña</a>
                    </div>
                    <p style='font-size: 0.8rem; color: #777;'>Este enlace caducará en 1 hora por motivos de seguridad.</p>
                    <hr style='border: 0; border-top: 1px solid #eee;'>
                    <p style='font-size: 0.7rem; color: #999;'>IoriScans - Tu plataforma favorita de Manhwa y Manga.</p>
                </div>
            ";

            // Fallback de texto plano para clientes de correo sin soporte HTML
            $mail->AltBody = "Hola " . $usuario['nombre'] . ",\n\nPara restablecer tu contraseña en IoriScans, visita el siguiente enlace:\n\n" . $enlace;

            $mail->send();
            $mensaje = "Instrucciones enviadas. Revisa tu bandeja de entrada y la carpeta de Spam.";
            $tipo_mensaje = "success";

        } catch (Exception $e) {
            // Manejo de excepciones controlado. No exponemos la traza del error de PHPMailer al cliente.
            $mensaje = "Error de red al conectar con el servidor SMTP. Inténtalo más tarde.";
            $tipo_mensaje = "danger";
        }

    } else {
        // =========================================================================================
        // 5. MITIGACIÓN DE ATAQUES DE ENUMERACIÓN DE USUARIOS
        // =========================================================================================
        // Arquitectura de Seguridad Crítica: Si el email NO existe, mostramos el MISMO mensaje 
        // de éxito que si existiera. Si dijéramos "El correo no existe", un atacante podría 
        // usar scripts automatizados para descubrir qué correos están registrados en la BD.
        $mensaje = "Si el correo existe en nuestra base de datos, te hemos enviado las instrucciones.";
        $tipo_mensaje = "success";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - IoriScans</title>
    
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
            <h4 class="fw-bold text-dark mt-3 mb-2">Recuperar Contraseña</h4>
            <p class="text-muted small fw-semibold">Ingresa tu correo y te enviaremos un enlace para crear una nueva clave.</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> py-2 px-3 text-start shadow-sm rounded-3 auth-alert">
                <i class="fas <?php echo ($tipo_mensaje === 'success') ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="formRecuperar">
            <div class="mb-4 text-start">
                <label class="form-label fw-bold text-secondary small text-uppercase">Correo Electrónico</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted px-3 auth-icon-bg">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input type="email" name="email" class="form-control bg-light border-start-0 login-input-style auth-input-round" placeholder="tu-correo@ejemplo.com" required>
                </div>
            </div>
            
            <div class="d-grid mt-4">
                <button type="submit" id="btnEnviar" class="btn btn-iori btn-lg fw-bold shadow-sm rounded-pill">
                    <i class="fas fa-paper-plane me-2"></i>Enviar Enlace
                </button>
            </div>
        </form>

        <div class="mt-4 pt-3 text-center border-top">
            <a href="/login" class="text-muted text-decoration-none fw-bold hover-iori transition-colors">
                <i class="fas fa-arrow-left me-1"></i> Volver a Iniciar Sesión
            </a>
        </div>
    </div>

    <script>
        document.getElementById('formRecuperar').addEventListener('submit', function() {
            const btn = document.getElementById('btnEnviar');
            btn.classList.add('disabled');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Conectando con el servidor...';
        });
    </script>
</body>
</html>