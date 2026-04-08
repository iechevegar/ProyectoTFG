<?php
session_start();
require 'includes/db.php';

// Importamos las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Requerimos los archivos que has descargado
require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

if (isset($_SESSION['usuario'])) {
    header("Location: /");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    $sql = "SELECT id, nombre FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();

        $token = bin2hex(random_bytes(32));
        $expira = date("Y-m-d H:i:s", strtotime('+1 hour'));

        $sql_update = "UPDATE usuarios SET reset_token = ?, reset_expira = ? WHERE email = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("sss", $token, $expira, $email);
        $stmt_update->execute();

        $enlace = "https://" . $_SERVER['HTTP_HOST'] . "/restablecer_password?token=" . $token;

        // --- INICIO DE CONFIGURACIÓN PHPMAILER ---
        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor SMTP (Gmail)
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'soporte.ioriscans@gmail.com'; // <--- PON TU GMAIL AQUÍ
            $mail->Password = 'gtut tchy trmv vora'; // <--- PON LA CONTRASEÑA DE APLICACIÓN AQUÍ
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            // Remitente y Destinatario
            $mail->setFrom('soporte.ioriscans@gmail.com', 'IoriScans'); // <--- PON TU GMAIL AQUÍ TAMBIÉN
            $mail->addAddress($email, $usuario['nombre']);

            // Contenido del correo
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Instrucciones para restablecer tu contraseña en IoriScans';

            // Cuerpo en HTML (lo que ve el usuario)
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee; padding: 20px;'>
                    <h2 style='color: #0d6efd;'>Hola " . htmlspecialchars($usuario['nombre']) . ",</h2>
                    <p>Has recibido este correo porque solicitaste restablecer tu contraseña en <strong>IoriScans</strong>.</p>
                    <p>Para continuar con el proceso, haz clic en el botón de abajo. Si no has solicitado este cambio, puedes ignorar este mensaje de forma segura.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$enlace' style='background-color: #0d6efd; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Restablecer mi contraseña</a>
                    </div>
                    <p style='font-size: 0.8rem; color: #777;'>Este enlace caducará en 1 hora por motivos de seguridad.</p>
                    <hr style='border: 0; border-top: 1px solid #eee;'>
                    <p style='font-size: 0.7rem; color: #999;'>IoriScans - Tu plataforma favorita de Manhwa y Manga.</p>
                </div>
            ";

            // Texto plano (Crucial para evitar Spam)
            $mail->AltBody = "Hola " . $usuario['nombre'] . ",\n\nPara restablecer tu contraseña en IoriScans, copia y pega el siguiente enlace en tu navegador:\n\n" . $enlace;

            $mail->send();

            // Mensaje de éxito limpio
            $mensaje = "Si el correo existe en nuestra base de datos, te hemos enviado las instrucciones para recuperar tu contraseña. Revisa tu bandeja de entrada (y la carpeta de Spam).";
            $tipo_mensaje = "success";

        } catch (Exception $e) {
            // Si falla el servidor de correo, seguimos mostrando el enlace verde para no bloquearnos en desarrollo
            $mensaje = "Error al enviar el correo, pero estamos en modo desarrollo.<br><br>
                        <small class='text-muted'>Enlace directo: <a href='$enlace' class='fw-bold text-success'>Clic Aquí</a></small>";
            $tipo_mensaje = "warning";
        }
        // --- FIN CONFIGURACIÓN PHPMAILER ---

    } else {
        // Mensaje genérico por seguridad (anti-rastreo de correos)
        $mensaje = "Si el correo existe en nuestra base de datos, te hemos enviado las instrucciones para recuperar tu contraseña. Revisa tu bandeja de entrada (y la carpeta de Spam).";
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
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/all.min.css">
</head>

<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">

    <div class="login-container bg-white p-4 shadow-sm" style="max-width: 400px; width: 100%; border-radius: 12px;">
        <div class="text-center mb-4">
            <a href="/" class="text-decoration-none text-dark fs-2 fw-bold">
                <i class="fas fa-key me-2 text-primary"></i>IoriScans
            </a>
            <p class="text-muted mt-2">Te enviaremos instrucciones a tu correo</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> py-3" style="font-size: 0.9rem;">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4 text-start">
                <label class="form-label fw-bold small">Correo Electrónico</label>
                <input type="email" name="email" class="form-control bg-light" placeholder="correo@ejemplo.com"
                    required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary fw-bold py-2 shadow-sm">Enviar Enlace</button>
            </div>
        </form>

        <div class="mt-4 text-center small text-muted border-top pt-3">
            <a href="/login" class="text-secondary text-decoration-none fw-bold"><i class="fas fa-arrow-left me-1"></i>
                Volver a Iniciar Sesión</a>
        </div>
    </div>
</body>

</html>