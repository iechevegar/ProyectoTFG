<?php
session_start();
require 'includes/db.php';

// FUNCIÓN AUXILIAR: Formato "Hace X tiempo"
function tiempo_transcurrido($fecha) {
    $timestamp = strtotime($fecha);
    $diferencia = time() - $timestamp;
    
    if ($diferencia < 60) return "Hace instantes";
    if ($diferencia < 3600) return "Hace " . floor($diferencia / 60) . " min";
    if ($diferencia < 86400) return "Hace " . floor($diferencia / 3600) . " h";
    if ($diferencia < 604800) return "Hace " . floor($diferencia / 86400) . " días";
    return date("d/m/Y", $timestamp);
}

// 1. LÓGICA DE BÚSQUEDA Y FILTROS (CATÁLOGO GENERAL)
$busqueda = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$filtro_genero = isset($_GET['genero']) ? $conn->real_escape_string($_GET['genero']) : '';

$sql = "SELECT * FROM obras WHERE 1=1";
if (!empty($busqueda)) {
    $sql .= " AND (titulo LIKE '%$busqueda%' OR autor LIKE '%$busqueda%' OR generos LIKE '%$busqueda%')";
}
if (!empty($filtro_genero)) {
    $sql .= " AND generos LIKE '%$filtro_genero%'";
}
$sql .= " ORDER BY id DESC"; 
$resultado = $conn->query($sql);

// 2. OBRAS DESTACADAS (HERO SLIDER)
$obras_destacadas = [];
if (empty($busqueda) && empty($filtro_genero)) {
    $resDest = $conn->query("SELECT * FROM obras ORDER BY RAND() LIMIT 3");
    while($row = $resDest->fetch_assoc()) {
        $obras_destacadas[] = $row;
    }
}

// 3. NUEVOS CAPÍTULOS (ÚLTIMOS 7 DÍAS) - NUEVO BLOQUE
// Hacemos JOIN para sacar la portada y título de la obra asociada al capítulo
$sqlNuevos = "SELECT c.id as cap_id, c.titulo as cap_titulo, c.fecha_subida, 
                     o.id as obra_id, o.titulo as obra_titulo, o.portada 
              FROM capitulos c
              JOIN obras o ON c.obra_id = o.id
              WHERE c.fecha_subida >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              ORDER BY c.fecha_subida DESC LIMIT 8"; // Limitamos a 8 para que no sea infinito
$resNuevos = $conn->query($sqlNuevos);

?>

<?php include 'includes/header.php'; ?>

<?php if (!empty($obras_destacadas) && empty($busqueda) && empty($filtro_genero)): ?>
<div id="heroCarousel" class="carousel slide mb-5 shadow-sm" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <?php foreach($obras_destacadas as $i => $obra): ?>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?php echo $i; ?>" class="<?php echo $i===0?'active':''; ?>"></button>
        <?php endforeach; ?>
    </div>
    <div class="carousel-inner">
        <?php foreach($obras_destacadas as $index => $obra): ?>
            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>" style="height: 450px; background-color: #111;">
                <div style="position: absolute; top:0; left:0; width:100%; height:100%; 
                            background: url('<?php echo $obra['portada']; ?>') center/cover; 
                            filter: blur(8px) brightness(0.3);">
                </div>
                <div class="container position-relative h-100 d-flex align-items-center justify-content-center">
                    <div class="row w-100 align-items-center">
                        <div class="col-md-4 text-center d-none d-md-block">
                            <img src="<?php echo $obra['portada']; ?>" class="rounded shadow-lg" 
                                 style="max-height: 350px; border: 3px solid rgba(255,255,255,0.2); transform: rotate(-3deg);">
                        </div>
                        <div class="col-md-8 text-white p-4">
                            <span class="badge bg-warning text-dark mb-3 text-uppercase fw-bold">Recomendado</span>
                            <h1 class="display-4 fw-bold mb-2"><?php echo $obra['titulo']; ?></h1>
                            <p class="h5 text-light mb-4" style="font-weight: 300;">por <?php echo $obra['autor']; ?></p>
                            <a href="detalle.php?id=<?php echo $obra['id']; ?>" class="btn btn-primary btn-lg rounded-pill px-5 shadow">
                                <i class="fas fa-book-reader me-2"></i>Leer Ahora
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>
<?php endif; ?>

<main class="container py-4">

    <?php if (empty($busqueda) && empty($filtro_genero)): ?>
        <div class="mb-5">
            <div class="d-flex align-items-center mb-3">
                <h3 class="fw-bold mb-0 me-2"><i class="fas fa-bolt text-warning"></i> Novedades de la Semana</h3>
                <span class="badge bg-danger">NEW</span>
            </div>

            <?php if ($resNuevos->num_rows > 0): ?>
                <div class="d-flex gap-3 overflow-auto pb-3" style="scrollbar-width: thin;">
                    <?php while($cap = $resNuevos->fetch_assoc()): ?>
                        <div class="card shadow-sm border-0 flex-shrink-0" style="width: 160px;">
                            <a href="visor.php?capId=<?php echo $cap['cap_id']; ?>&obraId=<?php echo $cap['obra_id']; ?>" class="text-decoration-none text-dark">
                                <div class="position-relative">
                                    <img src="<?php echo $cap['portada']; ?>" class="card-img-top" style="height: 220px; object-fit: cover; filter: brightness(0.9);" alt="Portada">
                                    <span class="position-absolute top-0 end-0 badge bg-danger m-1 shadow-sm">UP</span>
                                </div>
                                <div class="card-body p-2">
                                    <h6 class="card-title fw-bold text-truncate mb-0" style="font-size: 0.9rem;" title="<?php echo $cap['obra_titulo']; ?>">
                                        <?php echo $cap['obra_titulo']; ?>
                                    </h6>
                                    <p class="text-primary small mb-1 text-truncate fw-bold">
                                        <?php echo $cap['cap_titulo']; ?>
                                    </p>
                                    <small class="text-muted d-block" style="font-size: 0.75rem;">
                                        <i class="far fa-clock me-1"></i><?php echo tiempo_transcurrido($cap['fecha_subida']); ?>
                                    </small>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-muted small">
                    <i class="fas fa-coffee me-2"></i> No se han subido capítulos en los últimos 7 días.
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h2 class="fw-bold text-dark m-0">
            <?php 
                if(!empty($busqueda)) echo 'Resultados para: "' . htmlspecialchars($busqueda) . '"';
                elseif(!empty($filtro_genero)) echo 'Género: ' . htmlspecialchars($filtro_genero);
                else echo '<i class="fas fa-layer-group text-primary me-2"></i>Catálogo Completo';
            ?>
        </h2>
        
        <div class="dropdown">
            <button class="btn btn-outline-dark dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-filter me-1"></i> Filtrar Género
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="index.php">Ver Todo</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="index.php?genero=Acción">Acción</a></li>
                <li><a class="dropdown-item" href="index.php?genero=Aventura">Aventura</a></li>
                <li><a class="dropdown-item" href="index.php?genero=Fantasía">Fantasía</a></li>
                <li><a class="dropdown-item" href="index.php?genero=Romance">Romance</a></li>
                <li><a class="dropdown-item" href="index.php?genero=Drama">Drama</a></li>
            </ul>
        </div>
    </div>

    <?php if ($resultado->num_rows > 0): ?>
        <div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-4 mb-5">
            <?php while($obra = $resultado->fetch_assoc()): ?>
                <div class="col">
                    <a href="detalle.php?id=<?php echo $obra['id']; ?>" class="text-decoration-none text-dark">
                        <div class="card h-100 shadow-sm border-0 hover-effect overflow-hidden">
                            <div class="position-relative" style="padding-top: 145%;">
                                <img src="<?php echo $obra['portada']; ?>" 
                                     class="position-absolute top-0 start-0 w-100 h-100 zoom-img" 
                                     style="object-fit: cover;" 
                                     alt="Portada">
                                <?php $primer_genero = explode(',', $obra['generos'])[0]; ?>
                                <span class="position-absolute bottom-0 start-0 badge bg-dark m-2 bg-opacity-75">
                                    <?php echo trim($primer_genero); ?>
                                </span>
                            </div>
                            
                            <div class="card-body p-2">
                                <h6 class="card-title fw-bold text-truncate mb-1 text-dark" title="<?php echo $obra['titulo']; ?>">
                                    <?php echo $obra['titulo']; ?>
                                </h6>
                                <small class="text-muted d-block text-truncate">
                                    <?php echo $obra['autor']; ?>
                                </small>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5 bg-light rounded-3">
            <i class="fas fa-search fa-3x text-muted mb-3 opacity-50"></i>
            <h3 class="fw-bold text-secondary">No encontramos obras.</h3>
            <p class="text-muted">Prueba a buscar con otro nombre o género.</p>
            <a href="index.php" class="btn btn-primary mt-2">Ver todo el catálogo</a>
        </div>
    <?php endif; ?>

</main>

<style>
    .hover-effect { 
        transition: transform 0.2s ease, box-shadow 0.2s ease; 
        border-radius: 10px;
    }
    .hover-effect:hover { 
        transform: translateY(-5px); 
        box-shadow: 0 10px 20px rgba(0,0,0,0.15)!important; 
    }
    .zoom-img { transition: transform 0.4s ease; }
    .hover-effect:hover .zoom-img { transform: scale(1.05); }
    
    /* Scroll horizontal bonito para Chrome/Safari/Edge */
    .overflow-auto::-webkit-scrollbar { height: 6px; }
    .overflow-auto::-webkit-scrollbar-track { background: #f1f1f1; }
    .overflow-auto::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
    .overflow-auto::-webkit-scrollbar-thumb:hover { background: #bbb; }
</style>

<?php include 'includes/footer.php'; ?>