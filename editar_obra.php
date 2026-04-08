<?php
session_start();
require 'includes/db.php';
require 'includes/funciones.php'; // <-- IMPORTAMOS LA FUNCIÓN DE SLUGS

// 1. SEGURIDAD: Solo admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /login");
    exit();
}

// 2. OBTENER OBRA A EDITAR
if (!isset($_GET['id'])) {
    header("Location: /admin");
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
    
    // --- MAGIA: ACTUALIZAR EL SLUG ---
    $slug = limpiarURL($titulo);
    
    // Comprobamos que el nuevo slug no lo tenga YA otra obra (excluyendo esta misma)
    $check_slug = $conn->query("SELECT id FROM obras WHERE slug = '$slug' AND id != $id");
    if ($check_slug && $check_slug->num_rows > 0) {
        $slug = $slug . '-' . rand(100, 999);
    }
    // ---------------------------------
    
    // Gestión de Portada (Solo si se sube una nueva)
    $ruta_portada = $obra['portada']; // Por defecto, mantenemos la vieja
    
    if (isset($_FILES['portada']) && $_FILES['portada']['error'] === 0) {
        $nombre_archivo = time() . "_" . $_FILES['portada']['name'];
        $ruta_destino = "assets/img/portadas/" . $nombre_archivo;
        
        if (move_uploaded_file($_FILES['portada']['tmp_name'], $ruta_destino)) {
            $ruta_portada = $ruta_destino;
        }
    }

    // UPDATE SQL (Añadido el slug a la consulta)
    $sql_update = "UPDATE obras SET titulo=?, slug=?, autor=?, generos=?, sinopsis=?, portada=? WHERE id=?";
    $stmt_up = $conn->prepare($sql_update);
    $stmt_up->bind_param("ssssssi", $titulo, $slug, $autor, $generos, $sinopsis, $ruta_portada, $id);
    
    if ($stmt_up->execute()) {
        header("Location: /admin?msg=Obra actualizada correctamente");
        exit();
    } else {
        $mensaje = "<div class='alert alert-danger shadow-sm'><i class='fas fa-exclamation-triangle me-2'></i> Error al actualizar.</div>";
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="/admin" class="text-decoration-none text-muted mb-1 d-inline-block">
                        <i class="fas fa-arrow-left"></i> Volver al Panel
                    </a>
                    <h2 class="fw-bold text-dark m-0"><i class="fas fa-edit text-warning me-2"></i> Editar Obra</h2>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card shadow-sm border-0 border-top border-warning border-3">
                <div class="card-body p-4">
                    <form method="POST" action="" enctype="multipart/form-data">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Título</label>
                            <input type="text" name="titulo" class="form-control bg-light" value="<?php echo htmlspecialchars($obra['titulo']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Autor</label>
                            <input type="text" name="autor" class="form-control bg-light" value="<?php echo htmlspecialchars($obra['autor']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Géneros</label>
                            <input type="text" name="generos" class="form-control bg-light" value="<?php echo htmlspecialchars($obra['generos']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Sinopsis</label>
                            <textarea name="sinopsis" class="form-control bg-light" rows="5"><?php echo htmlspecialchars($obra['sinopsis']); ?></textarea>
                        </div>

                        <div class="row mb-4 p-3 bg-light rounded border">
                            <div class="col-md-4 text-center text-md-start mb-3 mb-md-0">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Portada Actual</label><br>
                                <?php 
                                    $imgActual = (strpos($obra['portada'], 'http') === 0) ? $obra['portada'] : '/' . ltrim($obra['portada'], '/'); 
                                ?>
                                <img src="<?php echo htmlspecialchars($imgActual); ?>" class="img-thumbnail shadow-sm" width="150">
                            </div>
                            <div class="col-md-8 d-flex flex-column justify-content-center">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Cambiar Portada (Opcional)</label>
                                <input type="file" name="portada" class="form-control" accept="image/*">
                                <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i> Deja esto vacío si quieres mantener la portada actual.</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 border-top pt-4 mt-2">
                            <a href="/admin" class="btn btn-light fw-bold px-4 border">Cancelar</a>
                            <button type="submit" class="btn btn-warning text-dark fw-bold px-4 shadow-sm">
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