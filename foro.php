<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. SISTEMA DE RESTRICCIONES (ACCOUNT STATE CHECKS)
// =========================================================================================
// Antes de renderizar la UI, verificamos si el usuario tiene un "Soft-Ban" activo.
// Esta variable booleana ($estaSuspendido) nos permitirá mutar la interfaz condicionalmente,
// ocultando los botones de "Crear Tema" y mostrando en su lugar la fecha de finalización del castigo.
$estaSuspendido = false;
$fechaDesbloqueoStr = '';

if (isset($_SESSION['usuario'])) {
    $nombreUser = $_SESSION['usuario'];
    $resUser = $conn->query("SELECT id, fecha_desbloqueo FROM usuarios WHERE nombre = '$nombreUser'");
    if ($resUser && $resUser->num_rows > 0) {
        $userData = $resUser->fetch_assoc();

        if (!empty($userData['fecha_desbloqueo']) && strtotime($userData['fecha_desbloqueo']) > time()) {
            $estaSuspendido = true;
            $fechaDesbloqueoStr = date('d/m/Y H:i', strtotime($userData['fecha_desbloqueo']));
        }
    }
}


// =========================================================================================
// 2. HELPERS DE PRESENTACIÓN (UI/UX)
// =========================================================================================
// Función auxiliar para humanizar las fechas de actividad (Time-Ago Formatting).
// En lugar de mostrar un timestamp frío, calculamos la diferencia temporal para mejorar
// drásticamente la experiencia de usuario (UX) dando sensación de "comunidad en tiempo real".
function tiempo_transcurrido_foro($fecha)
{
    if (!$fecha) return "";
    $timestamp = strtotime($fecha);
    $diferencia = time() - $timestamp;

    if ($diferencia < 60) return "Hace instantes";
    if ($diferencia < 3600) return "Hace " . floor($diferencia / 60) . " min";
    if ($diferencia < 86400) return "Hace " . floor($diferencia / 3600) . " h";
    return date("d/m/Y", $timestamp); // Fallback a fecha absoluta si pasó más de un día
}


// =========================================================================================
// 3. QUERY BUILDER: FILTRADO DINÁMICO Y PAGINACIÓN
// =========================================================================================
// Recolectamos los parámetros GET sanitizándolos inmediatamente para evitar inyecciones SQL.
$busqueda = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$categoria = isset($_GET['cat']) ? $_GET['cat'] : 'todas';
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'actividad';

$resultados_por_pagina = 8;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $resultados_por_pagina;

// Construcción modular de la cláusula WHERE.
$condicion_sql = "WHERE 1=1";
if (!empty($busqueda)) {
    // Búsqueda Full-Text básica en título y cuerpo del mensaje
    $condicion_sql .= " AND (t.titulo LIKE '%$busqueda%' OR t.contenido LIKE '%$busqueda%')";
}
if ($categoria !== 'todas') {
    $catLimpia = $conn->real_escape_string($categoria);
    $condicion_sql .= " AND t.categoria = '$catLimpia'";
}

// Calculamos el volumen total del Data-Set aplicando los filtros para poder construir el Paginador visual.
$sqlTotal = "SELECT COUNT(t.id) as total FROM foro_temas t " . $condicion_sql;
$resTotal = $conn->query($sqlTotal);
$total_registros = $resTotal->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $resultados_por_pagina);


// =========================================================================================
// 4. LA MACRO-CONSULTA DEL FEED (N+1 PROBLEM AVOIDANCE)
// =========================================================================================
// NOTA ARQUITECTÓNICA CLAVE: En lugar de iterar con un foreach en PHP y lanzar una consulta 
// a la base de datos por cada tema para contar sus respuestas (lo que causaría el Problema N+1),
// utilizamos "Subconsultas Correlacionadas" (Correlated Subqueries). 
// Esto nos permite traernos toda la agregación (conteo, fecha de última respuesta y autor de la misma) 
// en una única e hiper-optimizada transacción de red.
$sql = "SELECT t.*, u.nombre, u.foto, u.rol,
        (SELECT COUNT(*) FROM foro_respuestas WHERE tema_id = t.id) as num_respuestas,
        (SELECT fecha FROM foro_respuestas WHERE tema_id = t.id ORDER BY fecha DESC LIMIT 1) as ultima_actividad_fecha,
        (SELECT u2.nombre FROM foro_respuestas r JOIN usuarios u2 ON r.usuario_id = u2.id WHERE r.tema_id = t.id ORDER BY r.fecha DESC LIMIT 1) as ultimo_usuario
        FROM foro_temas t
        JOIN usuarios u ON t.usuario_id = u.id
        $condicion_sql";

// --- ALGORITMO DE BUMPING Y ORDENAMIENTO ---
if ($orden === 'populares') {
    $sql .= " ORDER BY num_respuestas DESC, t.fecha DESC";
} elseif ($orden === 'antiguos') {
    $sql .= " ORDER BY t.fecha ASC";
} elseif ($orden === 'recientes') {
    $sql .= " ORDER BY t.fecha DESC";
} else {
    // EL MOTOR DE BUMPING: Usamos la función COALESCE de SQL. 
    // Comprueba la fecha de la última respuesta; si es NULL (nadie ha respondido aún), 
    // utiliza la fecha de creación del tema. Esto simula el comportamiento natural de foros 
    // clásicos donde un comentario nuevo "sube" el tema arriba del todo.
    $sql .= " ORDER BY COALESCE(ultima_actividad_fecha, t.fecha) DESC";
}

$sql .= " LIMIT $resultados_por_pagina OFFSET $offset";
$resultado = $conn->query($sql);


// =========================================================================================
// 5. MANTENIMIENTO DEL ESTADO (STATE PRESERVATION)
// =========================================================================================
// Generamos dinámicamente la URL base para el paginador. 
// Conservamos cualquier parámetro GET activo (como la búsqueda o el orden) eliminando 
// la "página" actual, para que al hacer clic en "Siguiente" los filtros no se reseteen.
$url_base = "/foro?" . http_build_query(array_diff_key($_GET, ["pagina" => ""])) . (count($_GET) > (isset($_GET['pagina']) ? 1 : 0) ? "&" : "");

// Micro-helper para la estandarización visual de las etiquetas
function badgeColor($cat)
{
    switch ($cat) {
        case 'Teorías': return 'bg-purple text-white';
        case 'Noticias': return 'bg-danger text-white';
        case 'Recomendaciones': return 'bg-success text-white';
        case 'Off-Topic': return 'bg-secondary text-white';
        default: return 'bg-iori text-white';
    }
}
?>
<?php include 'includes/header.php'; ?>

<main class="container py-5 foro-main-container">

    <div class="row">
        <div class="col-lg-3 mb-4">

            <?php if (isset($_SESSION['usuario'])): ?>
                <?php if ($estaSuspendido): ?>
                    <div class="alert alert-danger text-center shadow-sm mb-4 border-danger p-3 rounded-4">
                        <i class="fas fa-ban fa-2x mb-2 opacity-75"></i>
                        <h6 class="fw-bold mb-1">Cuenta Suspendida</h6>
                        <small class="d-block mb-1">Tu rol ha sido limitado a Modo Lectura.</small>
                        <small class="fw-bold opacity-75 text-danger">Restricción activa hasta: <?php echo $fechaDesbloqueoStr; ?></small>
                    </div>
                <?php else: ?>
                    <a href="/foro/agregar-tema" class="btn btn-iori w-100 mb-4 fw-bold shadow-sm rounded-pill py-2 text-uppercase" style="letter-spacing: 1px;">
                        <i class="fas fa-plus me-2"></i>Crear Tema
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden bg-white text-center">
                    <div class="card-body p-4">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 foro-cta-icon">
                            <i class="fas fa-users fa-2x text-iori opacity-75"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-2">¡Únete al debate!</h6>
                        <p class="text-muted small mb-3" style="line-height: 1.4;">Inicia sesión para participar y compartir tu opinión con la comunidad.</p>
                        <a href="/login" class="btn btn-iori w-100 fw-bold shadow-sm rounded-pill py-2 text-uppercase">
                            <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden">
                <div class="card-body bg-white">
                    <h6 class="fw-bold text-dark mb-3 small text-uppercase"><i class="fas fa-search text-iori me-2"></i>Buscar en Foro</h6>
                    <form action="/foro" method="GET">
                        <div class="input-group shadow-sm">
                            <input type="text" name="q" class="form-control border-light bg-light" placeholder="Palabra clave..." value="<?php echo htmlspecialchars($busqueda); ?>">
                            <button class="btn btn-iori" type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden">
                <div class="card-body p-0 bg-white">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item bg-light fw-bold text-dark small py-3 text-uppercase"><i class="fas fa-folder-open text-iori me-2"></i>Categorías</div>
                        <a href="/foro?cat=todas" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 <?php echo $categoria == 'todas' ? 'active fw-bold' : 'text-secondary'; ?>">Todas las discusiones <i class="fas fa-layer-group opacity-50"></i></a>
                        <a href="/foro?cat=General" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 <?php echo $categoria == 'General' ? 'active fw-bold' : 'text-secondary'; ?>">General <i class="fas fa-comments opacity-50"></i></a>
                        <a href="/foro?cat=Teorías" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 <?php echo $categoria == 'Teorías' ? 'active fw-bold' : 'text-secondary'; ?>">Teorías <i class="fas fa-brain opacity-50"></i></a>
                        <a href="/foro?cat=Noticias" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 <?php echo $categoria == 'Noticias' ? 'active fw-bold' : 'text-secondary'; ?>">Noticias <i class="fas fa-bullhorn opacity-50"></i></a>
                        <a href="/foro?cat=Recomendaciones" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 <?php echo $categoria == 'Recomendaciones' ? 'active fw-bold' : 'text-secondary'; ?>">Recomendaciones <i class="fas fa-thumbs-up opacity-50"></i></a>
                        <a href="/foro?cat=Off-Topic" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 <?php echo $categoria == 'Off-Topic' ? 'active fw-bold' : 'text-secondary'; ?>">Off-Topic <i class="fas fa-coffee opacity-50"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-4 bg-white p-3 rounded-4 shadow-sm border border-light">
                <h4 class="fw-bold mb-3 mb-sm-0 text-dark d-flex align-items-center">
                    <i class="fas fa-list-ul text-iori me-2"></i>
                    <?php echo ($categoria === 'todas') ? 'Últimas Discusiones' : 'Categoría: ' . htmlspecialchars($categoria); ?>
                </h4>

                <form action="/foro" method="GET" class="d-flex align-items-center">
                    <?php if ($categoria != 'todas') echo '<input type="hidden" name="cat" value="' . $categoria . '">'; ?>
                    <label class="me-2 small fw-bold text-secondary text-uppercase d-none d-md-block">Ordenar:</label>
                    <select name="orden" class="form-select border-light shadow-sm bg-light fw-semibold text-secondary foro-order-select" onchange="this.form.submit()">
                        <option value="actividad" <?php echo $orden == 'actividad' ? 'selected' : ''; ?>>Última Actividad</option>
                        <option value="recientes" <?php echo $orden == 'recientes' ? 'selected' : ''; ?>>Más Recientes</option>
                        <option value="populares" <?php echo $orden == 'populares' ? 'selected' : ''; ?>>Más Populares</option>
                        <option value="antiguos" <?php echo $orden == 'antiguos' ? 'selected' : ''; ?>>Más Antiguos</option>
                    </select>
                </form>
            </div>

            <div class="d-flex flex-column gap-3 mb-4">
                <?php if ($resultado->num_rows > 0): ?>
                    <?php while ($tema = $resultado->fetch_assoc()): ?>
                        <div class="card shadow-sm border-0 rounded-4 tema-card bg-white position-relative overflow-hidden">
                            <div class="card-body p-4 d-flex gap-3 align-items-center">
                                
                                <div class="text-center flex-shrink-0">
                                    <?php
                                    $foto = !empty($tema['foto'])
                                        ? ((strpos($tema['foto'], 'http') === 0) ? $tema['foto'] : '/' . ltrim($tema['foto'], '/'))
                                        : 'https://ui-avatars.com/api/?name=' . urlencode($tema['nombre']) . '&background=0D8A92&color=fff&size=60&font-size=0.4&bold=true';
                                    ?>
                                    <img src="<?php echo htmlspecialchars($foto); ?>" class="rounded-circle border border-2 border-light shadow-sm" width="55" height="55" style="object-fit:cover;">
                                </div>

                                <div class="flex-grow-1 min-width-0">
                                    <div class="mb-1">
                                        <span class="badge <?php echo badgeColor($tema['categoria']); ?> rounded-pill px-2 py-1 me-2 shadow-sm align-middle badge-foro-small"><?php echo $tema['categoria']; ?></span>
                                        <a href="/foro/<?php echo urlencode($tema['slug']); ?>" class="text-decoration-none text-dark fw-bold fs-5 stretched-link align-middle"><?php echo htmlspecialchars($tema['titulo']); ?></a>
                                    </div>
                                    <p class="text-secondary small mb-2 text-truncate foro-post-content-preview">
                                        <?php echo htmlspecialchars(substr($tema['contenido'], 0, 150)) . '...'; ?>
                                    </p>
                                    
                                    <div class="d-flex align-items-center flex-wrap text-muted small">
                                        <span class="me-3 fw-semibold"><i class="fas fa-user-edit text-iori me-1"></i><?php echo htmlspecialchars($tema['nombre']); ?></span>
                                        <span class="me-3"><i class="far fa-calendar-alt me-1"></i><?php echo date('d M Y', strtotime($tema['fecha'])); ?></span>
                                        <?php if ($tema['num_respuestas'] > 0): ?>
                                            <span class="d-none d-md-inline text-primary bg-primary bg-opacity-10 px-2 py-1 rounded">
                                                <i class="fas fa-reply fa-rotate-180 me-1"></i>Última resp. por <strong><?php echo htmlspecialchars($tema['ultimo_usuario']); ?></strong>
                                                <span class="ms-1 opacity-75">(<?php echo tiempo_transcurrido_foro($tema['ultima_actividad_fecha']); ?>)</span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="d-none d-sm-flex flex-column align-items-center justify-content-center bg-light rounded-4 ms-3 border foro-counter-box">
                                    <i class="far fa-comments text-iori mb-1 fs-5"></i>
                                    <span class="fw-bold fs-5 lh-1 text-dark"><?php echo $tema['num_respuestas']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 bg-white rounded-4 shadow-sm border border-light">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 biblioteca-empty-icon">
                            <i class="fas fa-search fa-2x text-muted opacity-50"></i>
                        </div>
                        <h5 class="fw-bold text-dark">No se encontraron temas</h5>
                        <p class="text-muted mb-4">Prueba a cambiar los filtros o anímate a iniciar la conversación.</p>
                        <?php if (isset($_SESSION['usuario']) && !$estaSuspendido): ?>
                            <a href="/foro/agregar-tema" class="btn btn-iori rounded-pill fw-bold px-4 shadow-sm">Crear un Tema Nuevo</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Paginación del foro" class="mt-4 mb-5">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link shadow-sm border-0 text-iori fw-bold" href="<?php echo $url_base . 'pagina=' . ($pagina_actual - 1); ?>" style="border-radius: 50px 0 0 50px;"><i class="fas fa-chevron-left me-1"></i> Anterior</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?php echo ($pagina_actual == $i) ? 'active' : ''; ?>">
                                <a class="page-link shadow-sm border-0 <?php echo ($pagina_actual == $i) ? 'bg-iori text-white' : 'text-dark fw-semibold'; ?>" href="<?php echo $url_base . 'pagina=' . $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                            <a class="page-link shadow-sm border-0 text-iori fw-bold" href="<?php echo $url_base . 'pagina=' . ($pagina_actual + 1); ?>" style="border-radius: 0 50px 50px 0;">Siguiente <i class="fas fa-chevron-right ms-1"></i></a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>