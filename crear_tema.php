<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$nombreUser = $_SESSION['usuario'];

// --- 1. SEGURIDAD: COMPROBAR SUSPENSIÓN ---
$resUser = $conn->query("SELECT id, fecha_desbloqueo FROM usuarios WHERE nombre = '$nombreUser'");
$userData = $resUser->fetch_assoc();
$userId = $userData['id'];

if (!empty($userData['fecha_desbloqueo']) && strtotime($userData['fecha_desbloqueo']) > time()) {
    // Si está bloqueado, lo expulsamos inmediatamente de vuelta al foro
    header("Location: foro.php?error=cuenta_suspendida");
    exit();
}
// ------------------------------------------


// 2. PROCESAR CREACIÓN DE TEMA
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = trim($_POST['titulo']);
    $categoria = $_POST['categoria'];
    $contenido = trim($_POST['contenido']);
    
    if (!empty($titulo) && !empty($contenido)) {

        $stmt = $conn->prepare("INSERT INTO foro_temas (usuario_id, titulo, contenido, categoria) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $titulo, $contenido, $categoria);
        
        if ($stmt->execute()) {
            header("Location: foro.php");
            exit();
        } else {
            $error = "Error al crear el tema.";
        }
    } else {
        $error = "Rellena todos los campos.";
    }
}
?>
<?php include 'includes/header.php'; ?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <div class="mb-3">
                <a href="foro.php" class="text-decoration-none text-muted fw-bold">
                    <i class="fas fa-arrow-left me-1"></i> Volver al Foro
                </a>
            </div>

            <div class="card shadow-sm border-primary border-top-4">
                <div class="card-body p-4 p-md-5">
                    <h3 class="mb-4 fw-bold"><i class="fas fa-comment-medical text-primary me-2"></i>Nuevo Tema de Discusión</h3>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="row mb-4">
                            <div class="col-md-8 mb-3 mb-md-0">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Título del Debate</label>
                                <input type="text" name="titulo" class="form-control bg-light" placeholder="Ej: Teoría sobre el final..." maxlength="150" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Categoría</label>
                                <select name="categoria" class="form-select bg-light">
                                    <option value="General">General</option>
                                    <option value="Teorías">Teorías</option>
                                    <option value="Noticias">Noticias</option>
                                    <option value="Recomendaciones">Recomendaciones</option>
                                    <option value="Off-Topic">Off-Topic</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Mensaje</label>
                            <textarea name="contenido" class="form-control bg-light" rows="8" placeholder="Escribe aquí tu teoría, duda o recomendación de forma detallada..." required></textarea>
                            <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i> Sé respetuoso con el resto de la comunidad.</div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2 border-top pt-4">
                            <a href="foro.php" class="btn btn-light fw-bold px-4">Cancelar</a>
                            <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">
                                <i class="fas fa-paper-plane me-2"></i>Publicar Tema
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>