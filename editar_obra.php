<?php
session_start();
require 'includes/db.php';

// 1. SEGURIDAD: Solo admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 2. OBTENER OBRA A EDITAR
if (!isset($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$id = intval($_GET['id']);
$mensaje = '';

// Buscar datos actuales
$sql = "SELECT * FROM obras WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();
$obra = $resultado->fetch_assoc();

if (!$obra) {
    die("Obra no encontrada");
}

// 3. PROCESAR ACTUALIZACIÓN
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = trim($_POST['titulo']);
    $autor = trim($_POST['autor']);
    $generos = trim($_POST['generos']);
    $sinopsis = trim($_POST['sinopsis']);
    
    // Gestión de Portada (Solo si se sube una nueva)
    $ruta_portada = $obra['portada']; // Por defecto, mantenemos la vieja
    
    if (isset($_FILES['portada']) && $_FILES['portada']['error'] === 0) {
        $nombre_archivo = time() . "_" . $_FILES['portada']['name'];
        $ruta_destino = "assets/img/portadas/" . $nombre_archivo;
        
        if (move_uploaded_file($_FILES['portada']['tmp_name'], $ruta_destino)) {
            $ruta_portada = $ruta_destino;
        }
    }

    // UPDATE SQL
    $sql_update = "UPDATE obras SET titulo=?, autor=?, generos=?, sinopsis=?, portada=? WHERE id=?";
    $stmt_up = $conn->prepare($sql_update);
    $stmt_up->bind_param("sssssi", $titulo, $autor, $generos, $sinopsis, $ruta_portada, $id);
    
    if ($stmt_up->execute()) {
        header("Location: admin.php?msg=Obra actualizada correctamente");
        exit();
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al actualizar.</div>";
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h2 class="mb-4">Editar Obra</h2>
            <?php echo $mensaje; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Título</label>
                            <input type="text" name="titulo" class="form-control" value="<?php echo htmlspecialchars($obra['titulo']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Autor</label>
                            <input type="text" name="autor" class="form-control" value="<?php echo htmlspecialchars($obra['autor']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Géneros</label>
                            <input type="text" name="generos" class="form-control" value="<?php echo htmlspecialchars($obra['generos']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Sinopsis</label>
                            <textarea name="sinopsis" class="form-control" rows="5"><?php echo htmlspecialchars($obra['sinopsis']); ?></textarea>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Portada Actual</label><br>
                                <img src="<?php echo $obra['portada']; ?>" class="img-thumbnail" width="150">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Cambiar Portada (Opcional)</label>
                                <input type="file" name="portada" class="form-control" accept="image/*">
                                <div class="form-text">Deja esto vacío si quieres mantener la portada actual.</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="admin.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-warning text-white">
                                <i class="fas fa-save me-2"></i>Guardar Cambios
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>