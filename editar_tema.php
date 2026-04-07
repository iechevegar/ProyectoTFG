<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['usuario']) || !isset($_GET['id'])) {
    header("Location: foro.php");
    exit();
}

$idTema = intval($_GET['id']);
$nombreUser = $_SESSION['usuario'];

// 1. OBTENER DATOS Y VERIFICAR DUEÑO (Protegido con bind_param)
$sql = "SELECT t.* FROM foro_temas t 
        JOIN usuarios u ON t.usuario_id = u.id 
        WHERE t.id = ? AND u.nombre = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $idTema, $nombreUser);
$stmt->execute();
$tema = $stmt->get_result()->fetch_assoc();

if (!$tema) {
    die("<div class='container py-5 text-center'><h3>No tienes permiso para editar este tema o no existe.</h3><a href='foro.php' class='btn btn-primary mt-3'>Volver al foro</a></div>");
}

// 2. PROCESAR EDICIÓN
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = trim($_POST['titulo']);
    $contenido = trim($_POST['contenido']);
    $categoria = $_POST['categoria'];

    if (!empty($titulo) && !empty($contenido)) {
        // Actualizamos datos y ponemos la fecha de edición AHORA (NOW())
        $sqlUp = "UPDATE foro_temas SET titulo=?, contenido=?, categoria=?, fecha_edicion=NOW() WHERE id=?";
        $stmtUp = $conn->prepare($sqlUp);
        $stmtUp->bind_param("sssi", $titulo, $contenido, $categoria, $idTema);
        
        if ($stmtUp->execute()) {
            header("Location: tema.php?id=$idTema"); // Volver al tema
            exit();
        }
    }
}

// SOLUCIÓN AL "HEADER MOVIDO": Incluimos el header dentro del bloque PHP principal 
// para evitar imprimir saltos de línea accidentales antes del DOCTYPE.
include 'includes/header.php'; 
?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <div class="mb-3">
                <a href="tema.php?id=<?php echo $idTema; ?>" class="text-decoration-none text-muted fw-bold">
                    <i class="fas fa-arrow-left me-1"></i> Volver al Tema
                </a>
            </div>

            <div class="card shadow-sm border-primary border-top-4">
                <div class="card-body p-4">
                    <h4 class="mb-4 fw-bold"><i class="fas fa-pencil-alt text-primary me-2"></i>Editar Tema</h4>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Título del debate</label>
                            <input type="text" name="titulo" class="form-control bg-light" value="<?php echo htmlspecialchars($tema['titulo']); ?>" required maxlength="100">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Categoría</label>
                            <select name="categoria" class="form-select bg-light">
                                <?php 
                                $cats = ['General', 'Teorías', 'Noticias', 'Recomendaciones', 'Off-Topic'];
                                foreach($cats as $c) {
                                    $selected = ($c == $tema['categoria']) ? 'selected' : '';
                                    echo "<option value='$c' $selected>$c</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Contenido</label>
                            <textarea name="contenido" class="form-control bg-light" rows="8" required><?php echo htmlspecialchars($tema['contenido']); ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2 border-top pt-3">
                            <a href="tema.php?id=<?php echo $idTema; ?>" class="btn btn-light fw-bold px-4">Cancelar</a>
                            <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">
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