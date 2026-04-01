<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener ID usuario
$nombreUser = $_SESSION['usuario'];
$resUser = $conn->query("SELECT id FROM usuarios WHERE nombre = '$nombreUser'");
$userId = $resUser->fetch_assoc()['id'];

// CONSULTA CLAVE: JOIN para sacar las obras que están en favoritos
$sql = "SELECT o.* FROM obras o 
        JOIN favoritos f ON o.id = f.obra_id 
        WHERE f.usuario_id = $userId 
        ORDER BY f.fecha_agregado DESC";
$resultado = $conn->query($sql);
?>
<?php include 'includes/header.php'; ?>

<main>
    <h1 class="mb-4"><i class="fas fa-heart text-danger"></i> Mi Biblioteca</h1>

    <div class="catalog-grid">
        <?php if ($resultado->num_rows > 0): ?>
            <?php while($obra = $resultado->fetch_assoc()): ?>
                <?php 
                    // Contar capítulos (Opcional, copia rápida del index)
                    $idObra = $obra['id'];
                    $numCaps = $conn->query("SELECT COUNT(*) as total FROM capitulos WHERE obra_id = $idObra")->fetch_assoc()['total'];
                    
                    // Géneros
                    $generos = explode(',', $obra['generos']);
                    $tags = array_slice($generos, 0, 2); 
                    $tagsHTML = '';
                    foreach($tags as $g) { $tagsHTML .= '<span class="tag">'.trim($g).'</span>'; }
                ?>
                
                <a href="detalle.php?id=<?php echo $obra['id']; ?>" class="card text-decoration-none text-dark">
                    <img src="<?php echo $obra['portada']; ?>" class="card-image" alt="Portada">
                    <div class="card-content">
                        <h3 class="card-title"><?php echo $obra['titulo']; ?></h3>
                        <div class="mb-2"><?php echo $tagsHTML; ?></div>
                        <small style="color:#888;"><?php echo $numCaps; ?> Capítulos</small>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center w-100 py-5" style="grid-column: 1/-1;">
                <i class="far fa-folder-open fa-3x mb-3 text-muted"></i>
                <p class="text-muted">Aún no has guardado ninguna obra.</p>
                <a href="index.php" class="btn btn-primary">Explorar Catálogo</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>