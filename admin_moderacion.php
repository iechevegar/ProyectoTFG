<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. CONTROL DE ACCESO BASADO EN ROLES (RBAC)
// =========================================================================================
// Protegemos el panel de moderación bloqueando el acceso a cualquier usuario
// que no tenga explícitamente el rol de 'admin' en la sesión actual.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /");
    exit();
}

// =========================================================================================
// 2. MANEJADOR DE PETICIONES POST (ELIMINACIÓN SEGURA)
// =========================================================================================
// Procesamos las solicitudes de borrado de contenido de forma unificada.
// Exigimos método POST para prevenir vulnerabilidades CSRF y borrados accidentales vía GET.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrar']) && isset($_POST['tipo']) && isset($_POST['id'])) {
    $tipo = $_POST['tipo'];
    $id = intval($_POST['id']);
    
    // Enrutamos la consulta de borrado hacia la tabla correspondiente según el origen del reporte
    if ($tipo === 'resena') {
        $conn->query("DELETE FROM resenas WHERE id = $id");
    } elseif ($tipo === 'comentario') {
        $conn->query("DELETE FROM comentarios WHERE id = $id");
    } elseif ($tipo === 'foro_tema') {
        $conn->query("DELETE FROM foro_temas WHERE id = $id");
    } elseif ($tipo === 'foro_respuesta') {
        $conn->query("DELETE FROM foro_respuestas WHERE id = $id");
    }
    
    // Patrón PRG (Post/Redirect/Get) para evitar el reenvío del formulario al recargar la página
    header("Location: /admin_moderacion.php?msg=Contenido eliminado correctamente");
    exit();
}

// =========================================================================================
// 3. MOTOR DE PAGINACIÓN Y CONSOLIDACIÓN DE DATOS (UNION ALL)
// =========================================================================================
$resultados_por_pagina = 15;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $resultados_por_pagina;

// Para construir un "Feed de Actividad" global sin saturar el servidor con múltiples consultas,
// empleamos una macro-consulta SQL utilizando UNION ALL. Esto nos permite extraer, normalizar 
// y combinar registros de 4 tablas distintas (reseñas, comentarios, temas y respuestas) 
// proyectándolos sobre una estructura de columnas común.
$sql_base = "
    (SELECT r.id, r.texto, r.fecha, u.nombre as autor, u.foto as autor_foto, o.titulo as origen, o.slug as origen_slug, 'resena' as tipo, 'resenas' as grupo_filtro, 'Reseña' as etiqueta, 'warning' as color_class, 'fa-star' as icono, CONCAT('/obra/', o.slug) as url_origen 
     FROM resenas r JOIN usuarios u ON r.usuario_id = u.id JOIN obras o ON r.obra_id = o.id)
    UNION ALL
    (SELECT c.id, c.texto, c.fecha, u.nombre as autor, u.foto as autor_foto, cap.titulo as origen, cap.slug as origen_slug, 'comentario' as tipo, 'comentarios' as grupo_filtro, 'Comentario' as etiqueta, 'success' as color_class, 'fa-comment-alt' as icono, CONCAT('/obra/', o.slug, '/', cap.slug) as url_origen 
     FROM comentarios c JOIN usuarios u ON c.usuario_id = u.id JOIN capitulos cap ON c.capitulo_id = cap.id JOIN obras o ON cap.obra_id = o.id)
    UNION ALL
    (SELECT t.id, t.contenido as texto, t.fecha, u.nombre as autor, u.foto as autor_foto, t.titulo as origen, t.slug as origen_slug, 'foro_tema' as tipo, 'foro' as grupo_filtro, 'Tema Foro' as etiqueta, 'primary' as color_class, 'fa-comments' as icono, CONCAT('/foro/', t.slug) as url_origen 
     FROM foro_temas t JOIN usuarios u ON t.usuario_id = u.id)
    UNION ALL
    (SELECT rs.id, rs.mensaje as texto, rs.fecha, u.nombre as autor, u.foto as autor_foto, t.titulo as origen, t.slug as origen_slug, 'foro_respuesta' as tipo, 'foro' as grupo_filtro, 'Respuesta' as etiqueta, 'info' as color_class, 'fa-reply' as icono, CONCAT('/foro/', t.slug) as url_origen 
     FROM foro_respuestas rs JOIN usuarios u ON rs.usuario_id = u.id JOIN foro_temas t ON rs.tema_id = t.id)
";

// Cálculo del volumen total de registros para generar la paginación dinámica
$sql_count = "SELECT COUNT(*) as total FROM ($sql_base) as total_actividad";
$res_count = $conn->query($sql_count);
$total_registros = $res_count->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $resultados_por_pagina);

// Extracción final aplicando el orden cronológico descendente y los límites de paginación
$sql_final = "SELECT * FROM ($sql_base) as data_final ORDER BY fecha DESC LIMIT $resultados_por_pagina OFFSET $offset";
$res_actividad = $conn->query($sql_final);

// Volcamos el resultset en un array en memoria para poder consumirlo 
// independientemente en la vista de escritorio y en la vista móvil.
$actividad = [];
if ($res_actividad && $res_actividad->num_rows > 0) {
    while($row = $res_actividad->fetch_assoc()) {
        $actividad[] = $row;
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-5 admin-main-container">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <a href="/admin" class="text-decoration-none text-muted mb-2 d-inline-block fw-bold hover-iori transition-colors">
                <i class="fas fa-arrow-left me-1"></i> Volver al Panel
            </a>
            <h2 class="fw-bold text-dark m-0"><i class="fas fa-shield-alt text-iori me-2"></i> Centro de Moderación</h2>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-4 border-start border-4 border-success bg-white">
            <i class="fas fa-check-circle me-2 text-success"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
            
            <div class="text-muted small mb-3 mb-md-0 fw-semibold ps-2">
                <i class="fas fa-history me-1 text-iori"></i> <strong>Historial de Actividad:</strong> Explorando la página <?php echo $pagina_actual; ?> de <?php echo max(1, $total_paginas); ?>.
            </div>
            
            <div class="btn-group shadow-sm p-1 bg-light rounded-pill flex-wrap" role="group" id="botones-filtro">
                <button type="button" class="btn btn-dark active rounded-pill px-4" onclick="filtrarTabla('todo', this)">Todo</button>
                <button type="button" class="btn text-dark border-0 rounded-pill px-3" onclick="filtrarTabla('resenas', this)">Reseñas</button>
                <button type="button" class="btn text-dark border-0 rounded-pill px-3" onclick="filtrarTabla('comentarios', this)">Comentarios</button>
                <button type="button" class="btn text-dark border-0 rounded-pill px-3" onclick="filtrarTabla('foro', this)">Foro</button>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden bg-white mb-5 d-none d-md-block">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 admin-table-hover" id="tabla-moderacion">
                <thead class="text-secondary small text-uppercase bg-light">
                    <tr>
                        <th class="ps-4 py-3 border-0">Fecha / Usuario</th>
                        <th class="py-3 border-0">Sección / Origen</th>
                        <th class="py-3 border-0" style="width: 45%;">Mensaje</th>
                        <th class="text-end pe-4 py-3 border-0">Acción</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php if (count($actividad) > 0): ?>
                        <?php foreach($actividad as $item): ?>
                            <?php 
                                // Resolución de avatares con fallback dinámico mediante API externa
                                $foto = !empty($item['autor_foto']) 
                                    ? ((strpos($item['autor_foto'], 'http') === 0) ? $item['autor_foto'] : '/' . ltrim($item['autor_foto'], '/')) 
                                    : 'https://ui-avatars.com/api/?name=' . urlencode($item['autor']) . '&background=0D8A92&color=fff&size=40&bold=true'; 
                            ?>
                            <tr class="fila-registro" data-grupo="<?php echo $item['grupo_filtro']; ?>">
                                <td class="ps-4 py-4 border-light">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($foto); ?>" class="rounded-circle me-3 border border-2 border-light shadow-sm avatar-admin-list">
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['autor']); ?></div>
                                            <div class="text-muted x-small"><?php echo date('d/m/y H:i', strtotime($item['fecha'])); ?></div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="py-4 border-light">
                                    <span class="badge bg-<?php echo $item['color_class']; ?> bg-opacity-10 text-<?php echo $item['color_class']; ?> border border-<?php echo $item['color_class']; ?> mb-1 rounded-pill px-3">
                                        <i class="fas <?php echo $item['icono']; ?> me-1"></i> <?php echo $item['etiqueta']; ?>
                                    </span>
                                    <div class="mt-1">
                                        <a href="<?php echo $item['url_origen']; ?>" target="_blank" class="text-decoration-none small text-muted text-truncate d-inline-block hover-iori fw-semibold" style="max-width: 180px;" title="Ir a: <?php echo htmlspecialchars($item['origen']); ?>">
                                            <i class="fas fa-external-link-alt me-1 opacity-50" style="font-size: 0.7rem;"></i> <?php echo htmlspecialchars($item['origen']); ?>
                                        </a>
                                    </div>
                                </td>
                                
                                <td class="py-4 border-light">
                                    <div class="bg-light p-3 rounded-4 border-start border-4 border-<?php echo $item['color_class']; ?> text-secondary shadow-sm" style="font-size: 0.95rem; max-height: 120px; overflow-y: auto; line-height: 1.5;">
                                        <?php echo nl2br(htmlspecialchars($item['texto'])); ?>
                                    </div>
                                </td>
                                
                                <td class="text-end pe-4 py-4 border-light">
                                    <form action="/admin_moderacion.php" method="POST" class="d-flex justify-content-end" onsubmit="return confirm('¿Eliminar este contenido permanentemente?');">
                                        <input type="hidden" name="borrar" value="1">
                                        <input type="hidden" name="tipo" value="<?php echo $item['tipo']; ?>">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        
                                        <button type="submit" class="btn btn-outline-danger shadow-sm d-inline-flex align-items-center justify-content-center hover-trash" title="Borrar definitivamente" style="width: 38px; height: 38px; border-radius: 50%; padding: 0; border: 1px solid #dc3545;">
                                            <i class="fas fa-trash-alt m-0 p-0"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="fila-vacia">
                            <td colspan="4" class="text-center py-5 bg-white">
                                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 admin-empty-state-icon">
                                    <i class="fas fa-check-circle fa-2x text-success opacity-25"></i>
                                </div>
                                <h5 class="fw-bold text-secondary">No hay actividad en esta página</h5>
                                <p class="text-muted mb-0">Todo limpio.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                    <tr class="fila-no-resultados" style="display: none;">
                        <td colspan="4" class="text-center py-5 bg-white">
                            <i class="fas fa-filter fa-2x text-muted opacity-25 mb-3"></i>
                            <p class="text-muted mb-0">No se han encontrado mensajes en este filtro (en la página actual).</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-md-none d-flex flex-column gap-3 mb-5">
        <?php if (count($actividad) > 0): ?>
            <?php foreach($actividad as $item): ?>
                <?php 
                    $foto = !empty($item['autor_foto']) 
                        ? ((strpos($item['autor_foto'], 'http') === 0) ? $item['autor_foto'] : '/' . ltrim($item['autor_foto'], '/')) 
                        : 'https://ui-avatars.com/api/?name=' . urlencode($item['autor']) . '&background=0D8A92&color=fff&size=40&bold=true'; 
                ?>
                <div class="card shadow-sm border-0 rounded-4 bg-white fila-registro" data-grupo="<?php echo $item['grupo_filtro']; ?>">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center">
                                <img src="<?php echo htmlspecialchars($foto); ?>" class="rounded-circle me-2 border border-2 border-light shadow-sm" width="35" height="35" style="object-fit:cover;">
                                <div>
                                    <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?php echo htmlspecialchars($item['autor']); ?></div>
                                    <div class="text-muted x-small"><?php echo date('d/m/y H:i', strtotime($item['fecha'])); ?></div>
                                </div>
                            </div>
                            <span class="badge bg-<?php echo $item['color_class']; ?> bg-opacity-10 text-<?php echo $item['color_class']; ?> border border-<?php echo $item['color_class']; ?> rounded-pill px-2 py-1" style="font-size: 0.65rem;">
                                <i class="fas <?php echo $item['icono']; ?> me-1"></i> <?php echo $item['etiqueta']; ?>
                            </span>
                        </div>

                        <div class="bg-light p-3 rounded-4 border-start border-4 border-<?php echo $item['color_class']; ?> text-secondary shadow-sm mb-3" style="font-size: 0.9rem; max-height: 120px; overflow-y: auto; line-height: 1.4;">
                            <?php echo nl2br(htmlspecialchars($item['texto'])); ?>
                        </div>

                        <div class="d-flex flex-column gap-2 border-top border-light pt-3">
                            <a href="<?php echo $item['url_origen']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold shadow-sm">
                                <i class="fas fa-external-link-alt me-1"></i> Ver Contexto
                            </a>
                            <form action="/admin_moderacion.php" method="POST" class="d-flex" onsubmit="return confirm('¿Eliminar este contenido permanentemente?');">
                                <input type="hidden" name="borrar" value="1">
                                <input type="hidden" name="tipo" value="<?php echo $item['tipo']; ?>">
                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger w-100 rounded-pill fw-bold shadow-sm">
                                    <i class="fas fa-trash-alt me-1"></i> Eliminar Contenido
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="card shadow-sm border-0 rounded-4 bg-white fila-no-resultados" style="display: none;">
                <div class="card-body text-center py-4">
                    <i class="fas fa-filter fa-2x text-muted opacity-25 mb-3"></i>
                    <p class="text-muted mb-0 small">No se han encontrado mensajes en este filtro (en la página actual).</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0 rounded-4 bg-white" id="fila-vacia-movil">
                <div class="card-body text-center py-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 admin-empty-state-icon">
                        <i class="fas fa-check-circle fa-2x text-success opacity-25"></i>
                    </div>
                    <h5 class="fw-bold text-secondary">No hay actividad</h5>
                    <p class="text-muted mb-0 small">Todo limpio.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($total_paginas > 1): ?>
        <nav aria-label="Navegación de páginas" class="pb-4">
            <ul class="pagination justify-content-center mb-0">
                <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link shadow-sm border-0 fw-bold <?php echo ($pagina_actual <= 1) ? 'text-muted' : 'text-iori'; ?>" 
                        href="?pagina=<?php echo ($pagina_actual - 1); ?>" 
                        style="border-radius: 50px 0 0 50px; padding: 10px 20px;">
                        <i class="fas fa-chevron-left me-1"></i> Anterior
                    </a>
                </li>
                
                <?php 
                $inicio = max(1, $pagina_actual - 2);
                $fin = min($total_paginas, $pagina_actual + 2);
                
                if($inicio > 1) {
                    echo '<li class="page-item"><a class="page-link shadow-sm border-0 fw-bold text-dark" href="?pagina=1">1</a></li>';
                    if($inicio > 2) echo '<li class="page-item disabled"><span class="page-link border-0 shadow-sm bg-light text-muted">...</span></li>';
                }

                for($i = $inicio; $i <= $fin; $i++): ?>
                    <li class="page-item <?php echo ($pagina_actual == $i) ? 'active' : ''; ?>">
                        <a class="page-link shadow-sm border-0 fw-bold <?php echo ($pagina_actual == $i) ? 'bg-iori text-white' : 'text-dark'; ?>" 
                            href="?pagina=<?php echo $i; ?>" style="padding: 10px 16px;">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; 
                
                if($fin < $total_paginas) {
                    if($fin < $total_paginas - 1) echo '<li class="page-item disabled"><span class="page-link border-0 shadow-sm bg-light text-muted">...</span></li>';
                    echo '<li class="page-item"><a class="page-link shadow-sm border-0 fw-bold text-dark" href="?pagina='.$total_paginas.'">'.$total_paginas.'</a></li>';
                }
                ?>
                
                <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                    <a class="page-link shadow-sm border-0 fw-bold <?php echo ($pagina_actual >= $total_paginas) ? 'text-muted' : 'text-iori'; ?>" 
                        href="?pagina=<?php echo ($pagina_actual + 1); ?>" 
                        style="border-radius: 0 50px 50px 0; padding: 10px 20px;">
                        Siguiente <i class="fas fa-chevron-right ms-1"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

</main>

<script>
    // Lógica de filtrado reactivo (CSR).
    // Oculta/Muestra nodos del DOM según la propiedad data-grupo inyectada por PHP,
    // ahorrando peticiones HTTP innecesarias al servidor.
    function filtrarTabla(categoria, botonClicado) {
        const botones = document.querySelectorAll('#botones-filtro button');
        botones.forEach(btn => {
            btn.classList.remove('btn-dark', 'active');
            btn.classList.add('text-dark');
        });
        
        botonClicado.classList.remove('text-dark');
        botonClicado.classList.add('btn-dark', 'active');

        // El filtro es universal: afecta simultáneamente al layout de escritorio y móvil
        const filas = document.querySelectorAll('.fila-registro');
        let contadorVisibles = 0;

        filas.forEach(fila => {
            const grupoFila = fila.getAttribute('data-grupo');
            if (categoria === 'todo' || grupoFila === categoria) {
                fila.style.display = ''; 
                contadorVisibles++;
            } else {
                fila.style.display = 'none'; 
            }
        });

        // Toggle del componente Empty State si el filtro activo no arroja coincidencias
        const filasNoResultados = document.querySelectorAll('.fila-no-resultados');
        const filaVaciaPC = document.getElementById('fila-vacia');
        const filaVaciaMovil = document.getElementById('fila-vacia-movil');
        
        if (filaVaciaPC || filaVaciaMovil) return;

        if (contadorVisibles === 0 && filas.length > 0) {
            filasNoResultados.forEach(el => el.style.display = '');
        } else {
            filasNoResultados.forEach(el => el.style.display = 'none');
        }
    }
</script>

<?php include 'includes/footer.php'; ?>