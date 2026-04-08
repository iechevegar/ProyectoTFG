<?php
session_start();
require 'includes/db.php';

// --- COMPROBAR SI EL USUARIO ESTÁ SUSPENDIDO ---
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
// -----------------------------------------------

// --- FUNCIÓN AUXILIAR DE TIEMPO ---
function tiempo_transcurrido_foro($fecha) {
    if (!$fecha) return "";
    $timestamp = strtotime($fecha);
    $diferencia = time() - $timestamp;
    
    if ($diferencia < 60) return "Hace instantes";
    if ($diferencia < 3600) return "Hace " . floor($diferencia / 60) . " min";
    if ($diferencia < 86400) return "Hace " . floor($diferencia / 3600) . " h";
    return date("d/m/Y", $timestamp);
}

// --- LÓGICA DE FILTROS ---
$busqueda = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$categoria = isset($_GET['cat']) ? $_GET['cat'] : 'todas';
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'actividad';

// --- CONFIGURACIÓN DE PAGINACIÓN ---
$resultados_por_pagina = 8;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $resultados_por_pagina;

// --- CONDICIÓN SQL BASE ---
$condicion_sql = "WHERE 1=1";
if (!empty($busqueda)) {
    $condicion_sql .= " AND (t.titulo LIKE '%$busqueda%' OR t.contenido LIKE '%$busqueda%')";
}
if ($categoria !== 'todas') {
    $catLimpia = $conn->real_escape_string($categoria);
    $condicion_sql .= " AND t.categoria = '$catLimpia'";
}

// --- CALCULAR TOTAL DE PÁGINAS ---
$sqlTotal = "SELECT COUNT(t.id) as total FROM foro_temas t " . $condicion_sql;
$resTotal = $conn->query($sqlTotal);
$total_registros = $resTotal->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $resultados_por_pagina);

// --- CONSTRUCCIÓN DE LA CONSULTA SQL PRINCIPAL ---
// t.* ya nos trae el 'slug' que creamos en la base de datos
$sql = "SELECT t.*, u.nombre, u.foto, u.rol,
        (SELECT COUNT(*) FROM foro_respuestas WHERE tema_id = t.id) as num_respuestas,
        (SELECT fecha FROM foro_respuestas WHERE tema_id = t.id ORDER BY fecha DESC LIMIT 1) as ultima_actividad_fecha,
        (SELECT u2.nombre FROM foro_respuestas r JOIN usuarios u2 ON r.usuario_id = u2.id WHERE r.tema_id = t.id ORDER BY r.fecha DESC LIMIT 1) as ultimo_usuario
        FROM foro_temas t
        JOIN usuarios u ON t.usuario_id = u.id
        $condicion_sql";

if ($orden === 'populares') {
    $sql .= " ORDER BY num_respuestas DESC, t.fecha DESC";
} elseif ($orden === 'antiguos') {
    $sql .= " ORDER BY t.fecha ASC";
} elseif ($orden === 'recientes') {
    $sql .= " ORDER BY t.fecha DESC"; 
} else {
    $sql .= " ORDER BY COALESCE(ultima_actividad_fecha, t.fecha) DESC";
}

$sql .= " LIMIT $resultados_por_pagina OFFSET $offset";
$resultado = $conn->query($sql);

$parametros_url = $_GET;
unset($parametros_url['pagina']); 
unset($parametros_url['i']);

$url_base = "/foro?" . http_build_query($parametros_url) . (empty($parametros_url) ? "" : "&");

function badgeColor($cat) {
    switch($cat) {
        case 'Teorías': return 'bg-purple text-white'; 
        case 'Noticias': return 'bg-danger';
        case 'Recomendaciones': return 'bg-success';
        case 'Off-Topic': return 'bg-secondary';
        default: return 'bg-primary';
    }
}
?>
<?php include 'includes/header.php'; ?>

<main class="container py-5">
    
    <div class="row">
        <div class="col-lg-3 mb-4">
            
            <?php if(isset($_SESSION['usuario'])): ?>
                
                <?php if($estaSuspendido): ?>
                    <div class="alert alert-danger text-center shadow-sm mb-4 border-danger p-3">
                        <i class="fas fa-ban fa-2x mb-2 opacity-75"></i>
                        <h6 class="fw-bold mb-1">Cuenta Suspendida</h6>
                        <small class="d-block mb-1">No puedes crear temas.</small>
                        <small class="fw-bold opacity-75 text-danger">Hasta: <?php echo $fechaDesbloqueoStr; ?></small>
                    </div>
                <?php else: ?>
                    <a href="/crear_tema.php" class="btn btn-primary w-100 mb-4 fw-bold shadow-sm">
                        <i class="fas fa-plus me-2"></i>Crear Nuevo Tema
                    </a>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-info small text-center mb-4">
                    <a href="/login" class="fw-bold">Entra</a> para crear temas.
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['error']) && $_GET['error'] == 'cuenta_suspendida'): ?>
                <div class="alert alert-danger small text-center mb-4 shadow-sm">
                    <i class="fas fa-exclamation-triangle me-1"></i> Acción denegada.
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h6 class="fw-bold text-muted mb-3">BUSCAR</h6>
                    <form action="/foro" method="GET">
                        <div class="input-group">
                            <input type="text" name="q" class="form-control" placeholder="Palabra clave..." value="<?php echo htmlspecialchars($busqueda); ?>">
                            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                        </div>
                        <?php if($categoria != 'todas') echo '<input type="hidden" name="cat" value="'.$categoria.'">'; ?>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-0">
                    <div class="list-group list-group-flush rounded-3">
                        <div class="list-group-item bg-light fw-bold text-muted small">CATEGORÍAS</div>
                        
                        <a href="/foro?cat=todas" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $categoria=='todas'?'active':''; ?>">
                            Todas
                            <i class="fas fa-layer-group opacity-50"></i>
                        </a>
                        <a href="/foro?cat=General" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $categoria=='General'?'active':''; ?>">
                            General
                        </a>
                        <a href="/foro?cat=Teorías" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $categoria=='Teorías'?'active':''; ?>">
                            Teorías
                            <i class="fas fa-brain opacity-50"></i>
                        </a>
                        <a href="/foro?cat=Noticias" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $categoria=='Noticias'?'active':''; ?>">
                            Noticias
                            <i class="fas fa-bullhorn opacity-50"></i>
                        </a>
                        <a href="/foro?cat=Recomendaciones" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $categoria=='Recomendaciones'?'active':''; ?>">
                            Recomendaciones
                            <i class="fas fa-thumbs-up opacity-50"></i>
                        </a>
                        <a href="/foro?cat=Off-Topic" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $categoria=='Off-Topic'?'active':''; ?>">
                            Off-Topic
                            <i class="fas fa-coffee opacity-50"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold mb-0">
                    <?php echo ($categoria === 'todas') ? 'Discusiones' : 'Categoría: ' . htmlspecialchars($categoria); ?>
                </h4>
                
                <form action="/foro" method="GET" class="d-flex align-items-center">
                    <?php if($categoria != 'todas') echo '<input type="hidden" name="cat" value="'.$categoria.'">'; ?>
                    <?php if($busqueda != '') echo '<input type="hidden" name="q" value="'.$busqueda.'">'; ?>
                    
                    <label class="me-2 small text-muted">Ordenar:</label>
                    <select name="orden" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 140px;">
                        <option value="actividad" <?php echo $orden=='actividad'?'selected':''; ?>>Última Actividad</option>
                        <option value="recientes" <?php echo $orden=='recientes'?'selected':''; ?>>Temas Nuevos</option>
                        <option value="antiguos" <?php echo $orden=='antiguos'?'selected':''; ?>>Más Antiguos</option>
                        <option value="populares" <?php echo $orden=='populares'?'selected':''; ?>>Más Populares</option>
                    </select>
                </form>
            </div>

            <div class="d-flex flex-column gap-3 mb-4">
                <?php if ($resultado->num_rows > 0): ?>
                    <?php while($tema = $resultado->fetch_assoc()): ?>
                        
                        <div class="card shadow-sm border-0 tema-card">
                            <div class="card-body d-flex gap-3">
                                <div class="text-center d-none d-sm-block" style="width: 60px;">
                                    <?php 
                                        // Truco HTTP para avatares
                                        $foto = !empty($tema['foto']) ? ((strpos($tema['foto'], 'http') === 0) ? $tema['foto'] : '/' . ltrim($tema['foto'], '/')) : 'https://via.placeholder.com/50'; 
                                    ?>
                                    <img src="<?php echo htmlspecialchars($foto); ?>" class="rounded-circle mb-1" width="50" height="50" style="object-fit:cover;">
                                </div>

                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <span class="badge <?php echo badgeColor($tema['categoria']); ?> mb-1 me-1"><?php echo $tema['categoria']; ?></span>
                                            <a href="/foro/<?php echo urlencode($tema['slug']); ?>" class="text-decoration-none text-dark fw-bold fs-5 stretched-link">
                                                <?php echo htmlspecialchars($tema['titulo']); ?>
                                            </a>
                                        </div>
                                        
                                        <div class="text-center text-muted ms-3" style="min-width: 60px;">
                                            <i class="far fa-comment-dots fs-5"></i>
                                            <div class="small fw-bold"><?php echo $tema['num_respuestas']; ?></div>
                                        </div>
                                    </div>
                                    
                                    <p class="text-secondary small mb-2 text-truncate" style="max-width: 90%;">
                                        <?php echo htmlspecialchars(substr($tema['contenido'], 0, 120)) . '...'; ?>
                                    </p>

                                    <div class="d-flex align-items-center justify-content-between text-muted" style="font-size: 0.8rem;">
                                        <div>
                                            <span class="me-3"><i class="far fa-user me-1"></i> <?php echo htmlspecialchars($tema['nombre']); ?> (Creador)</span>
                                            <span><i class="far fa-calendar me-1"></i> <?php echo date('d/m/Y', strtotime($tema['fecha'])); ?></span>
                                        </div>
                                        
                                        <?php if($tema['num_respuestas'] > 0): ?>
                                            <div class="text-end d-none d-md-block">
                                                <i class="fas fa-reply fa-rotate-180 text-primary me-1"></i>
                                                <span class="fw-bold"><?php echo htmlspecialchars($tema['ultimo_usuario']); ?></span> 
                                                <span class="ms-1"><?php echo tiempo_transcurrido_foro($tema['ultima_actividad_fecha']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="100" class="mb-3 opacity-50" alt="Vacio">
                        <h5 class="text-muted">No se encontraron temas</h5>
                        <p class="text-muted small">Prueba a cambiar los filtros o crea uno nuevo.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Paginación del foro" class="mt-4 mb-5">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link shadow-sm" href="<?php echo $url_base . 'pagina=' . ($pagina_actual - 1); ?>">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_paginas; $i++): ?>
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

        </div>
    </div>
</main>

<style>
    .tema-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .tema-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.1)!important; }
</style>

<?php include 'includes/footer.php'; ?>