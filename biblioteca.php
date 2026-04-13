<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: /login");
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
            <?php while ($obra = $resultado->fetch_assoc()): ?>
                <?php
                $idObra = $obra['id'];
                $obraSlug = $obra['slug'];

                // 1. Contar TOTAL de capítulos de la obra
                $resTotal = $conn->query("SELECT COUNT(*) as total FROM capitulos WHERE obra_id = $idObra");
                $totalCaps = $resTotal->fetch_assoc()['total'];

                // 2. LÓGICA DE PROGRESO "ASUMIDO" (Lo que tú sugeriste)
                // Buscamos el ID del capítulo más alto que ha tocado el usuario
                $sqlMaxCap = "SELECT MAX(c.id) as max_id 
                              FROM capitulos_leidos cl 
                              JOIN capitulos c ON cl.capitulo_id = c.id 
                              WHERE c.obra_id = $idObra AND cl.usuario_id = $userId";
                $maxCapId = $conn->query($sqlMaxCap)->fetch_assoc()['max_id'];

                $capsLeidosCompletados = 0;

                if ($maxCapId) {
                    // Contamos cuántos capítulos hay desde el inicio hasta ese último capítulo que tocó
                    $sqlPos = "SELECT COUNT(*) as leidos FROM capitulos WHERE obra_id = $idObra AND id <= $maxCapId";
                    $capsLeidosCompletados = $conn->query($sqlPos)->fetch_assoc()['leidos'];

                    // Ajuste fino: Si el último capítulo que tocó no está terminado, le restamos 1 al progreso general
                    $sqlUltimoToque = "SELECT cl.ultima_pagina, c.contenido FROM capitulos_leidos cl JOIN capitulos c ON cl.capitulo_id = c.id WHERE c.id = $maxCapId AND cl.usuario_id = $userId";
                    $ultimoToque = $conn->query($sqlUltimoToque)->fetch_assoc();
                    $imagenes = json_decode($ultimoToque['contenido'], true);
                    $totalPaginas = is_array($imagenes) ? count($imagenes) : 1;
                    $pag = intval($ultimoToque['ultima_pagina']);

                    if ($pag > 0 && $pag < $totalPaginas) {
                        $capsLeidosCompletados--; // No ha terminado el actual, así que no lo sumamos al total de completados
                    }
                }

                // 3. LÓGICA DE NAVEGACIÓN (Botón Inteligente)
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
                $color_boton = "btn-primary";

                if ($resUltimo->num_rows > 0) {
                    $ultimoCap = $resUltimo->fetch_assoc();
                    $imagenes = json_decode($ultimoCap['contenido'], true);
                    $totalPaginasCap = is_array($imagenes) ? count($imagenes) : 1;
                    $pagLeida = intval($ultimoCap['ultima_pagina']);
                    $capIdActual = $ultimoCap['id'];

                    if ($pagLeida >= $totalPaginasCap || $pagLeida === 0) {
                        // Terminó este capítulo. Buscamos si existe el SIGUIENTE.
                        $sqlNext = "SELECT titulo, slug FROM capitulos WHERE obra_id = $idObra AND id > $capIdActual ORDER BY id ASC LIMIT 1";
                        $resNext = $conn->query($sqlNext);

                        if ($resNext->num_rows > 0) {
                            $nextCap = $resNext->fetch_assoc();
                            // Recortamos el título si tiene más de 18 caracteres
                            $tituloCorto = mb_strimwidth($nextCap['titulo'], 0, 18, "...");
                            $texto_continuar = "Sig: " . $tituloCorto;
                            $url_continuar = "/obra/" . $obraSlug . "/" . $nextCap['slug'];
                            $icono_continuar = "fas fa-forward";
                        } else {
                            $texto_continuar = "¡Al día!";
                            $url_continuar = $url_detalles;
                            $icono_continuar = "fas fa-check-circle";
                            $color_boton = "btn-success";
                        }
                    } else {
                        // Está a medias de un capítulo. Recortamos el título para que quepa la página.
                        $tituloCorto = mb_strimwidth($ultimoCap['titulo'], 0, 15, "...");
                        $texto_continuar = $tituloCorto . " (Pág. " . $pagLeida . ")";
                        $url_continuar = "/obra/" . $obraSlug . "/" . $ultimoCap['slug'];
                        $icono_continuar = "fas fa-play-circle";
                    }
                } else {
                    // No ha empezado. Buscamos el PRIMER capítulo.
                    $sqlFirst = "SELECT slug FROM capitulos WHERE obra_id = $idObra ORDER BY id ASC LIMIT 1";
                    $resFirst = $conn->query($sqlFirst);
                    if ($resFirst->num_rows > 0) {
                        $firstCap = $resFirst->fetch_assoc();
                        $url_continuar = "/obra/" . $obraSlug . "/" . $firstCap['slug'];
                    }
                }

                // Calcular porcentaje para la barra
                $porcentaje = ($totalCaps > 0) ? round(($capsLeidosCompletados / $totalCaps) * 100) : 0;
                // Prevenir que el porcentaje sea negativo si no hay caps
                if ($porcentaje < 0)
                    $porcentaje = 0;

                // Arreglar la ruta de la portada
                $portada = !empty($obra['portada']) ? ((strpos($obra['portada'], 'http') === 0) ? $obra['portada'] : '/' . ltrim($obra['portada'], '/')) : 'https://via.placeholder.com/300x450';
                ?>

                <div class="col">
                    <div class="card h-100 shadow-sm border hover-effect overflow-hidden bg-white d-flex flex-column">

                        <a href="<?php echo $url_detalles; ?>" class="text-decoration-none text-dark flex-grow-1">
                            <div class="position-relative" style="padding-top: 145%;">
                                <img src="<?php echo htmlspecialchars($portada); ?>"
                                    class="position-absolute top-0 start-0 w-100 h-100 zoom-img border-bottom"
                                    style="object-fit: cover;" alt="Portada">
                            </div>

                            <div class="card-body p-3 pb-0">
                                <h6 class="card-title fw-bold text-truncate mb-2 text-dark">
                                    <?php echo htmlspecialchars($obra['titulo']); ?>
                                </h6>

                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted" style="font-size: 0.75rem;">Progreso</small>
                                    <small class="fw-bold text-primary" style="font-size: 0.75rem;">
                                        <?php echo $capsLeidosCompletados; ?> / <?php echo $totalCaps; ?>
                                    </small>
                                </div>
                                <div class="progress border mb-3" style="height: 6px; background-color: #f1f1f1;">
                                    <div class="progress-bar bg-primary" role="progressbar"
                                        style="width: <?php echo $porcentaje; ?>%;" aria-valuenow="<?php echo $porcentaje; ?>"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </a>

                        <div class="p-3 pt-0 mt-auto">
                            <div class="d-grid">
                                <a href="<?php echo $url_continuar; ?>"
                                    class="btn <?php echo $color_boton; ?> btn-sm fw-bold text-truncate"
                                    title="<?php echo htmlspecialchars($texto_continuar); ?>">
                                    <i class="<?php echo $icono_continuar; ?> me-1"></i>
                                    <?php echo htmlspecialchars($texto_continuar); ?>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 rounded-3 border bg-light">
                <i class="far fa-folder-open fa-3x mb-3 text-muted opacity-50"></i>
                <h4 class="text-secondary fw-bold">Tu biblioteca está vacía</h4>
                <p class="text-muted">Guarda tus obras favoritas para no perder el progreso.</p>
                <a href="/" class="btn btn-primary mt-2 fw-bold">Explorar Catálogo</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
    .hover-effect {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border-radius: 8px;
    }

    .hover-effect:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08) !important;
    }

    .zoom-img {
        transition: transform 0.4s ease;
    }

    .hover-effect:hover .zoom-img {
        transform: scale(1.03);
    }
</style>

<?php include 'includes/footer.php'; ?>