<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['usuario']) || !isset($_GET['id'])) {
    header("Location: foro.php");
    exit();
}

$idTema = intval($_GET['id']);
$nombreUser = $_SESSION['usuario'];

// 1. OBTENER DATOS Y VERIFICAR DUEÑO
// Hacemos JOIN para verificar que el usuario actual es el dueño
$sql = "SELECT t.* FROM foro_temas t 
        JOIN usuarios u ON t.usuario_id = u.id 
        WHERE t.id = $idTema AND u.nombre = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nombreUser);
$stmt->execute();
$tema = $stmt->get_result()->fetch_assoc();

if (!$tema) {
    die("No tienes permiso para editar este tema o no existe.");
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
?>
<?php include 'includes/header.php'; ?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h3 class="mb-4">Editar Tema</h3>
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Título</label>
                            <input type="text" name="titulo" class="form-control" value="<?php echo htmlspecialchars($tema['titulo']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Categoría</label>
                            <select name="categoria" class="form-select">
                                <?php 
                                $cats = ['General', 'Teorías', 'Noticias', 'Recomendaciones', 'Off-Topic'];
                                foreach($cats as $c) {
                                    $selected = ($c == $tema['categoria']) ? 'selected' : '';
                                    echo "<option value='$c' $selected>$c</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Contenido</label>
                            <textarea name="contenido" class="form-control" rows="6" required><?php echo htmlspecialchars($tema['contenido']); ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="tema.php?id=<?php echo $idTema; ?>" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>