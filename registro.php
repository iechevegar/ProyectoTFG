<?php
session_start();
require 'includes/db.php';

// Si ya está logueado, fuera
if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['usuario']);
    $email = trim($_POST['email']);
    $pass = trim($_POST['password']);
    $confirm_pass = trim($_POST['confirm_password']);

    // 1. VALIDACIONES BÁSICAS
    if (empty($nombre) || empty($email) || empty($pass)) {
        $error = "Por favor, rellena todos los campos.";
    } elseif ($pass !== $confirm_pass) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($pass) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        // 2. COMPROBAR SI EL USUARIO O EMAIL YA EXISTEN
        $sql = "SELECT id FROM usuarios WHERE nombre = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $nombre, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Ese nombre de usuario o email ya está registrado.";
        } else {
            // 3. ENCRIPTAR CONTRASEÑA
            $pass_hash = password_hash($pass, PASSWORD_DEFAULT);
            $rol = 'lector'; // Por defecto todos son lectores

            // 4. INSERTAR EN BD
            $sql_insert = "INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("ssss", $nombre, $email, $pass_hash, $rol);

            if ($stmt_insert->execute()) {
                // --- MAGIA UX: AUTO-LOGIN ---
                session_regenerate_id(true); // Seguridad extra
                
                $_SESSION['usuario'] = $nombre;
                $_SESSION['rol'] = $rol;
                $_SESSION['foto'] = null; // Como acaba de registrarse, aún no tiene foto
                
                // Lo enviamos directo al catálogo (index.php) ya logueado
                header("Location: index.php");
                exit();
                // ----------------------------
            } else {
                $error = "Error al registrarse. Inténtalo de nuevo.";
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
    <title>Registro - LectorApp</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo time(); ?>">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">

    <div class="login-container bg-white p-4 shadow-sm" style="max-width: 400px; width: 100%; border-radius: 12px;">
        <div class="text-center mb-4">
            <a href="index.php" class="text-decoration-none text-dark fs-2 fw-bold">
                <i class="fas fa-book-open me-2"></i>LectorApp
            </a>
            <p class="text-muted mt-2">Crea tu cuenta gratis</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-1"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3 text-start">
                <label class="form-label fw-bold small">Usuario</label>
                <input type="text" name="usuario" class="form-control" placeholder="Tu nombre de usuario" required>
            </div>

            <div class="mb-3 text-start">
                <label class="form-label fw-bold small">Email</label>
                <input type="email" name="email" class="form-control" placeholder="correo@ejemplo.com" required>
            </div>

            <div class="mb-3 text-start">
                <label class="form-label fw-bold small">Contraseña</label>
                <input type="password" name="password" class="form-control" placeholder="Mínimo 4 caracteres" required>
            </div>

            <div class="mb-4 text-start">
                <label class="form-label fw-bold small">Confirmar Contraseña</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Repite la contraseña" required>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-primary fw-bold">Registrarse y Entrar</button>
            </div>
        </form>

        <div class="mt-4 text-center small text-muted">
            ¿Ya tienes cuenta? <a href="login.php" class="text-primary text-decoration-none fw-bold">Inicia Sesión</a>
        </div>
    </div>

</body>
</html>