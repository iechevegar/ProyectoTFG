<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = trim($_POST['titulo']);
    $categoria = $_POST['categoria'];
    $contenido = trim($_POST['contenido']);
    
    if (!empty($titulo) && !empty($contenido)) {
        $nombreUser = $_SESSION['usuario'];
        $resUser = $conn->query("SELECT id FROM usuarios WHERE nombre = '$nombreUser'");
        $userId = $resUser->fetch_assoc()['id'];

        // Insertamos también la categoría
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
            <h2 class="mb-4">Nuevo Tema de Discusión</h2>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Título</label>
                                <input type="text" name="titulo" class="form-control" placeholder="Ej: Teoría sobre el final..." required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Categoría</label>
                                <select name="categoria" class="form-select">
                                    <option value="General">General</option>
                                    <option value="Teorías">Teorías</option>
                                    <option value="Noticias">Noticias</option>
                                    <option value="Recomendaciones">Recomendaciones</option>
                                    <option value="Off-Topic">Off-Topic</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Mensaje</label>
                            <textarea name="contenido" class="form-control" rows="6" placeholder="Escribe aquí tu mensaje..." required></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="foro.php" class="btn btn-outline-secondary px-4">Cancelar</a>
                            <button type="submit" class="btn btn-primary px-4">Publicar Tema</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>