<?php
session_start();
require 'includes/db.php';

// 1. Validar SLUG en lugar de ID
if (!isset($_GET['slug']) || empty($_GET['slug'])) {
    header("Location: /foro");
    exit();
}

$slug = $_GET['slug'];

// --- OBTENER EL ID DEL TEMA A PARTIR DEL SLUG ---
$sql_id = "SELECT id FROM foro_temas WHERE slug = ?";
$stmt_id = $conn->prepare($sql_id);
$stmt_id->bind_param("s", $slug);
$stmt_id->execute();
$res_id = $stmt_id->get_result();

if ($res_id->num_rows === 0) {
    header("Location: /404.php");
    exit();
}

$idTema = $res_id->fetch_assoc()['id'];
$resultados_por_pagina = 10; 
// --------------------------------------------------

// --- 1. COMPROBAR ESTADO DEL USUARIO Y SUSPENSIÓN ---
$estaSuspendido = false;
$fechaDesbloqueoStr = '';
$userId = null;

if (isset($_SESSION['usuario'])) {
    $nombreUser = $_SESSION['usuario'];
    $resUser = $conn->query("SELECT id, fecha_desbloqueo FROM usuarios WHERE nombre = '$nombreUser'");
    if ($resUser && $resUser->num_rows > 0) {
        $userData = $resUser->fetch_assoc();
        $userId = $userData['id']; 
        
        // Verificamos si la fecha de desbloqueo es mayor a la actual
        if (!empty($userData['fecha_desbloqueo']) && strtotime($userData['fecha_desbloqueo']) > time()) {
            $estaSuspendido = true;
            $fechaDesbloqueoStr = date('d/m/Y H:i', strtotime($userData['fecha_desbloqueo']));
        }
    }
}
// ----------------------------------------------------


// ---------------------------------------------------------
// 2. LÓGICA DE POST Y ACCIONES SEGURAS
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // A. PROCESAR NUEVA RESPUESTA
    if (isset($_POST['respuesta'])) {
        if ($userId) {
            
            // DEFENSA BACKEND: Si está suspendido, no procesamos la respuesta
            if ($estaSuspendido) {
                header("Location: /foro/$slug");
                exit();
            }

            $mensaje = trim($_POST['respuesta']);
            if (!empty($mensaje)) {
                $stmt = $conn->prepare("INSERT INTO foro_respuestas (tema_id, usuario_id, mensaje) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $idTema, $userId, $mensaje);
                $stmt->execute();
                
                // UX MAGIA: Redirigir a la última página al comentar
                $sqlCount = "SELECT COUNT(id) as total FROM foro_respuestas WHERE tema_id = $idTema";
                $total_resp = $conn->query($sqlCount)->fetch_assoc()['total'];
                $ultima_pagina = max(1, ceil($total_resp / $resultados_por_pagina));
                
                header("Location: /foro/$slug?pagina=$ultima_pagina");
                exit();
            }
        } else {
            header("Location: /login");
            exit();
        }
    }
    
    // B. BORRAR TEMA (Solo Admin - Seguro Anti-CSRF)
    if (isset($_POST['borrar_tema']) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        $conn->query("DELETE FROM foro_temas WHERE id = $idTema");
        header("Location: /foro");
        exit();
    }

    // C. BORRAR RESPUESTA (Solo Admin - Seguro Anti-CSRF)
    if (isset($_POST['borrar_resp']) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        $idResp = intval($_POST['borrar_resp']);
        $conn->query("DELETE FROM foro_respuestas WHERE id = $idResp");
        header("Location: /foro/$slug");
        exit();
    }
}

// ---------------------------------------------------------
// 3. CONSULTAS DE DATOS Y PAGINACIÓN
// ---------------------------------------------------------

$sqlTema = "SELECT t.*, u.nombre, u.foto, u.rol 
            FROM foro_temas t 
            JOIN usuarios u ON t.usuario_id = u.id 
            WHERE t.id = $idTema";
$resTema = $conn->query($sqlTema);
$tema = $resTema->fetch_assoc();

if (!$tema) {
    echo "<div class='container py-5 text-center'><h1>Tema no encontrado o eliminado.</h1><a href='/foro' class='btn btn-primary mt-3'>Volver al Foro</a></div>";
    exit();
}

$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $resultados_por_pagina;

$sqlTotalResp = "SELECT COUNT(id) as total FROM foro_respuestas WHERE tema_id = $idTema";
$resTotalResp = $conn->query($sqlTotalResp);
$total_registros = $resTotalResp->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $resultados_por_pagina);

$sqlResp = "SELECT r.*, u.nombre, u.foto, u.rol 
            FROM foro_respuestas r 
            JOIN usuarios u ON r.usuario_id = u.id 
            WHERE r.tema_id = $idTema 
            ORDER BY r.fecha ASC 
            LIMIT $resultados_por_pagina OFFSET $offset";
$respuestas = $conn->query($sqlResp);
?>

<?php include 'includes/header.php'; ?>

<main class="container py-5" style="max-width: 900px;">
    
    <div class="mb-3">
        <a href="/foro" class="text-decoration-none text-muted fw-bold">
            <i class="fas fa-arrow-left me-1"></i> Volver al Foro
        </a>
    </div>

    <?php if($pagina_actual === 1): ?>
        <div class="card shadow-sm mb-4 border-primary">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div>
                    <span class="badge bg-secondary mb-1"><?php echo htmlspecialchars($tema['categoria']); ?></span>
                    <h3 class="mb-0 text-primary fw-bold"><?php echo htmlspecialchars($tema['titulo']); ?></h3>
                </div>
                
                <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de borrar TODO este hilo?');">
                        <input type="hidden" name="borrar_tema" value="1">
                        <button type="submit" class="btn btn-sm btn-danger shadow-sm">
                            <i class="fas fa-trash-alt me-1"></i> Borrar Hilo
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="card-body">
                <div class="d-flex mb-3 align-items-center border-bottom pb-3">
                    <?php 
                        // Truco para rutas absolutas de imagen
                        $foto = !empty($tema['foto']) ? ((strpos($tema['foto'], 'http') === 0) ? $tema['foto'] : '/' . ltrim($tema['foto'], '/')) : 'https://via.placeholder.com/50'; 
                    ?>
                    <img src="<?php echo htmlspecialchars($foto); ?>" class="rounded-circle me-3 border" width="50" height="50" style="object-fit:cover;">
                    <div>
                        <strong class="d-block text-dark fs-5">
                            <?php echo htmlspecialchars($tema['nombre']); ?> 
                            <?php if($tema['rol'] === 'admin'): ?>
                                <span class="badge bg-danger ms-1" style="font-size:0.6em">ADMIN</span>
                            <?php endif; ?>
                        </strong>
                        
                        <div class="text-muted small">
                            <i class="far fa-calendar-alt me-1"></i> Publicado el <?php echo date('d/m/Y H:i', strtotime($tema['fecha'])); ?>
                            <?php if(!empty($tema['fecha_edicion'])): ?>
                                <span class="fst-italic ms-2 text-secondary">
                                    (Editado el <?php echo date('d/m/y H:i', strtotime($tema['fecha_edicion'])); ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card-text fs-5 py-2" style="white-space: pre-wrap; color: #333; line-height: 1.6;"><?php echo htmlspecialchars($tema['contenido']); ?></div>
                
                <?php if(isset($_SESSION['usuario']) && $_SESSION['usuario'] === $tema['nombre']): ?>
                    <div class="mt-3 text-end">
                        <a href="/editar_tema.php?id=<?php echo $tema['id']; ?>" class="btn btn-sm btn-outline-secondary fw-bold">
                            <i class="fas fa-pencil-alt me-1"></i> Editar Tema
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-light border border-primary border-start-4 shadow-sm mb-4">
            <h5 class="mb-0 text-primary fw-bold">
                <i class="fas fa-comments me-2"></i><?php echo htmlspecialchars($tema['titulo']); ?> 
                <span class="text-muted fs-6 fw-normal ms-2">(Página <?php echo $pagina_actual; ?>)</span>
            </h5>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center border-bottom border-2 border-secondary pb-2 mb-4">
        <h5 class="mb-0 fw-bold">Respuestas <span class="text-muted fs-6">(<?php echo $total_registros; ?>)</span></h5>
    </div>
    
    <?php if ($respuestas->num_rows > 0): ?>
        <?php while($resp = $respuestas->fetch_assoc()): ?>
            <div class="card mb-3 shadow-sm border-0 bg-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                        
                        <div class="d-flex align-items-center">
                            <?php 
                                $fotoR = !empty($resp['foto']) ? ((strpos($resp['foto'], 'http') === 0) ? $resp['foto'] : '/' . ltrim($resp['foto'], '/')) : 'https://via.placeholder.com/40'; 
                            ?>
                            <img src="<?php echo htmlspecialchars($fotoR); ?>" class="rounded-circle me-3 border" width="45" height="45" style="object-fit:cover;">
                            <div>
                                <span class="fw-bold text-dark fs-6">
                                    <?php echo htmlspecialchars($resp['nombre']); ?>
                                    <?php if($resp['rol'] === 'admin'): ?>
                                        <span class="badge bg-danger ms-1" style="font-size:0.6em">ADMIN</span>
                                    <?php endif; ?>
                                </span>
                                <br>
                                <small class="text-muted"><i class="far fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($resp['fecha'])); ?></small>
                            </div>
                        </div>

                        <div>
                            <?php if(isset($_SESSION['usuario']) && $_SESSION['usuario'] === $resp['nombre']): ?>
                                <a href="/editar_respuesta.php?id=<?php echo $resp['id']; ?>" class="btn btn-sm btn-light text-secondary me-1" title="Editar">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                            <?php endif; ?>

                            <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Borrar esta respuesta permanentemente?');">
                                    <input type="hidden" name="borrar_resp" value="<?php echo $resp['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger border-0" title="Borrar respuesta">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="text-dark" style="font-size: 1.05rem; line-height: 1.5; white-space: pre-wrap;">
                        <?php echo htmlspecialchars($resp['mensaje']); ?>
                        
                        <?php if(!empty($resp['fecha_edicion'])): ?>
                            <div class="mt-2 text-muted fst-italic" style="font-size: 0.8rem;">
                                (Editado el <?php echo date('d/m/y H:i', strtotime($resp['fecha_edicion'])); ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-light text-center border py-5 text-muted shadow-sm rounded-3">
            <i class="far fa-comment-dots fa-3x mb-3 opacity-25"></i>
            <h5>No hay respuestas aún</h5>
            <p class="mb-0">¡Sé el primero en compartir tu opinión!</p>
        </div>
    <?php endif; ?>

    <?php if ($total_paginas > 1): ?>
        <nav aria-label="Paginación de respuestas" class="mt-4 mb-5">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link shadow-sm" href="/foro/<?php echo $slug; ?>?pagina=<?php echo ($pagina_actual - 1); ?>">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                </li>
                
                <?php for($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($pagina_actual == $i) ? 'active' : ''; ?>">
                        <a class="page-link shadow-sm" href="/foro/<?php echo $slug; ?>?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                    <a class="page-link shadow-sm" href="/foro/<?php echo $slug; ?>?pagina=<?php echo ($pagina_actual + 1); ?>">
                        Siguiente <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <div class="mt-4">
        <?php if(isset($_SESSION['usuario'])): ?>
            
            <?php if($estaSuspendido): ?>
                <div class="alert alert-danger text-center shadow-sm py-4 border-danger border-top-4">
                    <i class="fas fa-ban fa-2x mb-3 text-danger opacity-75 d-block"></i>
                    <h5 class="fw-bold text-danger">Participación Bloqueada</h5>
                    <span class="fs-6 text-muted">Tu cuenta se encuentra en modo Solo Lectura por infracciones de las normas de la comunidad.</span>
                    <small class="d-block mt-2 fw-bold text-danger">Podrás volver a participar el: <?php echo $fechaDesbloqueoStr; ?></small>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-primary border-top-4">
                    <div class="card-body bg-white p-4">
                        <h5 class="card-title mb-3 fw-bold"><i class="fas fa-reply text-primary me-2"></i>Participar en la discusión</h5>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <textarea name="respuesta" class="form-control bg-light" rows="4" placeholder="Escribe aquí tu respuesta..." required></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                                    <i class="fas fa-paper-plane me-2"></i>Publicar Respuesta
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-secondary text-center shadow-sm py-4">
                <i class="fas fa-lock fa-2x mb-3 text-muted opacity-50 d-block"></i>
                <span class="fs-5">Debes <a href="/login" class="fw-bold text-primary text-decoration-none">iniciar sesión</a> para participar.</span>
            </div>
        <?php endif; ?>
    </div>

</main>

<?php include 'includes/footer.php'; ?>