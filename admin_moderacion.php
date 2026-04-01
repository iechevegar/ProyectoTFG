<?php
session_start();
require 'includes/db.php';

// SEGURIDAD: Solo Administradores
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// --- LÓGICA DE BORRADO ---
if (isset($_GET['borrar']) && isset($_GET['tipo']) && isset($_GET['id'])) {
    $tipo = $_GET['tipo'];
    $id = intval($_GET['id']);
    
    if ($tipo === 'resena') {
        $conn->query("DELETE FROM resenas WHERE id = $id");
    } elseif ($tipo === 'comentario') {
        $conn->query("DELETE FROM comentarios WHERE id = $id");
    } elseif ($tipo === 'foro_tema') {
        $conn->query("DELETE FROM foro_temas WHERE id = $id");
    } elseif ($tipo === 'foro_respuesta') {
        $conn->query("DELETE FROM foro_respuestas WHERE id = $id");
    }
    
    header("Location: admin_moderacion.php?msg=Contenido eliminado correctamente");
    exit();
}

// --- RECOPILAR TODA LA ACTIVIDAD DE LA WEB ---
$actividad = [];

// 1. Obtener Reseñas
$resResenas = $conn->query("SELECT r.id, r.texto, r.fecha, u.nombre as autor, o.titulo as origen 
                            FROM resenas r 
                            JOIN usuarios u ON r.usuario_id = u.id 
                            JOIN obras o ON r.obra_id = o.id");
while($row = $resResenas->fetch_assoc()) {
    $row['tipo'] = 'resena';
    $row['grupo_filtro'] = 'resenas'; // Para el filtro JS
    $row['etiqueta'] = 'Reseña en Obra';
    $row['color'] = 'warning';
    $actividad[] = $row;
}

// 2. Obtener Comentarios de Capítulos
$resComentarios = $conn->query("SELECT c.id, c.texto, c.fecha, u.nombre as autor, cap.titulo as origen 
                                FROM comentarios c 
                                JOIN usuarios u ON c.usuario_id = u.id 
                                JOIN capitulos cap ON c.capitulo_id = cap.id");
while($row = $resComentarios->fetch_assoc()) {
    $row['tipo'] = 'comentario';
    $row['grupo_filtro'] = 'comentarios'; // Para el filtro JS
    $row['etiqueta'] = 'Comentario en Cap.';
    $row['color'] = 'success';
    $actividad[] = $row;
}

// 3. Obtener Temas del Foro
$resTemas = $conn->query("SELECT t.id, t.contenido as texto, t.fecha, u.nombre as autor, t.titulo as origen 
                          FROM foro_temas t 
                          JOIN usuarios u ON t.usuario_id = u.id");
while($row = $resTemas->fetch_assoc()) {
    $row['tipo'] = 'foro_tema';
    $row['grupo_filtro'] = 'foro'; // Para el filtro JS
    $row['etiqueta'] = 'Tema en Foro';
    $row['color'] = 'primary';
    $actividad[] = $row;
}

// 4. Obtener Respuestas del Foro
$resRespuestas = $conn->query("SELECT r.id, r.mensaje as texto, r.fecha, u.nombre as autor, t.titulo as origen 
                               FROM foro_respuestas r 
                               JOIN usuarios u ON r.usuario_id = u.id 
                               JOIN foro_temas t ON r.tema_id = t.id");
while($row = $resRespuestas->fetch_assoc()) {
    $row['tipo'] = 'foro_respuesta';
    $row['grupo_filtro'] = 'foro'; // Para el filtro JS
    $row['etiqueta'] = 'Respuesta Foro';
    $row['color'] = 'info';
    $actividad[] = $row;
}

// --- ORDENAR TODO POR FECHA (De más nuevo a más viejo) ---
usort($actividad, function($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

// Limitar a los últimos 50 registros
$actividad = array_slice($actividad, 0, 50);

?>

<?php include 'includes/header.php'; ?>

<main class="container py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h2 class="fw-bold text-dark m-0"><i class="fas fa-shield-alt text-danger me-2"></i> Centro de Moderación</h2>
        <a href="admin.php" class="btn btn-outline-secondary btn-sm fw-bold">
            <i class="fas fa-arrow-left me-1"></i> Volver al Panel
        </a>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 bg-light p-3 rounded border">
        <p class="text-muted mb-3 mb-md-0 small">Mostrando los últimos 50 mensajes. Usa los filtros para revisar por secciones.</p>
        
        <div class="btn-group shadow-sm" role="group" id="botones-filtro">
            <button type="button" class="btn btn-dark active" onclick="filtrarTabla('todo', this)">Todo</button>
            <button type="button" class="btn btn-outline-dark" onclick="filtrarTabla('resenas', this)">Reseñas</button>
            <button type="button" class="btn btn-outline-dark" onclick="filtrarTabla('comentarios', this)">Comentarios</button>
            <button type="button" class="btn btn-outline-dark" onclick="filtrarTabla('foro', this)">Foro</button>
        </div>
    </div>

    <div class="card shadow-sm border-0 bg-white">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tabla-moderacion">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Fecha</th>
                            <th>Usuario</th>
                            <th>Sección / Origen</th>
                            <th style="width: 45%;">Mensaje</th>
                            <th class="text-end pe-4">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($actividad) > 0): ?>
                            <?php foreach($actividad as $item): ?>
                                <tr class="fila-registro" data-grupo="<?php echo $item['grupo_filtro']; ?>">
                                    <td class="ps-4 text-muted small" style="white-space: nowrap;">
                                        <?php echo date('d/m/y H:i', strtotime($item['fecha'])); ?>
                                    </td>
                                    
                                    <td class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($item['autor']); ?>
                                    </td>
                                    
                                    <td>
                                        <span class="badge bg-<?php echo $item['color']; ?> bg-opacity-10 text-<?php echo $item['color']; ?> border border-<?php echo $item['color']; ?> mb-1">
                                            <?php echo $item['etiqueta']; ?>
                                        </span>
                                        <div class="small text-muted text-truncate" style="max-width: 180px;" title="<?php echo htmlspecialchars($item['origen']); ?>">
                                            <i class="fas fa-reply fa-rotate-180 me-1"></i> <?php echo htmlspecialchars($item['origen']); ?>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <div class="bg-light p-2 rounded border-start border-3 border-<?php echo $item['color']; ?> text-secondary" style="font-size: 0.9rem; max-height: 80px; overflow-y: auto;">
                                            <?php echo nl2br(htmlspecialchars($item['texto'])); ?>
                                        </div>
                                    </td>
                                    
                                    <td class="text-end pe-4">
                                        <a href="admin_moderacion.php?borrar=1&tipo=<?php echo $item['tipo']; ?>&id=<?php echo $item['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('¿Eliminar este mensaje definitivamente?');" 
                                           title="Eliminar contenido">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="fila-vacia">
                                <td colspan="5" class="text-center py-5">
                                    <i class="fas fa-check-circle fa-3x text-success opacity-25 mb-3"></i>
                                    <h5 class="fw-bold text-secondary">Todo limpio</h5>
                                    <p class="text-muted mb-0">No hay actividad reciente de los usuarios.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr id="fila-no-resultados" style="display: none;">
                            <td colspan="5" class="text-center py-5">
                                <i class="fas fa-filter fa-2x text-muted opacity-25 mb-3"></i>
                                <p class="text-muted mb-0">No hay mensajes en esta categoría.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<style>
    td div::-webkit-scrollbar { width: 4px; }
    td div::-webkit-scrollbar-track { background: transparent; }
    td div::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
</style>

<script>
    // Lógica para filtrar la tabla instantáneamente
    function filtrarTabla(categoria, botonClicado) {
        // 1. Cambiar el estilo de los botones (Activar el pulsado, desactivar los demás)
        const botones = document.getElementById('botones-filtro').getElementsByTagName('button');
        for (let btn of botones) {
            btn.classList.remove('btn-dark', 'active');
            btn.classList.add('btn-outline-dark');
        }
        botonClicado.classList.remove('btn-outline-dark');
        botonClicado.classList.add('btn-dark', 'active');

        // 2. Filtrar las filas de la tabla
        const filas = document.querySelectorAll('.fila-registro');
        let contadorVisibles = 0;

        filas.forEach(fila => {
            const grupoFila = fila.getAttribute('data-grupo');
            
            if (categoria === 'todo' || grupoFila === categoria) {
                fila.style.display = ''; // Mostrar
                contadorVisibles++;
            } else {
                fila.style.display = 'none'; // Ocultar
            }
        });

        // 3. Mostrar mensaje si el filtro deja la tabla vacía
        const filaNoResultados = document.getElementById('fila-no-resultados');
        if (contadorVisibles === 0 && filas.length > 0) {
            filaNoResultados.style.display = '';
        } else {
            filaNoResultados.style.display = 'none';
        }
    }
</script>

<?php include 'includes/footer.php'; ?>