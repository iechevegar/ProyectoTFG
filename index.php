<?php
session_start();
require 'includes/db.php';

function tiempo_transcurrido($fecha)
{
    $timestamp = strtotime($fecha);
    $diferencia = time() - $timestamp;

    if ($diferencia < 60)
        return "Hace instantes";
    if ($diferencia < 3600)
        return "Hace " . floor($diferencia / 60) . " min";
    if ($diferencia < 86400)
        return "Hace " . floor($diferencia / 3600) . " h";
    if ($diferencia < 604800)
        return "Hace " . floor($diferencia / 86400) . " días";
    return date("d/m/Y", $timestamp);
}

$busqueda = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$filtro_genero = isset($_GET['genero']) ? $conn->real_escape_string($_GET['genero']) : '';
$filtro_autor = isset($_GET['autor']) ? $conn->real_escape_string($_GET['autor']) : '';

$resultados_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $resultados_por_pagina;

$condicion_sql = "WHERE 1=1";
if (!empty($busqueda)) {
    $condicion_sql .= " AND (titulo LIKE '%$busqueda%' OR autor LIKE '%$busqueda%' OR generos LIKE '%$busqueda%')";
}
if (!empty($filtro_genero)) {
    $condicion_sql .= " AND generos LIKE '%$filtro_genero%'";
}
if (!empty($filtro_autor)) {
    $condicion_sql .= " AND autor = '$filtro_autor'";
}

$sqlTotal = "SELECT COUNT(id) as total FROM obras " . $condicion_sql;
$resTotal = $conn->query($sqlTotal);
$total_registros = $resTotal->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $resultados_por_pagina);

$sql = "SELECT o.*, 
        (SELECT AVG(puntuacion) FROM resenas WHERE obra_id = o.id) as nota_media 
        FROM obras o 
        $condicion_sql 
        ORDER BY o.id DESC 
        LIMIT $resultados_por_pagina OFFSET $offset";
$resultado = $conn->query($sql);

$obras_destacadas = [];
$resNuevos = null;

if (empty($busqueda) && empty($filtro_genero) && empty($filtro_autor) && $pagina_actual === 1) {
    $resDest = $conn->query("SELECT o.*, 
                             (SELECT AVG(puntuacion) FROM resenas WHERE obra_id = o.id) as nota_media 
                             FROM obras o ORDER BY RAND() LIMIT 3");
    while ($row = $resDest->fetch_assoc()) {
        $obras_destacadas[] = $row;
    }

    $sqlNuevos = "SELECT c.id as cap_id, c.slug as cap_slug, c.titulo as cap_titulo, c.fecha_subida, 
                         o.id as obra_id, o.slug as obra_slug, o.titulo as obra_titulo, o.portada,
                         (SELECT AVG(puntuacion) FROM resenas WHERE obra_id = o.id) as nota_media
                  FROM capitulos c
                  JOIN obras o ON c.obra_id = o.id
                  WHERE c.fecha_subida >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  ORDER BY c.fecha_subida DESC LIMIT 8";
    $resNuevos = $conn->query($sqlNuevos);
}

// --- MAGIA: EXTRAER GÉNEROS DINÁMICOS ÚNICOS ---
$sqlGeneros = "SELECT generos FROM obras WHERE generos IS NOT NULL AND generos != ''";
$resGeneros = $conn->query($sqlGeneros);
$lista_generos = [];

while ($row = $resGeneros->fetch_assoc()) {
    $generosArray = explode(',', $row['generos']);
    foreach ($generosArray as $g) {
        $gLimpio = trim($g);
        if (!empty($gLimpio) && !in_array($gLimpio, $lista_generos)) {
            $lista_generos[] = $gLimpio;
        }
    }
}
sort($lista_generos);
// -----------------------------------------------

// --- MAGIA: EXTRAER AUTORES DINÁMICOS ÚNICOS ---
$sqlAutores = "SELECT DISTINCT autor FROM obras WHERE autor IS NOT NULL AND autor != '' ORDER BY autor ASC";
$resAutores = $conn->query($sqlAutores);
// -----------------------------------------------

$parametros_url = $_GET;
unset($parametros_url['pagina']);
unset($parametros_url['i']);

$url_base = "/?" . http_build_query($parametros_url) . (empty($parametros_url) ? "" : "&");
?>

<?php include 'includes/header.php'; ?>

<?php if (!empty($obras_destacadas)): ?>
    <div id="heroCarousel" class="carousel slide mb-5 shadow-sm" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <?php foreach ($obras_destacadas as $i => $obra): ?>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?php echo $i; ?>"
                    class="<?php echo $i === 0 ? 'active' : ''; ?>"></button>
            <?php endforeach; ?>
        </div>
        <div class="carousel-inner">
            <?php foreach ($obras_destacadas as $index => $obra): ?>
                <?php
                $nota_carrusel = $obra['nota_media'] ? round($obra['nota_media'], 1) : '-';
                $imgPortadaDest = (strpos($obra['portada'], 'http') === 0) ? $obra['portada'] : '/' . ltrim($obra['portada'], '/');
                ?>

                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?> hero-item">
                    <div class="hero-bg-blur"
                        style="background-image: url('<?php echo htmlspecialchars($imgPortadaDest); ?>');"></div>

                    <div class="container position-relative h-100 d-flex align-items-center justify-content-center">
                        <div class="row w-100 align-items-center">
                            <div class="col-md-4 text-center d-none d-md-block">
                                <img src="<?php echo htmlspecialchars($imgPortadaDest); ?>" class="rounded shadow-lg"
                                    style="height: 350px; width: 240px; object-fit: cover; border: 3px solid rgba(255,255,255,0.2); transform: rotate(-3deg);">
                            </div>
                            <div class="col-md-8 text-white p-4">
                                <span class="badge bg-warning text-dark mb-3 text-uppercase fw-bold">Recomendado</span>
                                <h1 class="display-4 fw-bold mb-2"><?php echo htmlspecialchars($obra['titulo']); ?></h1>

                                <div class="d-flex align-items-center mb-2">
                                    <span class="text-warning fs-5 me-2"><i class="fas fa-star"></i></span>
                                    <span class="fw-bold fs-5 text-white"><?php echo $nota_carrusel; ?> <small
                                            class="text-white-50 fs-6 fw-normal">/ 5</small></span>
                                    <span class="ms-3 text-light opacity-75">por
                                        <?php echo htmlspecialchars($obra['autor']); ?></span>
                                </div>

                                <div class="mb-4">
                                    <?php
                                    if (!empty($obra['generos'])) {
                                        $generos_arr = explode(',', $obra['generos']);
                                        foreach ($generos_arr as $g) {
                                            echo '<span class="badge badge-iori me-2 mb-1 rounded-pill">' . htmlspecialchars(trim($g)) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>

                                <a href="/obra/<?php echo urlencode($obra['slug']); ?>"
                                    class="btn btn-iori btn-lg rounded-pill px-5 shadow mt-2">
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

    <?php if ($resNuevos && $resNuevos->num_rows > 0): ?>
        <div class="mb-5">
            <div class="d-flex align-items-center mb-3">
                <h3 class="fw-bold mb-0 me-2"><i class="fas fa-bolt text-iori"></i> Novedades de la Semana</h3>
                <span class="badge bg-danger">NEW</span>
            </div>

            <div class="d-flex gap-3 overflow-auto pb-3 scroll-novedades">
                <?php while ($cap = $resNuevos->fetch_assoc()): ?>
                    <?php
                    $nota_nov = $cap['nota_media'] ? round($cap['nota_media'], 1) : '-';
                    $imgPortadaNov = (strpos($cap['portada'], 'http') === 0) ? $cap['portada'] : '/' . ltrim($cap['portada'], '/');
                    ?>

                    <div class="card shadow-sm border-0 flex-shrink-0" style="width: 180px;">
                        <a href="/obra/<?php echo urlencode($cap['obra_slug']); ?>/<?php echo urlencode($cap['cap_slug']); ?>"
                            class="text-decoration-none text-dark">
                            <div class="position-relative">
                                <img src="<?php echo htmlspecialchars($imgPortadaNov); ?>" class="card-img-top"
                                    style="height: 250px; object-fit: cover; filter: brightness(0.9);" alt="Portada">
                                <span class="position-absolute top-0 end-0 badge bg-danger m-1 shadow-sm">UP</span>

                                <span
                                    class="position-absolute bottom-0 start-0 badge bg-dark bg-opacity-75 m-1 shadow-sm d-flex align-items-center">
                                    <i class="fas fa-star text-warning me-1" style="font-size: 0.65rem;"></i>
                                    <?php echo $nota_nov; ?>
                                </span>
                            </div>
                            <div class="card-body p-2">
                                <h6 class="card-title fw-bold text-truncate mb-0" style="font-size: 0.9rem;"
                                    title="<?php echo htmlspecialchars($cap['obra_titulo']); ?>">
                                    <?php echo htmlspecialchars($cap['obra_titulo']); ?>
                                </h6>
                                <p class="text-iori small mb-1 text-truncate fw-bold">
                                    <?php echo htmlspecialchars($cap['cap_titulo']); ?>
                                </p>
                                <small class="text-muted d-block" style="font-size: 0.75rem;">
                                    <i class="far fa-clock me-1"></i><?php echo tiempo_transcurrido($cap['fecha_subida']); ?>
                                </small>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 border-bottom pb-3 gap-3">
        <h2 class="fw-bold text-dark m-0">
            <?php
            if (!empty($busqueda))
                echo 'Resultados para: "' . htmlspecialchars($busqueda) . '"';
            elseif (!empty($filtro_genero))
                echo 'Género: ' . htmlspecialchars($filtro_genero);
            elseif (!empty($filtro_autor))
                echo 'Obras de: ' . htmlspecialchars($filtro_autor);
            else
                echo '<i class="fas fa-layer-group text-iori me-2"></i>Catálogo Completo';
            ?>
        </h2>

        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-iori rounded-pill btn-sm fw-bold px-3" type="button"
                    data-bs-toggle="dropdown">
                    <i class="fas fa-user-edit me-1"></i> Autores
                </button>
                <ul class="dropdown-menu shadow-sm border-0" style="max-height: 300px; overflow-y: auto;">
                    <li><a class="dropdown-item" href="/">Ver Todos</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <?php while ($rowA = $resAutores->fetch_assoc()): ?>
                        <li><a class="dropdown-item"
                                href="/?autor=<?php echo urlencode($rowA['autor']); ?>"><?php echo htmlspecialchars($rowA['autor']); ?></a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>

            <div class="dropdown">
                <button class="btn btn-outline-iori rounded-pill btn-sm fw-bold px-3" type="button"
                    data-bs-toggle="dropdown">
                    <i class="fas fa-filter me-1"></i> Géneros
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0"
                    style="max-height: 300px; overflow-y: auto;">
                    <li><a class="dropdown-item" href="/">Ver Todos</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <?php foreach ($lista_generos as $generoBD): ?>
                        <li><a class="dropdown-item"
                                href="/?genero=<?php echo urlencode($generoBD); ?>"><?php echo htmlspecialchars($generoBD); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <?php if ($resultado->num_rows > 0): ?>
        <div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-4 mb-5">
            <?php while ($obra = $resultado->fetch_assoc()): ?>
                <?php
                $nota_cat = $obra['nota_media'] ? round($obra['nota_media'], 1) : '-';
                $imgPortadaCat = (strpos($obra['portada'], 'http') === 0) ? $obra['portada'] : '/' . ltrim($obra['portada'], '/');
                ?>

                <div class="col">
                    <a href="/obra/<?php echo urlencode($obra['slug']); ?>" class="text-decoration-none text-dark">
                        <div class="card-obra h-100 shadow-sm overflow-hidden">
                            <div class="position-relative" style="padding-top: 145%;">
                                <img src="<?php echo htmlspecialchars($imgPortadaCat); ?>"
                                    class="position-absolute top-0 start-0 w-100 h-100 zoom-img"
                                    style="object-fit: cover; border-bottom: 1px solid #eee;" alt="Portada">
                            </div>

                            <div class="card-body p-2 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="card-title fw-bold text-truncate mb-0 text-dark pe-1"
                                            title="<?php echo htmlspecialchars($obra['titulo']); ?>" style="max-width: 75%;">
                                            <?php echo htmlspecialchars($obra['titulo']); ?>
                                        </h6>

                                        <div class="bg-light border rounded px-1 d-flex align-items-center flex-shrink-0 text-dark"
                                            style="font-size: 0.75rem;">
                                            <i class="fas fa-star text-warning me-1" style="font-size: 0.65rem;"></i><span
                                                class="fw-bold"><?php echo $nota_cat; ?></span>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block text-truncate mb-1">
                                        <?php echo htmlspecialchars($obra['autor']); ?>
                                    </small>
                                </div>

                                <div class="mt-1 d-flex flex-wrap gap-1">
                                    <?php
                                    if (!empty($obra['generos'])) {
                                        $generos_arr = explode(',', $obra['generos']);
                                        $generos_arr = array_slice($generos_arr, 0, 3);
                                        foreach ($generos_arr as $g) {
                                            echo '<span class="badge badge-iori me-1 mb-1" style="font-size: 0.65rem;">' . htmlspecialchars(trim($g)) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>

                            </div>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>

        <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación del catálogo" class="mt-4 mb-5">
                <ul class="pagination justify-content-center">

                    <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link shadow-sm" href="<?php echo $url_base . 'pagina=' . ($pagina_actual - 1); ?>">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    </li>

                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo ($pagina_actual == $i) ? 'active' : ''; ?>">
                            <a class="page-link shadow-sm" href="<?php echo $url_base . 'pagina=' . $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                        <a class="page-link shadow-sm" href="<?php echo $url_base . 'pagina=' . ($pagina_actual + 1); ?>">
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>

                </ul>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-5 bg-light rounded-3 border">
            <i class="fas fa-search fa-3x text-muted mb-3 opacity-50"></i>
            <h3 class="fw-bold text-secondary">No encontramos obras.</h3>
            <p class="text-muted">Prueba a buscar con otro nombre o género.</p>
            <a href="/" class="btn btn-iori mt-2 fw-bold">Ver todo el catálogo</a>
        </div>
    <?php endif; ?>

</main>

<?php include 'includes/footer.php'; ?>