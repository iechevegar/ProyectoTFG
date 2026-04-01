<?php
// 1. Iniciar sesión al principio absoluto
session_start();
require 'includes/db.php';

$error = '';

// Si el usuario ya está logueado, lo mandamos al index directamente
if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// 2. Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['usuario']);
    $pass = trim($_POST['password']);

    // 3. CONSULTA A LA BASE DE DATOS
    // Buscamos al usuario por nombre para obtener su hash y rol
    $sql = "SELECT nombre, password, rol, foto FROM usuarios WHERE nombre = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $fila = $resultado->fetch_assoc();
        
        // 4. VERIFICACIÓN DE CONTRASEÑA
        // password_verify: Para usuarios nuevos registrados (encriptados)
        // ===: Para tus usuarios de prueba antiguos (texto plano)
        if (password_verify($pass, $fila['password']) || $pass === $fila['password']) {
            
            // Login Correcto: Regeneramos ID de sesión por seguridad
            session_regenerate_id(true);
            
            $_SESSION['usuario'] = $fila['nombre'];
            $_SESSION['rol'] = $fila['rol'];
            // Guardamos la foto en sesión. Si es NULL, se queda vacía.
            $_SESSION['foto'] = $fila['foto'];
            
            // Redirigir al catálogo
            header("Location: index.php");
            exit();
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "Usuario no encontrado.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - LectorApp</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo time(); ?>">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">

    <div class="login-container bg-white p-4 shadow-sm" style="max-width: 400px; width: 100%; border-radius: 12px; text-align: center;">
        
        <div class="mb-4">
            <a href="index.php" class="text-decoration-none text-dark fs-2 fw-bold">
                <i class="fas fa-book-open me-2"></i>LectorApp
            </a>
            <p class="text-muted mt-2">Bienvenido de nuevo</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger py-2 text-start" style="font-size: 0.9rem;">
                <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3 text-start">
                <label class="form-label fw-bold small">Usuario</label>
                <input type="text" name="usuario" class="form-control" placeholder="Ej: Admin" required>
            </div>

            <div class="mb-4 text-start">
                <label class="form-label fw-bold small">Contraseña</label>
                <input type="password" name="password" class="form-control" placeholder="Tu contraseña" required>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-primary fw-bold">Entrar</button>
            </div>
        </form>

        <div class="mt-4 text-center small text-muted border-top pt-3">
            ¿No tienes cuenta? <a href="registro.php" class="text-primary text-decoration-none fw-bold">Regístrate gratis</a>
        </div>
        
        <div class="mt-3 text-start p-2 bg-light rounded border" style="font-size: 0.75rem; color: #666;">
            <strong>Usuarios Test:</strong><br>
            • Admin / admin<br>
            • Lector / lector
        </div>
    </div>

</body>
</html>