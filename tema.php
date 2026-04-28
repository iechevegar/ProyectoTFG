<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. ENRUTAMIENTO SEMÁNTICO Y RESOLUCIÓN DE IDENTIDADES (SLUG -> ID)
// =========================================================================================
// El frontend utiliza URLs amigables (Slugs) por motivos de SEO y legibilidad.
// Sin embargo, el motor relacional trabaja de forma óptima con IDs numéricos.
// Este bloque actúa como un "Resolver": captura el slug de la URL y busca su Primary Key.
if (!isset($_GET['slug']) || empty($_GET['slug'])) {
    header("Location: /foro");
    exit();
}

$slug = $_GET['slug'];

// Prevención de Inyección SQL al resolver el identificador del recurso.
$sql_id = "SELECT id FROM foro_temas WHERE slug = ?";
$stmt_id = $conn->prepare($sql_id);
$stmt_id->bind_param("s", $slug);
$stmt_id->execute();
$res_id = $stmt_id->get_result();

// Patrón Fail-Fast (404 Lógico): Si un usuario o crawler intenta acceder a un slug 
// modificado, obsoleto o eliminado, detenemos el script y enrutamos a la página de error.
if ($res_id->num_rows === 0) {
    header("Location: /404.php");
    exit();
}

$idTema = $res_id->fetch_assoc()['id'];
$resultados_por_pagina = 10; 


// =========================================================================================
// 2. MIDDLEWARE DE ESTADO Y POLÍTICAS DE COMUNIDAD (SOFT-BANS)
// =========================================================================================
// Comprobamos si el usuario tiene una sanción vigente usando get_estado_usuario(),
// que usa prepared statement internamente (Anti-SQLi).
$estaSuspendido = false;
$fechaDesbloqueoStr = '';
$userId = null;

if (isset($_SESSION['usuario'])) {
    $estadoUser = get_estado_usuario($conn);
    $userId             = $estadoUser['id'];
    $estaSuspendido     = $estadoUser['suspendido'];
    $fechaDesbloqueoStr = $estadoUser['hasta'] ?? '';
}


// =========================================================================================
// 3. CONTROLADOR DE MUTACIONES Y ACCIONES SEGURAS (POST METHODS)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- A. CREACIÓN DE NUEVA RESPUESTA ---
    if (isset($_POST['respuesta'])) {
        if ($userId) {
            
            // DEFENSA EN PROFUNDIDAD (Defense in Depth): Aunque ocultemos el formulario en el HTML 
            // a los usuarios suspendidos, un atacante podría forzar un POST usando herramientas como Postman. 
            // Bloqueamos la transacción estrictamente a nivel de Backend.
            if ($estaSuspendido) {
                header("Location: /foro/$slug");
                exit();
            }

            $mensaje = trim($_POST['respuesta']);
            if (!empty($mensaje)) {
                $stmt = $conn->prepare("INSERT INTO foro_respuestas (tema_id, usuario_id, mensaje) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $idTema, $userId, $mensaje);
                $stmt->execute();
                
                // --- ALGORITMO DE NAVEGACIÓN INTELIGENTE (UX MÁGICA) ---
                // Para evitar que el usuario se quede en la página 1 y tenga que buscar su propio
                // comentario, calculamos matemáticamente en qué página de la paginación se acaba 
                // de ubicar su respuesta, y lo redirigimos exactamente a ese punto.
                $sqlCount = "SELECT COUNT(id) as total FROM foro_respuestas WHERE tema_id = $idTema";
                $total_resp = $conn->query($sqlCount)->fetch_assoc()['total'];
                $ultima_pagina = max(1, ceil($total_resp / $resultados_por_pagina));
                
                // Redirección PRG (Post/Redirect/Get) parametrizada
                header("Location: /foro/$slug?pagina=$ultima_pagina");
                exit();
            }
        } else {
            header("Location: /login");
            exit();
        }
    }
    
    // --- B. HERRAMIENTAS DE MODERACIÓN (RBAC ADMIN) ---
    // Validación estricta: Solo procesamos peticiones destructivas si el rol en sesión es 'admin'.
    
    // Accion: Purga de Tema Principal (ON DELETE CASCADE elimina las respuestas hijas).
    if (isset($_POST['borrar_tema']) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        $stmtBT = $conn->prepare("DELETE FROM foro_temas WHERE id = ?");
        $stmtBT->bind_param("i", $idTema);
        $stmtBT->execute();
        header("Location: /foro");
        exit();
    }

    // Accion: Purga de Respuesta Individual.
    if (isset($_POST['borrar_resp']) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        $idResp = intval($_POST['borrar_resp']);
        $stmtBR = $conn->prepare("DELETE FROM foro_respuestas WHERE id = ?");
        $stmtBR->bind_param("i", $idResp);
        $stmtBR->execute();
        header("Location: /foro/$slug");
        exit();
    }
}

// =========================================================================================
// 4. EXTRACCIÓN DE DATOS Y CONSTRUCCIÓN DE PAGINACIÓN (READ STATE)
// =========================================================================================

// Extraccion de la Entidad Padre (El Tema) con datos del autor.
$sqlTema = "SELECT t.*, u.nombre, u.foto, u.rol
            FROM foro_temas t
            JOIN usuarios u ON t.usuario_id = u.id
            WHERE t.id = ?";
$stmtTema = $conn->prepare($sqlTema);
$stmtTema->bind_param("i", $idTema);
$stmtTema->execute();
$tema = $stmtTema->get_result()->fetch_assoc();

// Fail-Fast: si el tema fue borrado concurrentemente por un admin.
if (!$tema) {
    echo "<div class='container py-5 text-center'><h1>Tema no encontrado o eliminado.</h1><a href='/foro' class='btn btn-iori mt-3'>Volver al Foro</a></div>";
    exit();
}

// Paginacion de respuestas.
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $resultados_por_pagina;

$stmtCountR = $conn->prepare("SELECT COUNT(id) as total FROM foro_respuestas WHERE tema_id = ?");
$stmtCountR->bind_param("i", $idTema);
$stmtCountR->execute();
$total_registros = $stmtCountR->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $resultados_por_pagina);

// Extraccion de respuestas correspondientes a la pagina actual.
$stmtResp = $conn->prepare("SELECT r.*, u.nombre, u.foto, u.rol
            FROM foro_respuestas r
            JOIN usuarios u ON r.usuario_id = u.id
            WHERE r.tema_id = ?
            ORDER BY r.fecha ASC
            LIMIT ? OFFSET ?");
$stmtResp->bind_param("iii", $idTema, $resultados_por_pagina, $offset);
$stmtResp->execute();
$respuestas = $stmtResp->get_result();

// Helper visual para clasificar taxonómicamente el tema en la Interfaz de Usuario
function badgeColor($cat) {
    switch($cat) {
        case 'Teorías': return 'bg-purple text-white'; 
        case 'Noticias': return 'bg-danger text-white';
        case 'Recomendaciones': return 'bg-success text-white';
        case 'Off-Topic': return 'bg-secondary text-white';
        default: return 'bg-iori text-white';
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-5 foro-main-container foro-thread-container">
    
    <div class="mb-4">
        <a href="/foro" class="text-decoration-none text-muted fw-bold hover-iori transition-colors">
            <i class="fas fa-arrow-left me-1"></i> Volver a las Discusiones
        </a>
    </div>

    <?php if($pagina_actual === 1): ?>
        <div class="card shadow-sm mb-5 border-0 rounded-4 foro-thread-header-card bg-white">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-start">
                <div>
                    <span class="badge <?php echo badgeColor($tema['categoria']); ?> mb-2 rounded-pill px-3 shadow-sm"><?php echo htmlspecialchars($tema['categoria']); ?></span>
                    <h2 class="mb-0 text-dark fw-bold foro-thread-title"><?php echo htmlspecialchars($tema['titulo']); ?></h2>
                </div>
                
                <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                    <form method="POST" class="d-inline ms-3 flex-shrink-0" onsubmit="return confirm('¿Estás seguro de borrar TODO este hilo de forma permanente?');">
                        <input type="hidden" name="borrar_tema" value="1">
                        <button type="submit" class="btn btn-sm btn-soft-danger rounded-pill px-3 fw-bold">
                            <i class="fas fa-trash-alt me-1"></i> Borrar Hilo
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="card-body px-4 pb-4">
                <div class="d-flex mb-4 align-items-center border-bottom pb-3 mt-3">
                    <?php 
                        // Fallback dinámico de Avatares hacia API Externa
                        $foto = !empty($tema['foto']) 
                            ? ((strpos($tema['foto'], 'http') === 0) ? $tema['foto'] : '/' . ltrim($tema['foto'], '/')) 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($tema['nombre']) . '&background=0D8A92&color=fff&size=60&font-size=0.4&bold=true'; 
                    ?>
                    <img src="<?php echo htmlspecialchars($foto); ?>" class="rounded-circle me-3 border border-2 border-light shadow-sm foro-avatar-md">
                    <div>
                        <strong class="d-block text-dark fs-5">
                            <?php echo htmlspecialchars($tema['nombre']); ?> 
                            <?php if($tema['rol'] === 'admin'): ?>
                                <span class="badge bg-danger ms-1 align-middle badge-micro">ADMIN</span>
                            <?php endif; ?>
                            <span class="badge bg-iori text-white ms-1 align-middle badge-micro">CREADOR</span>
                        </strong>
                        
                        <div class="text-muted small mt-1 fw-semibold">
                            <i class="far fa-calendar-alt me-1"></i> <?php echo date('d/m/Y H:i', strtotime($tema['fecha'])); ?>
                            <?php if(!empty($tema['fecha_edicion'])): ?>
                                <span class="fst-italic ms-2 text-secondary opacity-75">
                                    (Editado el <?php echo date('d/m/y H:i', strtotime($tema['fecha_edicion'])); ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card-text fs-5 foro-post-content break-word text-dark"><?php echo htmlspecialchars(trim($tema['contenido'])); ?></div>
                
                <?php if(isset($_SESSION['usuario']) && $_SESSION['usuario'] === $tema['nombre']): ?>
                    <div class="mt-4 pt-3 border-top text-end">
                        <a href="/foro/editar-tema/<?php echo $tema['id']; ?>" class="btn btn-sm btn-soft-secondary fw-bold rounded-pill px-3 transition-colors">
                            <i class="fas fa-pencil-alt me-1"></i> Editar Tema
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert bg-white border border-iori border-start-5 shadow-sm mb-5 rounded-4 p-3 d-flex align-items-center">
            <i class="fas fa-comments fa-2x text-iori me-3 opacity-75"></i>
            <div>
                <h5 class="mb-0 text-dark fw-bold"><?php echo htmlspecialchars($tema['titulo']); ?></h5>
                <span class="text-muted fs-6">Continuación de la página <?php echo $pagina_actual; ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center border-bottom border-2 border-dark pb-2 mb-4 mt-5">
        <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-reply text-iori me-2"></i>Respuestas</h4>
        <span class="badge bg-dark rounded-pill fs-6 px-3 shadow-sm"><?php echo $total_registros; ?> en total</span>
    </div>
    
    <?php if ($respuestas->num_rows > 0): ?>
        <div class="d-flex flex-column gap-3">
            <?php while($resp = $respuestas->fetch_assoc()): ?>
                <div class="card shadow-sm border-0 rounded-4 bg-white">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                            
                            <div class="d-flex align-items-center">
                                <?php 
                                    $fotoR = !empty($resp['foto']) 
                                        ? ((strpos($resp['foto'], 'http') === 0) ? $resp['foto'] : '/' . ltrim($resp['foto'], '/')) 
                                        : 'https://ui-avatars.com/api/?name=' . urlencode($resp['nombre']) . '&background=0D8A92&color=fff&size=50&font-size=0.4&bold=true'; 
                                ?>
                                <img src="<?php echo htmlspecialchars($fotoR); ?>" class="rounded-circle me-3 border border-2 border-light shadow-sm foro-avatar-sm">
                                <div>
                                    <span class="fw-bold text-dark fs-6">
                                        <?php echo htmlspecialchars($resp['nombre']); ?>
                                        <?php if($resp['rol'] === 'admin'): ?>
                                            <span class="badge bg-danger ms-1 align-middle badge-micro">ADMIN</span>
                                        <?php endif; ?>
                                        <?php if($resp['nombre'] === $tema['nombre']): ?>
                                            <span class="badge bg-iori text-white ms-1 align-middle badge-micro">CREADOR</span>
                                        <?php endif; ?>
                                    </span>
                                    <br>
                                    <small class="text-muted fw-semibold"><i class="far fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($resp['fecha'])); ?></small>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <?php if(isset($_SESSION['usuario']) && $_SESSION['usuario'] === $resp['nombre']): ?>
                                    <a href="/foro/editar-respuesta/<?php echo $resp['id']; ?>" class="btn btn-sm btn-soft-secondary rounded-circle btn-icon-circle transition-colors" title="Editar tu respuesta">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Borrar esta respuesta permanentemente?');">
                                        <input type="hidden" name="borrar_resp" value="<?php echo $resp['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-soft-danger rounded-circle btn-icon-circle" title="Borrar respuesta">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="text-dark foro-reply-text break-word"><?php echo htmlspecialchars(trim($resp['mensaje'])); ?></div>
                        
                        <?php if(!empty($resp['fecha_edicion'])): ?>
                            <div class="mt-3 text-muted fst-italic text-end" style="font-size: 0.8rem;">
                                (Editado el <?php echo date('d/m/y H:i', strtotime($resp['fecha_edicion'])); ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="alert bg-white text-center border-0 py-5 text-muted shadow-sm rounded-4 mt-3">
            <i class="far fa-comment-dots fa-3x mb-3 opacity-25"></i>
            <h5 class="fw-bold text-dark">No hay respuestas aún</h5>
            <p class="mb-0">¡Sé el primero en compartir tu opinión!</p>
        </div>
    <?php endif; ?>

    <?php if ($total_paginas > 1): ?>
        <nav aria-label="Paginación de respuestas" class="mt-5 mb-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link shadow-sm border-0 text-iori fw-bold" href="/foro/<?php echo $slug; ?>?pagina=<?php echo ($pagina_actual - 1); ?>" style="border-radius: 50px 0 0 50px;">
                        <i class="fas fa-chevron-left me-1"></i> Anterior
                    </a>
                </li>
                
                <?php for($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($pagina_actual == $i) ? 'active' : ''; ?>">
                        <a class="page-link shadow-sm border-0 <?php echo ($pagina_actual == $i) ? 'bg-iori text-white' : 'text-dark fw-semibold'; ?>" href="/foro/<?php echo $slug; ?>?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                    <a class="page-link shadow-sm border-0 text-iori fw-bold" href="/foro/<?php echo $slug; ?>?pagina=<?php echo ($pagina_actual + 1); ?>" style="border-radius: 0 50px 50px 0;">
                        Siguiente <i class="fas fa-chevron-right ms-1"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <div class="mt-5">
        <?php if(isset($_SESSION['usuario'])): ?>
            
            <?php if($estaSuspendido): ?>
                <div class="alert alert-danger text-center shadow-sm py-4 rounded-4 border border-danger">
                    <i class="fas fa-ban fa-2x mb-3 text-danger opacity-75 d-block"></i>
                    <h5 class="fw-bold text-danger">Participación Bloqueada</h5>
                    <span class="fs-6 text-muted">Tu cuenta se encuentra en modo Solo Lectura por infracciones de las normas de la comunidad.</span>
                    <small class="d-block mt-2 fw-bold text-danger">Podrás volver a participar el: <?php echo $fechaDesbloqueoStr; ?></small>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-0 rounded-4 bg-white">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3 fw-bold text-dark"><i class="fas fa-pencil-alt text-iori me-2"></i>Escribe una respuesta</h5>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <textarea name="respuesta" class="form-control bg-light shadow-sm border-light foro-textarea-style" rows="4" placeholder="Comparte tu punto de vista con la comunidad..." required></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-iori px-4 fw-bold shadow-sm rounded-pill">
                                    <i class="fas fa-paper-plane me-2"></i>Publicar Respuesta
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 bg-white mt-4">
                <div class="card-body text-center py-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-lock fa-2x text-muted opacity-50"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-2">Únete a la conversación</h4>
                    <p class="text-muted mb-4">Debes iniciar sesión para participar en la discusión y compartir tu opinión.</p>
                    <a href="/login" class="btn btn-iori btn-lg fw-bold rounded-pill px-5 shadow-sm">
                        <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

</main>

<?php include 'includes/footer.php'; ?>