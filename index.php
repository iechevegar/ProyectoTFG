<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. VIEW HELPERS (UTILIDADES DE PRESENTACIÓN)
// =========================================================================================
// Transformador de timestamps a formato relativo (Time-Ago).
// Mejora la UX al presentar el tiempo transcurrido en un formato humano ("Hace 5 min") 
// en lugar de fechas absolutas y frías ("23/04/2026 14:00").
function tiempo_transcurrido($fecha)
{
    $timestamp = strtotime($fecha);
    $diferencia = time() - $timestamp;

    if ($diferencia < 60) return "Hace instantes";
    if ($diferencia < 3600) return "Hace " . floor($diferencia / 60) . " min";
    if ($diferencia < 86400) return "Hace " . floor($diferencia / 3600) . " h";
    if ($diferencia < 604800) return "Hace " . floor($diferencia / 86400) . " días";
    return date("d/m/Y", $timestamp);
}

// Diccionarios visuales (Mapeo de UI). Centralizamos los códigos hexadecimales corporativos 
// según taxonomía para garantizar la consistencia de diseño en toda la landing page.
function obtenerColorTipo($tipo)
{
    $tipo = strtoupper(trim($tipo));
    switch ($tipo) {
        case 'MANHWA': return '#1a9341'; 
        case 'MANGA': return '#215bc2'; 
        case 'NOVELA': return '#b71b29'; 
        case 'DONGHUA': return '#17a2b8';
        case 'MANHUA': return '#6f42c1';
        default: return '#6c757d';
    }
}

function obtenerColorDemografia($demo)
{
    $demo = strtoupper(trim($demo));
    switch ($demo) {
        case 'SEINEN': return '#bd1e2c'; 
        case 'SHOUNEN': return '#d39200'; 
        case 'SHOUJO': return '#b12f9d'; 
        case 'JOSEI': return '#6610f2';
        case 'KODOMO': return '#20c997';
        default: return '#343a40';
    }
}

// =========================================================================================
// 2. QUERY BUILDER: MOTOR DE BÚSQUEDA Y FILTRADO MULTI-CRITERIO (ANTI-SQLi)
// =========================================================================================
// Usamos prepared statements con parámetros enlazados en lugar de real_escape_string,
// que es susceptible a fallos de codificación y no es suficiente como única defensa.
$busqueda     = isset($_GET['q'])      ? trim($_GET['q'])      : '';
$filtro_genero= isset($_GET['genero']) ? trim($_GET['genero']) : '';
$filtro_autor = isset($_GET['autor'])  ? trim($_GET['autor'])  : '';

$resultados_por_pagina = 12;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $resultados_por_pagina;

// Construimos la cláusula WHERE y los parámetros dinámicamente.
// El array $params pasa referencias para call_user_func_array con bind_param.
$where  = "WHERE 1=1";
$tipos  = "";
$params = [];

if (!empty($busqueda)) {
    // Búsqueda Full-Text: coincidencias en título, autor y géneros relacionados.
    $like = "%$busqueda%";
    $where .= " AND (o.titulo LIKE ? OR o.autor LIKE ?
                OR EXISTS (SELECT 1 FROM obra_genero og JOIN generos g ON og.genero_id = g.id WHERE og.obra_id = o.id AND g.nombre LIKE ?))";
    $tipos   .= "sss";
    $params[] = &$like; $params[] = &$like; $params[] = &$like;
}
if (!empty($filtro_genero)) {
    $where .= " AND EXISTS (SELECT 1 FROM obra_genero og JOIN generos g ON og.genero_id = g.id WHERE og.obra_id = o.id AND g.nombre = ?)";
    $tipos   .= "s";
    $params[] = &$filtro_genero;
}
if (!empty($filtro_autor)) {
    $where .= " AND o.autor = ?";
    $tipos   .= "s";
    $params[] = &$filtro_autor;
}

// Consulta de conteo para el paginador (mismos filtros, sin LIMIT).
$stmtTotal = $conn->prepare("SELECT COUNT(o.id) as total FROM obras o $where");
if (!empty($tipos)) {
    $ba = array_merge([$tipos], $params);
    call_user_func_array([$stmtTotal, 'bind_param'], $ba);
}
$stmtTotal->execute();
$total_registros = $stmtTotal->get_result()->fetch_assoc()['total'];
$total_paginas   = ceil($total_registros / $resultados_por_pagina);

// =========================================================================================
// 3. OBTENCIÓN DEL CATÁLOGO PRINCIPAL (EVITANDO N+1 PROBLEM)
// =========================================================================================
// Integramos valoraciones y géneros en la misma consulta SQL con subconsultas correlacionadas
// (AVG, GROUP_CONCAT) para evitar múltiples viajes a la base de datos por obra.
$sql = "SELECT o.*,
        (SELECT AVG(puntuacion) FROM resenas WHERE obra_id = o.id) as nota_media,
        (SELECT GROUP_CONCAT(g.nombre SEPARATOR ', ') FROM obra_genero og JOIN generos g ON og.genero_id = g.id WHERE og.obra_id = o.id) as generos
        FROM obras o
        $where
        ORDER BY o.id DESC
        LIMIT ? OFFSET ?";

// Añadimos LIMIT y OFFSET como parámetros enlazados para cerrar completamente la superficie SQLi.
$tipos_main  = $tipos . "ii";
$params_main = $params;
$params_main[] = &$resultados_por_pagina;
$params_main[] = &$offset;

$stmtMain = $conn->prepare($sql);
$ba_main = array_merge([$tipos_main], $params_main);
call_user_func_array([$stmtMain, 'bind_param'], $ba_main);
$stmtMain->execute();
$resultado = $stmtMain->get_result();


// =========================================================================================
// 4. OPTIMIZACIÓN DE RENDIMIENTO: CARGA CONDICIONAL DE MÓDULOS PESADOS
// =========================================================================================
$obras_destacadas = [];
$resNuevos = null;

// Condición de ejecución: Solo lanzamos las costosas consultas del Carrusel Hero y 
// el scroll de Novedades si estamos estrictamente en la raíz de la página 1 (sin búsquedas activas).
// Esto ahora recursos masivos del servidor cuando los usuarios simplemente navegan por el catálogo.
if (empty($busqueda) && empty($filtro_genero) && empty($filtro_autor) && $pagina_actual === 1) {
    
    // Módulo Hero (Carrusel): Extraemos 3 obras aleatorias para dinamizar el landing
    $resDest = $conn->query("SELECT o.*, 
                             (SELECT AVG(puntuacion) FROM resenas WHERE obra_id = o.id) as nota_media,
                             (SELECT GROUP_CONCAT(g.nombre SEPARATOR ', ') FROM obra_genero og JOIN generos g ON og.genero_id = g.id WHERE og.obra_id = o.id) as generos
                             FROM obras o ORDER BY RAND() LIMIT 3");
    while ($row = $resDest->fetch_assoc()) {
        $obras_destacadas[] = $row;
    }

    // Módulo Novedades: Cruce de tablas para obtener los capítulos insertados en la última semana cronológica (7 days)
    $sqlNuevos = "SELECT c.id as cap_id, c.slug as cap_slug, c.titulo as cap_titulo, c.fecha_subida, 
                         o.id as obra_id, o.slug as obra_slug, o.titulo as obra_titulo, o.portada, o.tipo_obra, o.demografia,
                         (SELECT AVG(puntuacion) FROM resenas WHERE obra_id = o.id) as nota_media
                  FROM capitulos c
                  JOIN obras o ON c.obra_id = o.id
                  WHERE c.fecha_subida >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  ORDER BY c.fecha_subida DESC LIMIT 10"; 
    $resNuevos = $conn->query($sqlNuevos);
}


// =========================================================================================
// 5. EXTRACCIÓN DE DATOS PARA WIDGETS LATERALES (SIDEBAR)
// =========================================================================================
// Ranking de Obras: Calculamos la nota media on-the-fly y filtramos con HAVING para 
// omitir obras que aún no tienen calificaciones (nota > 0).
$sqlTop = "SELECT slug, titulo, portada, (SELECT AVG(puntuacion) FROM resenas WHERE obra_id = obras.id) as nota FROM obras HAVING nota > 0 ORDER BY nota DESC LIMIT 5";
$resTop = $conn->query($sqlTop);

// Actividad del Foro
$sqlForo = "SELECT titulo, slug, fecha, (SELECT COUNT(*) FROM foro_respuestas WHERE tema_id = foro_temas.id) as num_respuestas FROM foro_temas ORDER BY fecha DESC LIMIT 4";
$resForo = $conn->query($sqlForo);

// Listados para los menús Dropdown de filtrado rápido
$sqlGeneros = "SELECT DISTINCT g.nombre FROM generos g JOIN obra_genero og ON g.id = og.genero_id ORDER BY g.nombre ASC";
$resGeneros = $conn->query($sqlGeneros);
$lista_generos = [];
while ($row = $resGeneros->fetch_assoc()) { $lista_generos[] = $row['nombre']; }

$sqlAutores = "SELECT DISTINCT autor FROM obras WHERE autor IS NOT NULL AND autor != '' ORDER BY autor ASC";
$resAutores = $conn->query($sqlAutores);

// =========================================================================================
// 6. GESTIÓN DEL ESTADO DE ENRUTAMIENTO (PRESERVATION STATE)
// =========================================================================================
// Generamos la URL base para el paginador inferior.
// Conservamos todos los parámetros de búsqueda o filtrado GET actuales, purificando únicamente
// el parámetro de página actual. Así la UX de filtrado no se rompe al navegar.
$parametros_url = $_GET;
unset($parametros_url['pagina']);
unset($parametros_url['i']); // Limpieza extra de posibles trazas
$url_base = "/?" . http_build_query($parametros_url) . (empty($parametros_url) ? "" : "&");
?>

<?php include 'includes/header.php'; ?>

<?php if (!empty($obras_destacadas)): ?>
    <div id="heroCarousel" class="carousel slide mb-5 shadow-sm" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <?php foreach ($obras_destacadas as $i => $obra): ?>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?php echo $i; ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>"></button>
            <?php endforeach; ?>
        </div>
        <div class="carousel-inner">
            <?php foreach ($obras_destacadas as $index => $obra): ?>
                <?php
                // Preparación de variables visuales y fallbacks
                $nota_carrusel = $obra['nota_media'] ? round($obra['nota_media'], 1) : '-';
                $imgPortadaDest = (strpos($obra['portada'], 'http') === 0) ? $obra['portada'] : '/' . ltrim($obra['portada'], '/');
                $tipoObra = !empty($obra['tipo_obra']) ? strtoupper($obra['tipo_obra']) : 'DESCONOCIDO';
                $colorTipo = obtenerColorTipo($tipoObra);
                $demoObra = !empty($obra['demografia']) ? strtoupper($obra['demografia']) : 'DESCONOCIDO';
                $colorDemo = obtenerColorDemografia($demoObra);
                ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?> hero-item">
                    <div class="hero-bg-blur" style="background-image: url('<?php echo htmlspecialchars($imgPortadaDest); ?>');"></div>
                    <div class="container position-relative h-100 d-flex align-items-center justify-content-center">
                        <div class="row w-100 align-items-center">
                            <div class="col-md-4 text-center d-none d-md-block position-relative">
                                <img src="<?php echo htmlspecialchars($imgPortadaDest); ?>" class="rounded-4 shadow-lg position-relative z-2 hero-img-destacada" alt="Portada">
                            </div>
                            <div class="col-md-8 text-white p-4">
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge bg-warning text-dark text-uppercase fw-bold"><i class="fas fa-fire me-1"></i> Destacado</span>
                                    <?php if ($tipoObra !== 'DESCONOCIDO'): ?>
                                        <span class="badge text-uppercase fw-bold" style="background-color: <?php echo $colorTipo; ?>;"><?php echo htmlspecialchars($tipoObra); ?></span>
                                    <?php endif; ?>
                                    <?php if ($demoObra !== 'DESCONOCIDO'): ?>
                                        <span class="badge text-uppercase fw-bold" style="background-color: <?php echo $colorDemo; ?>;"><?php echo htmlspecialchars($demoObra); ?></span>
                                    <?php endif; ?>
                                </div>
                                <h1 class="display-4 fw-bold mb-2 text-shadow"><?php echo htmlspecialchars($obra['titulo']); ?></h1>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="text-warning fs-5 me-2"><i class="fas fa-star"></i></span>
                                    <span class="fw-bold fs-5 text-white"><?php echo $nota_carrusel; ?> <small class="text-white-50 fs-6 fw-normal">/ 5</small></span>
                                    <span class="ms-3 text-light opacity-75"><i class="fas fa-pen-nib me-1"></i> <?php echo htmlspecialchars($obra['autor']); ?></span>
                                </div>
                                <div class="mb-4 d-flex gap-2 flex-wrap">
                                    <?php
                                    if (!empty($obra['generos'])) {
                                        $generos_arr = explode(',', $obra['generos']);
                                        foreach (array_slice($generos_arr, 0, 4) as $g) { 
                                            echo '<span class="badge border border-light text-light rounded-pill px-3 py-2 bg-dark bg-opacity-25">' . htmlspecialchars(trim($g)) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                                <a href="/obra/<?php echo urlencode($obra['slug']); ?>" class="btn btn-iori btn-lg rounded-pill px-5 shadow-sm fw-bold border-0">
                                    <i class="fas fa-book-reader me-2"></i>Leer Ahora
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
    </div>
<?php endif; ?>

<main class="container py-4">

    <?php if ($resNuevos && $resNuevos->num_rows > 0): ?>
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-end mb-3 border-bottom border-light pb-2">
                <h3 class="fw-bold mb-0 text-dark"><i class="fas fa-bolt text-warning me-2"></i> Actualizaciones Recientes</h3>
            </div>

            <div class="d-flex gap-3 overflow-auto pt-2 pb-4 px-2 scroll-novedades">
                <?php while ($cap = $resNuevos->fetch_assoc()): ?>
                    <?php
                    $nota_nov = $cap['nota_media'] ? round($cap['nota_media'], 1) : '-';
                    $imgPortadaNov = (strpos($cap['portada'], 'http') === 0) ? $cap['portada'] : '/' . ltrim($cap['portada'], '/');
                    $tipoObraNov = !empty($cap['tipo_obra']) ? strtoupper($cap['tipo_obra']) : 'DESCONOCIDO';
                    $colorTipoNov = obtenerColorTipo($tipoObraNov);
                    ?>

                    <div class="card-obra shadow-sm border-0 hover-effect bg-white d-flex flex-column flex-shrink-0 rounded-4 overflow-hidden" style="width: 200px;">
                        <a href="/obra/<?php echo urlencode($cap['obra_slug']); ?>/<?php echo urlencode($cap['cap_slug']); ?>" class="text-decoration-none text-dark d-flex flex-column h-100">
                            <div class="portada-wrapper">
                                <div class="position-absolute w-100 text-center fw-bold text-white py-1 z-2" style="top: 0; left: 0; background-color: <?php echo $colorTipoNov; ?>; font-size: 0.75rem; letter-spacing: 1px;">
                                    <?php echo htmlspecialchars($tipoObraNov); ?>
                                </div>
                                <span class="position-absolute start-0 badge bg-dark bg-opacity-75 m-2 shadow-sm d-flex align-items-center z-3 rounded-pill px-2" style="top: 25px; font-size: 0.75rem;">
                                    <i class="fas fa-star text-warning me-1"></i> <?php echo $nota_nov; ?>
                                </span>
                                <img src="<?php echo htmlspecialchars($imgPortadaNov); ?>" class="position-absolute top-0 start-0 w-100 h-100 zoom-img" style="object-fit: cover; filter: brightness(0.95);" alt="Portada">
                            </div>

                            <div class="card-body p-3 d-flex flex-column justify-content-between h-100 bg-white">
                                <div>
                                    <h6 class="card-title fw-bold text-truncate mb-2 text-dark" title="<?php echo htmlspecialchars($cap['obra_titulo']); ?>" style="font-size: 0.95rem;">
                                        <?php echo htmlspecialchars($cap['obra_titulo']); ?>
                                    </h6>
                                    <p class="text-iori small mb-2 text-truncate fw-bold bg-iori bg-opacity-10 d-inline-block px-2 py-1 rounded-2" style="max-width: 100%;">
                                        <i class="far fa-file-alt me-1 opacity-75"></i> <?php echo htmlspecialchars($cap['cap_titulo']); ?>
                                    </p>
                                </div>
                                <small class="text-muted d-block mt-auto fw-semibold" style="font-size: 0.75rem;">
                                    <i class="far fa-clock me-1 text-iori"></i><?php echo tiempo_transcurrido($cap['fecha_subida']); ?>
                                </small>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        
        <div class="col-lg-9">
            
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 border-bottom border-light pb-3 gap-3">
                <h3 class="fw-bold text-dark m-0">
                    <?php
                    // Feedback visual Reactivo del filtro actual para el usuario
                    if (!empty($busqueda)) echo 'Búsqueda: <span class="text-iori">"' . htmlspecialchars($busqueda) . '"</span>';
                    elseif (!empty($filtro_genero)) echo 'Género: <span class="text-iori">' . htmlspecialchars($filtro_genero) . '</span>';
                    elseif (!empty($filtro_autor)) echo 'Autor: <span class="text-iori">' . htmlspecialchars($filtro_autor) . '</span>';
                    else echo '<i class="fas fa-layer-group text-iori me-2"></i>Catálogo Completo';
                    ?>
                </h3>

                <div class="d-flex gap-2">
                    <div class="dropdown">
                        <button class="btn bg-light text-dark border shadow-sm rounded-pill btn-sm fw-bold px-3 dropdown-toggle hover-bg-light" type="button" style="cursor: pointer;" data-bs-toggle="dropdown">
                            <i class="fas fa-user-edit text-iori me-1"></i> Autores
                        </button>
                        <ul class="dropdown-menu shadow-sm border-0 rounded-4" style="max-height: 300px; overflow-y: auto;">
                            <li><a class="dropdown-item fw-bold text-iori" href="/">Ver Todos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php while ($rowA = $resAutores->fetch_assoc()): ?>
                                <li><a class="dropdown-item" href="/?autor=<?php echo urlencode($rowA['autor']); ?>"><?php echo htmlspecialchars($rowA['autor']); ?></a></li>
                            <?php endwhile; ?>
                        </ul>
                    </div>

                    <div class="dropdown">
                        <button class="btn bg-light text-dark border shadow-sm rounded-pill btn-sm fw-bold px-3 dropdown-toggle hover-bg-light" type="button" style="cursor: pointer;" data-bs-toggle="dropdown">
                            <i class="fas fa-filter text-iori me-1"></i> Géneros
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4" style="max-height: 300px; overflow-y: auto;">
                            <li><a class="dropdown-item fw-bold text-iori" href="/">Ver Todos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php foreach ($lista_generos as $generoBD): ?>
                                <li><a class="dropdown-item" href="/?genero=<?php echo urlencode($generoBD); ?>"><?php echo htmlspecialchars($generoBD); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if ($resultado->num_rows > 0): ?>
                <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-4 mb-5">
                    <?php while ($obra = $resultado->fetch_assoc()): ?>
                        <?php
                        $nota_cat = $obra['nota_media'] ? round($obra['nota_media'], 1) : '-';
                        $imgPortadaCat = (strpos($obra['portada'], 'http') === 0) ? $obra['portada'] : '/' . ltrim($obra['portada'], '/');
                        $tipoObraCat = !empty($obra['tipo_obra']) ? strtoupper($obra['tipo_obra']) : 'DESCONOCIDO';
                        $colorTipoCat = obtenerColorTipo($tipoObraCat);
                        $demoObraCat = !empty($obra['demografia']) ? strtoupper($obra['demografia']) : 'DESCONOCIDO';
                        $colorDemoCat = obtenerColorDemografia($demoObraCat);
                        ?>
                        <div class="col">
                            <a href="/obra/<?php echo urlencode($obra['slug']); ?>" class="text-decoration-none text-dark h-100 d-block">
                                <div class="card-obra h-100 shadow-sm border-0 hover-effect bg-white d-flex flex-column rounded-4 overflow-hidden">
                                    <div class="portada-wrapper">
                                        <div class="position-absolute w-100 text-center fw-bold text-white py-1 z-2" style="top: 0; left: 0; background-color: <?php echo $colorTipoCat; ?>; font-size: 0.75rem; letter-spacing: 1px;">
                                            <?php echo htmlspecialchars($tipoObraCat); ?>
                                        </div>
                                        <img src="<?php echo htmlspecialchars($imgPortadaCat); ?>" class="position-absolute top-0 start-0 w-100 h-100 zoom-img" style="object-fit: cover;" alt="Portada">
                                        <?php if ($demoObraCat !== 'DESCONOCIDO'): ?>
                                            <div class="position-absolute w-100 text-center fw-bold text-white py-1 z-2" style="bottom: 0; left: 0; background-color: <?php echo $colorDemoCat; ?>; font-size: 0.70rem; letter-spacing: 1px;">
                                                <?php echo htmlspecialchars($demoObraCat); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <h6 class="card-title fw-bold text-truncate mb-0 pe-1 text-dark" title="<?php echo htmlspecialchars($obra['titulo']); ?>" style="max-width: 75%;">
                                                    <?php echo htmlspecialchars($obra['titulo']); ?>
                                                </h6>
                                                <div class="bg-light border rounded px-1 d-flex align-items-center flex-shrink-0 text-dark" style="font-size: 0.75rem;">
                                                    <i class="fas fa-star text-warning me-1" style="font-size: 0.65rem;"></i><span class="fw-bold"><?php echo $nota_cat; ?></span>
                                                </div>
                                            </div>
                                            <small class="text-muted d-block text-truncate mb-2" style="font-size:0.8rem;"><i class="fas fa-pen-nib me-1 opacity-50"></i><?php echo htmlspecialchars($obra['autor']); ?></small>
                                            
                                            <div class="d-flex flex-wrap gap-1" style="max-height: 22px; overflow: hidden;">
                                                <?php
                                                if (!empty($obra['generos'])) {
                                                    $generos_arr = explode(',', $obra['generos']);
                                                    foreach ($generos_arr as $g) {
                                                        echo '<span class="badge bg-light text-secondary border rounded-pill" style="font-size: 0.60rem; padding: 0.2rem 0.4rem;">' . htmlspecialchars(trim($g)) . '</span>';
                                                    }
                                                }
                                                ?>
                                            </div>
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
                                <a class="page-link shadow-sm border-0 fw-bold <?php echo ($pagina_actual <= 1) ? 'text-muted' : 'text-iori'; ?>" href="<?php echo $url_base . 'pagina=' . ($pagina_actual - 1); ?>" style="border-radius: 50px 0 0 50px; padding: 10px 20px;">
                                    <i class="fas fa-chevron-left me-1"></i> Anterior
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <li class="page-item <?php echo ($pagina_actual == $i) ? 'active' : ''; ?>">
                                    <a class="page-link shadow-sm border-0 fw-bold <?php echo ($pagina_actual == $i) ? 'bg-iori text-white' : 'text-dark'; ?>" href="<?php echo $url_base . 'pagina=' . $i; ?>" style="padding: 10px 16px;">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                                <a class="page-link shadow-sm border-0 fw-bold <?php echo ($pagina_actual >= $total_paginas) ? 'text-muted' : 'text-iori'; ?>" href="<?php echo $url_base . 'pagina=' . ($pagina_actual + 1); ?>" style="border-radius: 0 50px 50px 0; padding: 10px 20px;">
                                    Siguiente <i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-4 border-0 shadow-sm mb-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-search fa-2x text-muted opacity-50"></i>
                    </div>
                    <h3 class="fw-bold text-dark">No encontramos obras.</h3>
                    <p class="text-muted">Prueba a buscar con otro nombre o revisa los filtros.</p>
                    <a href="/" class="btn btn-iori mt-2 fw-bold rounded-pill px-4 shadow-sm">Ver todo el catálogo</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-3 d-none d-lg-block">
            
            <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2">
                    <h6 class="fw-bold text-dark text-uppercase mb-0"><i class="fas fa-trophy text-warning me-2"></i> Top Valoradas</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush rounded-bottom-4">
                        <?php 
                        $rank = 1;
                        if($resTop && $resTop->num_rows > 0):
                            while($top = $resTop->fetch_assoc()): 
                                $portadaTop = (strpos($top['portada'], 'http') === 0) ? $top['portada'] : '/' . ltrim($top['portada'], '/');
                                // Destacado visual jerárquico del top 3 de obras
                                $colorRank = $rank === 1 ? 'text-warning' : ($rank === 2 ? 'text-secondary' : ($rank === 3 ? 'text-orange' : 'text-muted'));
                        ?>
                            <li class="list-group-item border-light py-3 px-4 hover-bg-light transition-colors">
                                <a href="/obra/<?php echo urlencode($top['slug']); ?>" class="text-decoration-none d-flex align-items-center gap-3">
                                    <span class="fw-bold fs-5 <?php echo $colorRank; ?>" style="min-width: 20px;">#<?php echo $rank; ?></span>
                                    <img src="<?php echo htmlspecialchars($portadaTop); ?>" class="rounded shadow-sm" width="45" height="65" style="object-fit: cover;">
                                    <div class="overflow-hidden">
                                        <h6 class="fw-bold text-dark text-truncate mb-1 hover-text-iori" style="font-size:0.9rem;">
                                            <?php echo htmlspecialchars($top['titulo']); ?>
                                        </h6>
                                        <span class="badge bg-light text-dark border"><i class="fas fa-star text-warning me-1"></i><?php echo round($top['nota'], 1); ?></span>
                                    </div>
                                </a>
                            </li>
                        <?php $rank++; endwhile; else: ?>
                            <li class="list-group-item text-center py-4 text-muted small">No hay suficientes valoraciones ponderadas.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 bg-white">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2">
                    <h6 class="fw-bold text-dark text-uppercase mb-0"><i class="fas fa-comments text-iori me-2"></i> Último en el Foro</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if($resForo && $resForo->num_rows > 0): while($foro = $resForo->fetch_assoc()): ?>
                            <li class="list-group-item border-light py-3 px-4 hover-bg-light transition-colors">
                                <a href="/foro/<?php echo urlencode($foro['slug']); ?>" class="text-decoration-none d-block">
                                    <h6 class="fw-bold text-dark text-truncate mb-2 hover-text-iori" style="font-size:0.85rem;">
                                        <?php echo htmlspecialchars($foro['titulo']); ?>
                                    </h6>
                                    <div class="d-flex justify-content-between align-items-center text-muted" style="font-size:0.75rem;">
                                        <span><i class="far fa-clock me-1"></i><?php echo tiempo_transcurrido($foro['fecha']); ?></span>
                                        
                                        <span class="badge bg-light text-secondary border rounded-pill" title="Métricas de interacción">
                                            <i class="fas fa-reply me-1 opacity-75"></i><?php echo $foro['num_respuestas']; ?>
                                        </span>
                                    </div>
                                </a>
                            </li>
                        <?php endwhile; else: ?>
                            <li class="list-group-item text-center py-4 text-muted small">Tablón de debate vacío.</li>
                        <?php endif; ?>
                    </ul>
                    <div class="p-3 text-center border-top border-light bg-light rounded-bottom-4">
                        <a href="/foro" class="btn btn-sm btn-outline-iori fw-bold text-uppercase rounded-pill px-4 shadow-sm w-100">
                            Ir al Panel de Comunidad <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<script>
// Inicialización programática de Dropdowns (Bootstrap Core Requisite)
document.addEventListener("DOMContentLoaded", function() {
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });
});
</script>

<?php include 'includes/footer.php'; ?>