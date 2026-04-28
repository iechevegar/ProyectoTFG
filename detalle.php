<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. RESOLUCIÓN DE RUTAS AMIGABLES (SLUG)
// =========================================================================================
if (!isset($_GET['slug']) || empty($_GET['slug'])) {
    header("Location: /");
    exit();
}

$slug = $_GET['slug'];

// Traducimos el SLUG público al ID interno para poder realizar las consultas relacionales
$sql_id = "SELECT id FROM obras WHERE slug = ?";
$stmt_id = $conn->prepare($sql_id);
$stmt_id->bind_param("s", $slug);
$stmt_id->execute();
$res_id = $stmt_id->get_result();

if ($res_id->num_rows === 0) {
    header("Location: /404.php");
    exit();
}

$idObra = $res_id->fetch_assoc()['id'];
$datos_obra = null;

// =========================================================================================
// 2. CONTROL DE ACCESO Y SUSPENSIONES (BANEOS)
// =========================================================================================
$estaSuspendido = false;
$fechaDesbloqueoStr = '';
$userId = null;

if (isset($_SESSION['usuario'])) {
    $estadoUser = get_estado_usuario($conn);
    $userId = $estadoUser['id'];
    $estaSuspendido = $estadoUser['suspendido'];
    $fechaDesbloqueoStr = $estadoUser['hasta'] ?? '';
}

// =========================================================================================
// 3. ANALÍTICAS: CONTADOR DE VISITAS
// =========================================================================================
$conn->query("UPDATE obras SET visitas = visitas + 1 WHERE id = " . (int)$idObra);

// =========================================================================================
// 4. PROCESAMIENTO DE VALORACIONES Y RESEÑAS (POST)
// =========================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. LÓGICA DE INSERCIÓN DE RESEÑA
    if (isset($_POST['texto_resena'])) {
        if (isset($_SESSION['usuario'])) {
            if ($estaSuspendido) {
                header("Location: /obra/$slug");
                exit();
            }

            $texto = trim($_POST['texto_resena']);
            $puntuacion = isset($_POST['puntuacion']) ? intval($_POST['puntuacion']) : 1;

            if (!empty($texto) && $userId) {
                $stmt = $conn->prepare("INSERT INTO resenas (usuario_id, obra_id, texto, puntuacion) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iisi", $userId, $idObra, $texto, $puntuacion);
                if ($stmt->execute()) {
                    header("Location: /obra/$slug");
                    exit();
                }
            }
        } else {
            header("Location: /login");
            exit();
        }
    }

    // B. LÓGICA DE MODERACIÓN (ELIMINACIÓN DE RESEÑAS)
    if (isset($_POST['borrar_resena']) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        csrf_verify("/obra/$slug");
        $idResena = intval($_POST['borrar_resena']);
        $stmtDel = $conn->prepare("DELETE FROM resenas WHERE id = ?");
        $stmtDel->bind_param("i", $idResena);
        $stmtDel->execute();
        header("Location: /obra/$slug");
        exit();
    }
}

// =========================================================================================
// 5. EXTRACCIÓN DE METADATOS DE LA OBRA Y TRACKING DE LECTURA
// =========================================================================================
$sql = "SELECT o.*, 
        (SELECT GROUP_CONCAT(g.nombre SEPARATOR ', ') FROM obra_genero og JOIN generos g ON og.genero_id = g.id WHERE og.obra_id = o.id) as generos_concat
        FROM obras o WHERE o.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idObra);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $datos_obra = $resultado->fetch_assoc();
    $datos_obra['generos'] = !empty($datos_obra['generos_concat']) ? array_map('trim', explode(',', $datos_obra['generos_concat'])) : [];

    $stmtNota = $conn->prepare("SELECT AVG(puntuacion) as media, COUNT(id) as total_votos FROM resenas WHERE obra_id = ?");
    $stmtNota->bind_param("i", $idObra);
    $stmtNota->execute();
    $resNota = $stmtNota->get_result()->fetch_assoc();
    $datos_obra['nota_media'] = $resNota['media'] ? round($resNota['media'], 1) : 0;
    $datos_obra['total_votos'] = $resNota['total_votos'];

    $es_favorito = false;
    $usuario_logueado = isset($_SESSION['usuario']);
    $rol_usuario = isset($_SESSION['rol']) ? $_SESSION['rol'] : 'invitado';

    if ($usuario_logueado && $userId) {
        $stmtFav = $conn->prepare("SELECT id FROM favoritos WHERE usuario_id = ? AND obra_id = ?");
        $stmtFav->bind_param("ii", $userId, $idObra);
        $stmtFav->execute();
        $es_favorito = $stmtFav->get_result()->num_rows > 0;

        $capitulos_leidos = [];
        $max_cap_tocado = 0;

        $stmtLeidos = $conn->prepare("SELECT cl.capitulo_id, cl.ultima_pagina FROM capitulos_leidos cl JOIN capitulos c ON cl.capitulo_id = c.id WHERE cl.usuario_id = ? AND c.obra_id = ?");
        $stmtLeidos->bind_param("ii", $userId, $idObra);
        $stmtLeidos->execute();
        $resLeidos = $stmtLeidos->get_result();

        while ($row = $resLeidos->fetch_assoc()) {
            $capitulos_leidos[$row['capitulo_id']] = $row['ultima_pagina'];
            if ($row['capitulo_id'] > $max_cap_tocado) {
                $max_cap_tocado = $row['capitulo_id'];
            }
        }
        $datos_obra['leidos_por_usuario'] = $capitulos_leidos;
        $datos_obra['max_cap_tocado'] = $max_cap_tocado;
    }

    $stmtCaps = $conn->prepare("SELECT id, titulo, slug, fecha_subida, contenido FROM capitulos WHERE obra_id = ? ORDER BY id ASC");
    $stmtCaps->bind_param("i", $idObra);
    $stmtCaps->execute();
    $resCaps = $stmtCaps->get_result();
    $capitulos = [];
    while ($cap = $resCaps->fetch_assoc()) {
        $imagenes = json_decode($cap['contenido'], true);
        $cap['total_paginas'] = is_array($imagenes) ? count($imagenes) : 0;
        unset($cap['contenido']);
        $capitulos[] = $cap;
    }
    $datos_obra['capitulos'] = $capitulos;
}

// =========================================================================================
// 6. RENDERIZADO DE OPINIONES
// =========================================================================================
$stmtResenas = $conn->prepare("SELECT r.*, u.nombre, u.foto, u.rol FROM resenas r JOIN usuarios u ON r.usuario_id = u.id WHERE r.obra_id = ? ORDER BY r.fecha DESC");
$stmtResenas->bind_param("i", $idObra);
$stmtResenas->execute();
$lista_resenas = $stmtResenas->get_result();

function pintarEstrellas($nota)
{
    $html = '';
    $nota_red = round($nota);
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $nota_red)
            $html .= '<i class="fas fa-star text-warning detalle-star-small"></i>';
        else
            $html .= '<i class="far fa-star text-warning opacity-50 detalle-star-small"></i>';
    }
    return $html;
}

include 'includes/header.php';
?>

<main class="detalle-main pb-5">

    <div class="container-fluid container-lg mt-3">
        <a href="/" class="text-decoration-none text-muted fw-bold hover-iori transition-colors">
            <i class="fas fa-arrow-left"></i> Volver al catálogo
        </a>
    </div>

    <div id="detalle-contenido">
        <div class="detalle-loading">
            <i class="fas fa-spinner fa-spin fa-2x text-iori"></i> Cargando datos de la obra...
        </div>
    </div>

    <div class="container-fluid container-lg mt-5 pt-4 border-top">
        <h3 class="mb-4 fw-bold"><i class="fas fa-comment-alt text-iori me-2"></i>Reseñas y Opiniones</h3>
        <div class="row">
            <div class="col-12">
                <?php if ($usuario_logueado && $rol_usuario !== 'admin'): ?>
                    <?php if ($estaSuspendido): ?>
                        <div class="alert alert-danger text-center shadow-sm py-4 border-danger border-top-4 mb-4">
                            <i class="fas fa-ban fa-2x mb-3 text-danger opacity-75 d-block"></i>
                            <h5 class="fw-bold text-danger">Participación Bloqueada</h5>
                            <span class="fs-6 text-muted">Tu cuenta se encuentra en modo Solo Lectura por infracciones a la
                                normativa comunitaria.</span>
                            <small class="d-block mt-2 fw-bold text-danger">Podrás volver a publicar reseñas el:
                                <?php echo $fechaDesbloqueoStr; ?></small>
                        </div>
                    <?php else: ?>
                        <div class="card mb-4 border shadow-sm rounded-4 bg-body">
                            <div class="card-body p-4">
                                <form method="POST" action="">
                                    <?php echo csrf_field(); ?>
                                    <label class="form-label fw-bold mb-0">Deja tu valoración:</label>
                                    <div class="d-block mb-1">
                                        <div class="clasificacion">
                                            <input id="radio5" type="radio" name="puntuacion" value="5">
                                            <label for="radio5"><i class="fas fa-star"></i></label>
                                            <input id="radio4" type="radio" name="puntuacion" value="4">
                                            <label for="radio4"><i class="fas fa-star"></i></label>
                                            <input id="radio3" type="radio" name="puntuacion" value="3">
                                            <label for="radio3"><i class="fas fa-star"></i></label>
                                            <input id="radio2" type="radio" name="puntuacion" value="2">
                                            <label for="radio2"><i class="fas fa-star"></i></label>
                                            <input id="radio1" type="radio" name="puntuacion" value="1" checked>
                                            <label for="radio1"><i class="fas fa-star"></i></label>
                                        </div>
                                    </div>
                                    <textarea name="texto_resena" class="form-control mb-3 shadow-sm" rows="3"
                                        placeholder="¿Qué te ha parecido el desarrollo de la trama?" required
                                        style="border-radius: 12px;"></textarea>
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <small class="text-muted fw-semibold"><i class="fas fa-info-circle me-1 text-iori"></i>
                                            Mantén el respeto en la comunidad. Avisa si incluyes spoilers.</small>
                                        <button type="submit"
                                            class="btn btn-iori fw-bold px-4 rounded-pill shadow-sm mt-2 mt-sm-0">Publicar
                                            Opinión</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php elseif (!$usuario_logueado): ?>
                    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-body">
                        <div class="card-body text-center py-5">
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                style="width: 80px; height: 80px;">
                                <i class="fas fa-lock fa-2x text-muted opacity-50"></i>
                            </div>
                            <h4 class="fw-bold mb-2">¿Quieres dejar tu valoración?</h4>
                            <p class="text-muted mb-4">Únete a la plataforma para puntuar esta obra y debatir con la
                                comunidad.</p>
                            <a href="/login" class="btn btn-iori btn-lg fw-bold rounded-pill px-5 shadow-sm">
                                <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($lista_resenas->num_rows > 0): ?>
                    <div class="row row-cols-1 row-cols-lg-2 g-4 align-items-start">
                        <?php while ($res = $lista_resenas->fetch_assoc()): ?>
                            <div class="col">
                                <div class="card border-0 shadow-sm review-card rounded-4 bg-body">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-start mb-3 border-bottom pb-3">
                                            <div class="flex-shrink-0">
                                                <?php $foto = !empty($res['foto']) ? ((strpos($res['foto'], 'http') === 0) ? $res['foto'] : '/' . ltrim($res['foto'], '/')) : 'https://via.placeholder.com/50'; ?>
                                                <img src="<?php echo htmlspecialchars($foto); ?>"
                                                    class="rounded-circle border border-2 border-light shadow-sm review-avatar"
                                                    width="55" height="55">
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <h6 class="mb-0 fw-bold fs-5">
                                                        <?php echo htmlspecialchars($res['nombre']); ?>
                                                        <?php if ($res['rol'] === 'admin'): ?>
                                                            <span
                                                                class="badge bg-danger ms-2 align-middle badge-admin-small">ADMIN</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <small class="text-muted fw-semibold">
                                                        <i
                                                            class="far fa-calendar-alt me-1"></i><?php echo date('d/m/Y', strtotime($res['fecha'])); ?>

                                                        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                                            <form method="POST" class="d-inline ms-2"
                                                                onsubmit="return confirm('¿Eliminar definitivamente este registro de la base de datos?');">
                                                                <input type="hidden" name="borrar_resena"
                                                                    value="<?php echo $res['id']; ?>">
                                                                <button type="submit"
                                                                    class="btn btn-sm btn-outline-danger p-1 ms-1 lh-1 btn-trash-small"
                                                                    title="Borrar reseña">
                                                                    <i class="fas fa-trash-alt btn-trash-icon"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <div class="mb-0">
                                                    <?php echo pintarEstrellas($res['puntuacion']); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <p class="mb-0 review-text"><?php echo htmlspecialchars($res['texto']); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 border-0 rounded-4 bg-body mt-4 shadow-sm">
                        <i class="far fa-star fa-3x mb-3 text-muted opacity-25"></i>
                        <p class="fs-5 fw-bold mb-0">Aún no hay reseñas registradas.</p>
                        <p class="text-muted small">¡Sé el primero en documentar tu opinión sobre esta obra!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
    const obra = <?php echo json_encode($datos_obra); ?>;
    const esFavorito = <?php echo json_encode($es_favorito); ?>;
    const estaLogueado = <?php echo json_encode($usuario_logueado); ?>;
    const rolUsuario = '<?php echo $rol_usuario; ?>';

    function obtenerColorTipoJS(tipo) {
        const t = (tipo || '').toUpperCase().trim();
        switch (t) {
            case 'MANHWA': return '#1a9341';
            case 'MANGA': return '#215bc2';
            case 'NOVELA': return '#b71b29';
            case 'DONGHUA': return '#17a2b8';
            case 'MANHUA': return '#6f42c1';
            default: return '#6c757d';
        }
    }

    function obtenerColorDemografiaJS(demo) {
        const d = (demo || '').toUpperCase().trim();
        switch (d) {
            case 'SEINEN': return '#bd1e2c';
            case 'SHOUNEN': return '#d39200';
            case 'SHOUJO': return '#b12f9d';
            case 'JOSEI': return '#6610f2';
            case 'KODOMO': return '#20c997';
            default: return '#343a40';
        }
    }

    function generarEstrellasJS(nota) {
        let html = '';
        let nota_red = Math.round(nota);
        for (let i = 1; i <= 5; i++) {
            if (i <= nota_red) html += '<i class="fas fa-star text-warning"></i> ';
            else html += '<i class="far fa-star text-warning opacity-25"></i> ';
        }
        return html;
    }

    function obtenerColorEstadoJS(estado) {
        const e = (estado || '').trim();
        switch (e) {
            case 'En Emisión': return 'bg-success';
            case 'Hiatus': return 'bg-warning text-dark';
            case 'Finalizado': return 'bg-primary';
            case 'Cancelado': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        const contenedor = document.getElementById('detalle-contenido');

        if (!obra) {
            contenedor.innerHTML = "<div class='alert alert-danger text-center mt-5 rounded-4 shadow-sm'>Registro no localizado en la base de datos.</div>";
            return;
        }

        // Eliminamos las clases text-dark aquí también para que el framework decida el color de texto
        const generosHTML = (obra.generos || []).map(g => `<span class="badge bg-light border border-light me-1 mb-1 fs-6 shadow-sm rounded-pill px-3 py-2">${g}</span>`).join('');

        let imagen = 'https://via.placeholder.com/300x450';
        if (obra.portada) {
            imagen = obra.portada.startsWith('http') ? obra.portada : '/' + obra.portada.replace(/^\/+/, '');
        }

        let botonFavHTML = '';
        if (estaLogueado && rolUsuario !== 'admin') {
            if (esFavorito) {
                botonFavHTML = `<a href="/accion_favorito.php?id=${obra.id}&slug=${obra.slug}&accion=quitar" class="btn btn-outline-danger ms-auto ms-md-3 rounded-pill fw-bold shadow-sm mt-2 mt-sm-0"><i class="fas fa-heart"></i> Guardado</a>`;
            } else {
                botonFavHTML = `<a href="/accion_favorito.php?id=${obra.id}&slug=${obra.slug}&accion=poner" class="btn btn-outline-iori ms-auto ms-md-3 rounded-pill fw-bold shadow-sm mt-2 mt-sm-0"><i class="far fa-heart"></i> Guardar Obra</a>`;
            }
        }

        const tipoObra = (obra.tipo_obra || 'DESCONOCIDO').toUpperCase();
        const demoObra = (obra.demografia || 'DESCONOCIDO').toUpperCase();
        const estadoObra = obra.estado_publicacion || 'En Emisión';

        let bandaTipoHTML = '';
        if (tipoObra !== 'DESCONOCIDO') {
            bandaTipoHTML = `<div class="detalle-badge-tipo shadow-sm" style="background-color: ${obtenerColorTipoJS(tipoObra)};">${tipoObra}</div>`;
        }

        let bandaDemoHTML = '';
        if (demoObra !== 'DESCONOCIDO') {
            bandaDemoHTML = `<div class="detalle-badge-demo shadow-sm" style="background-color: ${obtenerColorDemografiaJS(demoObra)};">${demoObra}</div>`;
        }

        let capsHTML = '';
        if (obra.capitulos && obra.capitulos.length > 0) {
            capsHTML = obra.capitulos.map(cap => {
                const maxCapTocado = parseInt(obra.max_cap_tocado) || 0;
                let paginaLeida = obra.leidos_por_usuario ? parseInt(obra.leidos_por_usuario[cap.id]) : undefined;
                const totalPaginas = parseInt(cap.total_paginas) || 1;

                if (parseInt(cap.id) < maxCapTocado) {
                    paginaLeida = totalPaginas;
                }

                const estaLeido = !isNaN(paginaLeida);
                const estaTerminado = estaLeido && (paginaLeida >= totalPaginas || paginaLeida === 0);

                let indicadorProgreso = '';
                let iconoOjo = '';

                if (estaLogueado && rolUsuario !== 'admin') {
                    if (estaTerminado) {
                        indicadorProgreso = `<span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill me-2 px-3 py-2"><i class="fas fa-check-double me-1"></i> Completado</span>`;
                        iconoOjo = `<a href="/accion_leido.php?capId=${cap.id}&obraId=${obra.id}&slug=${obra.slug}&accion=desmarcar" class="text-success me-3 text-decoration-none hover-scale d-inline-block" title="Marcar como NO leído"><i class="fas fa-eye fs-5"></i></a>`;
                    } else if (estaLeido) {
                        indicadorProgreso = `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary rounded-pill me-2 px-3 py-2"><i class="fas fa-bookmark me-1"></i> Progreso: ${paginaLeida} / ${totalPaginas}</span>`;
                        iconoOjo = `<a href="/accion_leido.php?capId=${cap.id}&obraId=${obra.id}&slug=${obra.slug}&accion=desmarcar" class="text-primary me-3 text-decoration-none hover-scale d-inline-block" title="Marcar como NO leído"><i class="fas fa-eye fs-5"></i></a>`;
                    } else {
                        indicadorProgreso = `<span class="badge bg-light text-muted border rounded-pill me-2 px-3 py-2"><i class="fas fa-book me-1"></i> No iniciado</span>`;
                        iconoOjo = `<a href="/accion_leido.php?capId=${cap.id}&obraId=${obra.id}&slug=${obra.slug}&accion=marcar" class="text-muted me-3 text-decoration-none opacity-50 hover-scale d-inline-block" title="Marcar como leído"><i class="fas fa-eye-slash fs-5"></i></a>`;
                    }
                }
                const claseOpacidadTexto = estaTerminado ? 'opacity-50' : '';

                return `
                <div class="capitulo-item d-flex justify-content-between align-items-center border-bottom p-3 hover-effect-light">
                    <div class="${claseOpacidadTexto}">
                        <span class="fw-bold d-block fs-6">${cap.titulo}</span>
                        <small class="text-muted"><i class="far fa-calendar-alt me-1"></i>${new Date(cap.fecha_subida).toLocaleDateString()}</small>
                    </div>
                    <div class="d-flex align-items-center">
                        ${indicadorProgreso}
                        ${iconoOjo}
                        <a href="/obra/${obra.slug}/${cap.slug}" class="text-iori hover-scale d-inline-block" title="Acceder al visor">
                            <i class="fas fa-play-circle fs-2"></i>
                        </a>
                    </div>
                </div>`;
            }).join('');
        } else {
            capsHTML = `
            <div class="text-center py-5 my-3 bg-body rounded-4 border-0 shadow-sm mx-3">
                <i class="far fa-folder-open fa-3x mb-3 text-muted opacity-50"></i>
                <h5 class="fw-bold text-secondary">Apertura pendiente</h5>
                <p class="text-muted mb-0">El equipo de edición publicará el material en breve.</p>
            </div>`;
        }

        // Inyección HTML purificada sin clases text-dark forzosas
        contenedor.innerHTML = `
            <div class="container-fluid container-lg mt-4 mb-5">
                <div class="row">
                    <div class="col-md-4 col-lg-3 text-center text-md-start mb-4 mb-md-0">
                        <div class="position-relative rounded-4 shadow-sm d-inline-block w-100 overflow-hidden detalle-portada-container border-0">
                            ${bandaTipoHTML}
                            <img src="${imagen}" class="w-100 d-block detalle-portada-img" alt="Portada de la obra">
                            ${bandaDemoHTML}
                        </div>
                    </div>
                    
                    <div class="col-md-8 col-lg-9 d-flex flex-column px-md-4">
                        
                        <div class="d-flex align-items-center mb-1 flex-wrap">
                            <h1 class="fw-bold mb-0 display-5 me-3">${obra.titulo}</h1>
                            ${botonFavHTML} 
                        </div>
                        
                        <div class="d-flex align-items-center flex-wrap gap-3 mb-3 mt-2 bg-body p-2 rounded-pill shadow-sm d-inline-flex w-auto border border-secondary border-opacity-10 pe-4">
                            <div class="d-flex align-items-center ms-2">
                                <span class="fs-5 me-2">${generarEstrellasJS(obra.nota_media)}</span>
                                <span class="fw-bold fs-5">${obra.nota_media} <small class="text-muted fw-normal fs-6">/ 5</small></span>
                                <span class="ms-2 text-muted small fw-semibold">(${obra.total_votos} revisiones)</span>
                            </div>
                            
                            <div class="d-none d-sm-block border-start border-2 border-secondary opacity-25 h-100 mx-2" style="height: 25px !important;"></div>
                            
                            <div class="d-flex align-items-center text-muted">
                                <span class="me-3 fw-bold" title="Métricas de acceso"><i class="fas fa-eye me-1 text-iori"></i> ${obra.visitas}</span>
                                <span class="badge ${obtenerColorEstadoJS(estadoObra)} rounded-pill px-3 shadow-sm"><i class="fas fa-broadcast-tower me-1"></i> ${estadoObra}</span>
                            </div>
                        </div>
                        
                        <p class="mb-3 fs-5 fw-semibold text-muted"><i class="fas fa-pen-nib me-2 text-iori"></i>${obra.autor}</p>
                        
                        <div class="mb-4">${generosHTML}</div>
                        
                        <h5 class="fw-bold border-bottom border-2 border-iori pb-2 d-inline-block">Documento Descriptivo</h5>
                        <div class="sinopsis-container mt-2">
                            <p class="sinopsis-texto opacity-75 fw-medium" id="sinopsisText">${obra.sinopsis || 'Despliegue argumental no disponible.'}</p>
                            <div class="sinopsis-difuminado" id="sinopsisFader"></div>
                        </div>
                        <button class="btn-leer-mas d-none" id="btnLeerMas">Leer más <i class="fas fa-chevron-down ms-1"></i></button>

                    </div>
                </div>
            </div>
            
            <div class="container-fluid container-lg mt-5">
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between border-bottom border-secondary border-opacity-25 border-2 pb-2 mb-4">
                            <h3 class="fw-bold mb-0"><i class="fas fa-list-ul text-iori me-2"></i>Índice de Capítulos</h3>
                            <span class="badge bg-body border border-secondary text-secondary rounded-pill fs-6 shadow-sm px-3">${obra.capitulos.length} serializaciones</span>
                        </div>
                        <div class="contenedor-capitulos bg-body shadow-sm rounded-4 border overflow-hidden w-100 mb-5">
                            ${capsHTML}
                        </div>
                    </div>
                </div>
            </div>
        `;

        const sinopsisEl = document.getElementById('sinopsisText');
        const btnLeerMas = document.getElementById('btnLeerMas');
        const fader = document.getElementById('sinopsisFader');

        if (sinopsisEl.scrollHeight > 120) {
            btnLeerMas.classList.remove('d-none');

            btnLeerMas.addEventListener('click', () => {
                const estaExpandida = sinopsisEl.classList.toggle('expandida');
                if (estaExpandida) {
                    btnLeerMas.innerHTML = 'Leer menos <i class="fas fa-chevron-up ms-1"></i>';
                } else {
                    btnLeerMas.innerHTML = 'Leer más <i class="fas fa-chevron-down ms-1"></i>';
                }
            });
        } else {
            fader.style.display = 'none';
        }
    });
</script>

<?php include 'includes/footer.php'; ?>