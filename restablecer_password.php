<?php
session_start();
require 'includes/db.php';

$mensaje = '';
$tipo_mensaje = '';
$token_valido = false;
$email_usuario = '';

// 1. Verificar Token por GET
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $sql = "SELECT email FROM usuarios WHERE reset_token = ? AND reset_expira > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $token_valido = true;
        $email_usuario = $resultado->fetch_assoc()['email'];
    } else {
        $mensaje = "El enlace ha caducado o no es válido. Solicita uno nuevo.";
        $tipo_mensaje = "danger";
    }
} else {
    header("Location: /login");
    exit();
}

// 2. Procesar cambio de contraseña por POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valido) {
    $pass = trim($_POST['password']);
    $confirm_pass = trim($_POST['confirm_password']);

    if (strlen($pass) < 6) {
        $mensaje = "La contraseña debe tener al menos 6 caracteres.";
        $tipo_mensaje = "danger";
    } elseif ($pass !== $confirm_pass) {
        $mensaje = "Las contraseñas no coinciden.";
        $tipo_mensaje = "danger";
    } else {
        $pass_hash = password_hash($pass, PASSWORD_DEFAULT);
        
        // Actualizamos contraseña y vaciamos el token para que no se pueda reusar
        $sql_update = "UPDATE usuarios SET password = ?, reset_token = NULL, reset_expira = NULL WHERE email = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ss", $pass_hash, $email_usuario);
        
        if ($stmt_update->execute()) {
            $mensaje = "¡Contraseña actualizada con éxito! Ya puedes iniciar sesión.";
            $tipo_mensaje = "success";
            $token_valido = false; // Ocultar el formulario
        } else {
            $mensaje = "Error al actualizar la contraseña.";
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
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/all.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">

    <div class="login-container bg-white p-4 shadow-sm" style="max-width: 400px; width: 100%; border-radius: 12px;">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-dark"><i class="fas fa-lock me-2 text-success"></i>Nueva Contraseña</h3>
        </div>
        
        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> py-2 text-center">
                <?php echo $mensaje; ?>
            </div>
            <?php if($tipo_mensaje === 'success'): ?>
                <div class="d-grid mt-3">
                    <a href="/login" class="btn btn-primary fw-bold">Ir a Iniciar Sesión</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if($token_valido): ?>
            <form method="POST" action="">
                <div class="mb-3 text-start">
                    <label class="form-label fw-bold small">Nueva Contraseña</label>
                    <input type="password" name="password" class="form-control bg-light" placeholder="Mínimo 6 caracteres" required>
                </div>
                <div class="mb-4 text-start">
                    <label class="form-label fw-bold small">Confirmar Contraseña</label>
                    <input type="password" name="confirm_password" class="form-control bg-light" placeholder="Repite la contraseña" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-success fw-bold py-2 shadow-sm">Guardar Contraseña</button>
                </div>
            </form>
        <?php endif; ?>
        
        <?php if(!$token_valido && $tipo_mensaje === 'danger'): ?>
            <div class="text-center mt-3">
                <a href="/recuperar_password" class="btn btn-outline-secondary btn-sm">Solicitar nuevo enlace</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>