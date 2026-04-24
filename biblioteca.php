<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. MIDDLEWARE DE AUTENTICACIÓN
// =========================================================================================
// El área de "Mi Biblioteca" es un entorno privado. Si el cliente no presenta 
// un token de sesión válido, interrumpimos la ejecución y forzamos el login.
if (!isset($_SESSION['usuario'])) {
    header("Location: /login");
    exit();
}

// =========================================================================================
// 2. RESOLUCIÓN DE IDENTIDAD Y EXTRACCIÓN DE DATOS
// =========================================================================================
// Obtenemos el identificador relacional (Primary Key) del usuario activo.
$nombreUser = $_SESSION['usuario'];
$resUser = $conn->query("SELECT id FROM usuarios WHERE nombre = '$nombreUser'");
$userId = $resUser->fetch_assoc()['id'];

// Extracción del catálogo personalizado: Realizamos un JOIN entre la tabla de obras
// y la tabla pivote de favoritos para recuperar únicamente las series seguidas por este usuario,
// priorizando (ORDER BY) las agregadas más recientemente.
$sql = "SELECT o.* FROM obras o 
        JOIN favoritos f ON o.id = f.obra_id 
        WHERE f.usuario_id = $userId 
        ORDER BY f.fecha_agregado DESC";
$resultado = $conn->query($sql);
?>
<?php include 'includes/header.php'; ?>

<main class="container py-5 biblioteca-main-container">
    
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div class="d-flex align-items-center">
            <div class="bg-iori text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm biblioteca-header-icon">
                <i class="fas fa-bookmark fa-lg"></i>
            </div>
            <h1 class="mb-0 fw-bold text-dark">Mi Biblioteca</h1>
        </div>
        <span class="badge bg-secondary rounded-pill px-3 py-2 fs-6 shadow-sm"><?php echo $resultado->num_rows; ?> Obras guardadas</span>
    </div>

    <div class="row row-cols-1 row-cols-lg-2 g-4 mb-5">
        <?php if ($resultado->num_rows > 0): ?>
            <?php while ($obra = $resultado->fetch_assoc()): ?>
                <?php
                $idObra = $obra['id'];
                $obraSlug = $obra['slug'];

                // =========================================================================================
                // 3. ALGORITMO DE TRACKING DE LECTURA (HEURÍSTICA DE PROGRESO)
                // =========================================================================================
                
                // Paso 3.1: Cuantificamos el volumen total de contenido serializado de la obra actual.
                $resTotal = $conn->query("SELECT COUNT(*) as total FROM capitulos WHERE obra_id = $idObra");
                $totalCaps = $resTotal->fetch_assoc()['total'];

                // Paso 3.2: Determinamos el límite superior (High-Water Mark) de lectura del usuario.
                // Buscamos el ID más alto con el que el usuario ha interactuado en esta obra específica.
                $sqlMaxCap = "SELECT MAX(c.id) as max_id 
                              FROM capitulos_leidos cl 
                              JOIN capitulos c ON cl.capitulo_id = c.id 
                              WHERE c.obra_id = $idObra AND cl.usuario_id = $userId";
                $maxCapId = $conn->query($sqlMaxCap)->fetch_assoc()['max_id'];

                $capsLeidosCompletados = 0;

                if ($maxCapId) {
                    // Contamos cuántos capítulos existen desde el inicio hasta la marca máxima.
                    $sqlPos = "SELECT COUNT(*) as leidos FROM capitulos WHERE obra_id = $idObra AND id <= $maxCapId";
                    $capsLeidosCompletados = $conn->query($sqlPos)->fetch_assoc()['leidos'];

                    // Control de granularidad (Lectura Parcial): Analizamos el payload JSON del último capítulo tocado
                    // para saber si el usuario lo terminó o si lo dejó a medias.
                    $sqlUltimoToque = "SELECT cl.ultima_pagina, c.contenido FROM capitulos_leidos cl JOIN capitulos c ON cl.capitulo_id = c.id WHERE c.id = $maxCapId AND cl.usuario_id = $userId";
                    $ultimoToque = $conn->query($sqlUltimoToque)->fetch_assoc();
                    $imagenes = json_decode($ultimoToque['contenido'], true);
                    $totalPaginas = is_array($imagenes) ? count($imagenes) : 1;
                    $pag = intval($ultimoToque['ultima_pagina']);

                    // Si el marcador de página es mayor que 0 pero no llega al total de páginas del JSON,
                    // el capítulo está "En curso", por lo que lo restamos del total de "Completados".
                    if ($pag > 0 && $pag < $totalPaginas) {
                        $capsLeidosCompletados--;
                    }
                }

                // =========================================================================================
                // 4. LÓGICA DE ENRUTAMIENTO PREDICTIVO (NEXT ACTION)
                // =========================================================================================
                // Evaluamos el estado actual para inferir cuál es el botón más útil para el usuario
                // (Retomar donde lo dejó, leer el siguiente capítulo, o avisarle de que está al día).
                $sqlUltimo = "SELECT c.id, c.titulo, c.slug, cl.ultima_pagina, c.contenido 
                              FROM capitulos_leidos cl 
                              JOIN capitulos c ON cl.capitulo_id = c.id 
                              WHERE c.obra_id = $idObra AND cl.usuario_id = $userId 
                              ORDER BY c.id DESC LIMIT 1";
                $resUltimo = $conn->query($sqlUltimo);

                $url_detalles = "/obra/" . $obraSlug;
                $url_continuar = $url_detalles;
                $texto_continuar = "Empezar a leer";
                $icono_continuar = "fas fa-play";
                $btn_clase = "btn-outline-iori";

                if ($resUltimo->num_rows > 0) {
                    $ultimoCap = $resUltimo->fetch_assoc();
                    $imagenes = json_decode($ultimoCap['contenido'], true);
                    $totalPaginasCap = is_array($imagenes) ? count($imagenes) : 1;
                    $pagLeida = intval($ultimoCap['ultima_pagina']);
                    $capIdActual = $ultimoCap['id'];

                    if ($pagLeida >= $totalPaginasCap || $pagLeida === 0) {
                        // Estado A: El capítulo actual está terminado. Buscamos secuencialmente el siguiente ID.
                        $sqlNext = "SELECT titulo, slug FROM capitulos WHERE obra_id = $idObra AND id > $capIdActual ORDER BY id ASC LIMIT 1";
                        $resNext = $conn->query($sqlNext);

                        if ($resNext->num_rows > 0) {
                            $nextCap = $resNext->fetch_assoc();
                            // Truncamos el string del título para evitar roturas del layout en la tarjeta móvil
                            $tituloCorto = mb_strimwidth($nextCap['titulo'], 0, 20, "...");
                            $texto_continuar = "Leer: " . $tituloCorto;
                            $url_continuar = "/obra/" . $obraSlug . "/" . $nextCap['slug'];
                            $icono_continuar = "fas fa-arrow-right";
                            $btn_clase = "btn-iori";
                        } else {
                            // Estado B: No hay más capítulos disponibles (Usuario Catch-up).
                            $texto_continuar = "¡Estás al día!";
                            $url_continuar = $url_detalles;
                            $icono_continuar = "fas fa-check-double";
                            $btn_clase = "btn-success text-white";
                        }
                    } else {
                        // Estado C: Capítulo parcialmente leído. Indicamos la página exacta de retoma.
                        $tituloCorto = mb_strimwidth($ultimoCap['titulo'], 0, 18, "...");
                        $texto_continuar = "Retomar: " . $tituloCorto . " (Pág " . $pagLeida . ")";
                        $url_continuar = "/obra/" . $obraSlug . "/" . $ultimoCap['slug'];
                        $icono_continuar = "fas fa-play-circle";
                        $btn_clase = "btn-warning text-dark fw-bold";
                    }
                } else {
                    // Estado D: El usuario tiene la obra guardada pero nunca ha interactuado con ella.
                    // Buscamos el primer capítulo cronológico disponible.
                    $sqlFirst = "SELECT slug FROM capitulos WHERE obra_id = $idObra ORDER BY id ASC LIMIT 1";
                    $resFirst = $conn->query($sqlFirst);
                    if ($resFirst->num_rows > 0) {
                        $firstCap = $resFirst->fetch_assoc();
                        $url_continuar = "/obra/" . $obraSlug . "/" . $firstCap['slug'];
                    }
                }

                // Cálculo del factor de completitud para alimentar la barra de progreso visual de Bootstrap
                $porcentaje = ($totalCaps > 0) ? round(($capsLeidosCompletados / $totalCaps) * 100) : 0;
                if ($porcentaje < 0) $porcentaje = 0;

                $color_barra = ($porcentaje == 100) ? 'bg-success' : 'bg-iori';
                $portada = !empty($obra['portada']) ? ((strpos($obra['portada'], 'http') === 0) ? $obra['portada'] : '/' . ltrim($obra['portada'], '/')) : 'https://via.placeholder.com/300x450';
                ?>

                <div class="col">
                    <div class="card h-100 shadow-sm border-0 card-biblioteca">
                        <div class="row g-0 h-100">
                            <div class="col-4 col-sm-3 position-relative overflow-hidden rounded-start">
                                <a href="<?php echo $url_detalles; ?>" class="d-block h-100 w-100">
                                    <img src="<?php echo htmlspecialchars($portada); ?>" class="img-fluid h-100 w-100 zoom-img biblioteca-cover-img" alt="Portada">
                                </a>
                                <?php if (!empty($obra['tipo_obra']) && $obra['tipo_obra'] !== 'Desconocido'): ?>
                                    <span class="position-absolute top-0 start-0 badge bg-dark opacity-75 m-1 biblioteca-badge-type">
                                        <?php echo htmlspecialchars($obra['tipo_obra']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="col-8 col-sm-9 d-flex flex-column">
                                <div class="card-body p-3 d-flex flex-column h-100">

                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <a href="<?php echo $url_detalles; ?>" class="text-decoration-none text-dark d-block biblioteca-title-link">
                                            <h5 class="card-title fw-bold text-truncate mb-0"
                                                title="<?php echo htmlspecialchars($obra['titulo']); ?>">
                                                <?php echo htmlspecialchars($obra['titulo']); ?>
                                            </h5>
                                        </a>
                                        <form method="POST" action="/accion_favorito.php" class="m-0 p-0">
                                            <input type="hidden" name="obra_id" value="<?php echo $idObra; ?>">
                                            <input type="hidden" name="origen" value="biblioteca">
                                            <button type="submit" class="btn btn-link text-danger p-0 m-0 btn-trash"
                                                title="Quitar de la biblioteca">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <p class="text-muted small mb-3 text-truncate"><i class="fas fa-pen-nib me-1"></i><?php echo htmlspecialchars($obra['autor']); ?></p>

                                    <div class="mt-auto mb-3">
                                        <div class="d-flex justify-content-between align-items-end mb-1">
                                            <span class="text-secondary fw-semibold biblioteca-progress-label">Progreso</span>
                                            <span class="fw-bold <?php echo ($porcentaje == 100) ? 'text-success' : 'text-iori'; ?> biblioteca-progress-percent">
                                                <?php echo $porcentaje; ?>% <span class="text-muted fw-normal ms-1">(<?php echo $capsLeidosCompletados; ?>/<?php echo $totalCaps; ?>)</span>
                                            </span>
                                        </div>
                                        <div class="progress shadow-sm biblioteca-progress-track">
                                            <div class="progress-bar <?php echo $color_barra; ?> biblioteca-progress-bar" role="progressbar"
                                                style="width: <?php echo $porcentaje; ?>%;"
                                                aria-valuenow="<?php echo $porcentaje; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>

                                    <a href="<?php echo $url_continuar; ?>"
                                        class="btn <?php echo $btn_clase; ?> btn-sm fw-bold w-100 shadow-sm rounded-pill mt-1">
                                        <i class="<?php echo $icono_continuar; ?> me-1"></i>
                                        <?php echo htmlspecialchars($texto_continuar); ?>
                                    </a>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 my-4 rounded-4 shadow-sm bg-white border border-light">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4 biblioteca-empty-icon">
                    <i class="far fa-folder-open fa-3x text-muted opacity-50"></i>
                </div>
                <h3 class="text-dark fw-bold mb-2">Tu biblioteca está vacía</h3>
                <p class="text-muted mb-4 fs-5">Añade obras a tus favoritos para hacer seguimiento de tu lectura.</p>
                <a href="/" class="btn btn-iori btn-lg rounded-pill px-5 fw-bold shadow-sm"><i class="fas fa-search me-2"></i>Explorar el Catálogo</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>