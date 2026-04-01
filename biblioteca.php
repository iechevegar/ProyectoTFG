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

// Obras que están en favoritos
$sql = "SELECT o.* FROM obras o 
        JOIN favoritos f ON o.id = f.obra_id 
        WHERE f.usuario_id = $userId 
        ORDER BY f.fecha_agregado DESC";
$resultado = $conn->query($sql);
?>
<?php include 'includes/header.php'; ?>

<main class="container py-5" style="background-color: #ffffff;">
    <div class="d-flex align-items-center mb-4 border-bottom pb-3">
        <i class="fas fa-bookmark fa-2x text-primary me-3"></i>
        <h1 class="mb-0 fw-bold">Mi Biblioteca</h1>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-5">
        <?php if ($resultado->num_rows > 0): ?>
            <?php while($obra = $resultado->fetch_assoc()): ?>
                <?php 
                    $idObra = $obra['id'];
                    
                    // 1. Contar TOTAL de capítulos de la obra
                    $resTotal = $conn->query("SELECT COUNT(*) as total FROM capitulos WHERE obra_id = $idObra");
                    $totalCaps = $resTotal->fetch_assoc()['total'];
                    
                    // 2. LÓGICA INTELIGENTE DE PROGRESO
                    // Buscamos el ID del capítulo más avanzado que ha leído el usuario
                    $sqlUltimo = "SELECT MAX(c.id) as max_id 
                                  FROM capitulos_leidos cl 
                                  JOIN capitulos c ON cl.capitulo_id = c.id 
                                  WHERE c.obra_id = $idObra AND cl.usuario_id = $userId";
                    $resUltimo = $conn->query($sqlUltimo);
                    $rowUltimo = $resUltimo->fetch_assoc();
                    
                    $progreso_real = 0; // Por defecto
                    
                    // Si ha leído algún capítulo, calculamos en qué posición está ese capítulo
                    if ($rowUltimo && $rowUltimo['max_id']) {
                        $maxCapId = $rowUltimo['max_id'];
                        // Contamos cuántos capítulos hay desde el principio hasta este último que ha leído
                        $sqlPos = "SELECT COUNT(*) as posicion FROM capitulos WHERE obra_id = $idObra AND id <= $maxCapId";
                        $progreso_real = $conn->query($sqlPos)->fetch_assoc()['posicion'];
                    }

                    // Calcular porcentaje para la barra
                    $porcentaje = ($totalCaps > 0) ? round(($progreso_real / $totalCaps) * 100) : 0;
                ?>
                
                <div class="col">
                    <a href="detalle.php?id=<?php echo $obra['id']; ?>" class="text-decoration-none text-dark">
                        <div class="card h-100 shadow-sm border hover-effect overflow-hidden bg-white">
                            <div class="position-relative" style="padding-top: 145%;">
                                <img src="<?php echo $obra['portada']; ?>" class="position-absolute top-0 start-0 w-100 h-100 zoom-img border-bottom" style="object-fit: cover;" alt="Portada">
                            </div>
                            
                            <div class="card-body p-3">
                                <h6 class="card-title fw-bold text-truncate mb-2 text-dark"><?php echo $obra['titulo']; ?></h6>
                                
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted" style="font-size: 0.75rem;">Progreso</small>
                                    <small class="fw-bold text-primary" style="font-size: 0.75rem;"><?php echo $progreso_real; ?> / <?php echo $totalCaps; ?></small>
                                </div>
                                <div class="progress border" style="height: 6px; background-color: #f1f1f1;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $porcentaje; ?>%;" aria-valuenow="<?php echo $porcentaje; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 rounded-3 border bg-light">
                <i class="far fa-folder-open fa-3x mb-3 text-muted opacity-50"></i>
                <h4 class="text-secondary fw-bold">Tu biblioteca está vacía</h4>
                <p class="text-muted">Guarda tus obras favoritas para no perder el progreso.</p>
                <a href="index.php" class="btn btn-primary mt-2 fw-bold">Explorar Catálogo</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
    .hover-effect { transition: transform 0.2s ease, box-shadow 0.2s ease; border-radius: 8px; }
    .hover-effect:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08)!important; }
    .zoom-img { transition: transform 0.4s ease; }
    .hover-effect:hover .zoom-img { transform: scale(1.03); }
</style>

<?php include 'includes/footer.php'; ?>