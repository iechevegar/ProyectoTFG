<?php
session_start();
require 'includes/db.php';

// 1. Validar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$idObra = intval($_GET['id']);
$datos_obra = null;

// --- CONTADOR DE VISITAS ---
$conn->query("UPDATE obras SET visitas = visitas + 1 WHERE id = $idObra");

// --- LÓGICA DE RESEÑAS Y PUNTUACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['texto_resena'])) {
    if (isset($_SESSION['usuario'])) {
        $texto = trim($_POST['texto_resena']);
        // 1 estrella por defecto si no tocan nada
        $puntuacion = isset($_POST['puntuacion']) ? intval($_POST['puntuacion']) : 1;

        if (!empty($texto)) {
            $nombreUser = $_SESSION['usuario'];
            $resUser = $conn->query("SELECT id FROM usuarios WHERE nombre = '$nombreUser'");
            $userId = $resUser->fetch_assoc()['id'];

            // Insertamos texto Y puntuación
            $stmt = $conn->prepare("INSERT INTO resenas (usuario_id, obra_id, texto, puntuacion) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $userId, $idObra, $texto, $puntuacion);
            if ($stmt->execute()) {
                header("Location: detalle.php?id=$idObra");
                exit();
            }
        }
    } else {
        header("Location: login.php");
        exit();
    }
}

// BORRAR RESEÑA (Solo Admin)
if (isset($_GET['borrar_resena']) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    $idResena = intval($_GET['borrar_resena']);
    $conn->query("DELETE FROM resenas WHERE id = $idResena");
    header("Location: detalle.php?id=$idObra");
    exit();
}

// --- OBTENER DATOS DE LA OBRA ---
$sql = "SELECT * FROM obras WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idObra);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $datos_obra = $resultado->fetch_assoc();
    $datos_obra['generos'] = array_map('trim', explode(',', $datos_obra['generos']));

    // --- CALCULAR LA NOTA MEDIA ---
    $sqlNota = "SELECT AVG(puntuacion) as media, COUNT(id) as total_votos FROM resenas WHERE obra_id = $idObra";
    $resNota = $conn->query($sqlNota)->fetch_assoc();
    $datos_obra['nota_media'] = $resNota['media'] ? round($resNota['media'], 1) : 0;
    $datos_obra['total_votos'] = $resNota['total_votos'];

    // Lógica Favoritos y Usuario Logueado
    $es_favorito = false;
    $usuario_logueado = isset($_SESSION['usuario']);
    // Sacamos el rol para usarlo más adelante
    $rol_usuario = isset($_SESSION['rol']) ? $_SESSION['rol'] : 'invitado';

    if ($usuario_logueado) {
        $nombreUser = $_SESSION['usuario'];
        $resUser = $conn->query("SELECT id FROM usuarios WHERE nombre = '$nombreUser'");
        $userRow = $resUser->fetch_assoc();
        if ($userRow) {
            $userId = $userRow['id'];
            $resFav = $conn->query("SELECT id FROM favoritos WHERE usuario_id = $userId AND obra_id = $idObra");
            if ($resFav->num_rows > 0) {
                $es_favorito = true;
            }

            // --- LÓGICA DE CAPÍTULOS LEÍDOS ---
            $capitulos_leidos = [];
            $sqlLeidos = "SELECT capitulo_id FROM capitulos_leidos WHERE usuario_id = $userId";
            $resLeidos = $conn->query($sqlLeidos);
            while ($row = $resLeidos->fetch_assoc()) {
                $capitulos_leidos[] = $row['capitulo_id'];
            }
            $datos_obra['leidos_por_usuario'] = $capitulos_leidos;
        }
    }

    // Obtener Capítulos
    $resCaps = $conn->query("SELECT id, titulo, fecha_subida FROM capitulos WHERE obra_id = $idObra ORDER BY id ASC");
    $capitulos = [];
    while ($cap = $resCaps->fetch_assoc()) {
        $capitulos[] = $cap;
    }
    $datos_obra['capitulos'] = $capitulos;
}

// CARGAR LAS RESEÑAS DE ESTA OBRA
$sqlResenas = "SELECT r.*, u.nombre, u.foto, u.rol 
               FROM resenas r 
               JOIN usuarios u ON r.usuario_id = u.id 
               WHERE r.obra_id = $idObra 
               ORDER BY r.fecha DESC";
$lista_resenas = $conn->query($sqlResenas);

// Función auxiliar PHP para dibujar estrellas en los comentarios
function pintarEstrellas($nota)
{
    $html = '';
    $nota_red = round($nota);
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $nota_red)
            $html .= '<i class="fas fa-star text-warning" style="font-size: 0.85rem;"></i>';
        else
            $html .= '<i class="far fa-star text-warning opacity-50" style="font-size: 0.85rem;"></i>';
    }
    return $html;
}

include 'includes/header.php';
?>

<style>
    /* Estilos del selector de estrellas */
    .clasificacion {
        direction: rtl;
        unicode-bidi: bidi-override;
        display: inline-block;
    }
    .clasificacion input[type="radio"] { display: none; }
    .clasificacion label {
        color: #ddd;
        font-size: 1.8rem;
        padding: 0 2px;
        cursor: pointer;
        transition: color 0.2s;
    }
    .clasificacion label:hover,
    .clasificacion label:hover~label,
    .clasificacion input[type="radio"]:checked~label {
        color: #ffc107;
    }
    /* Estilos hover capítulos */
    .hover-effect-light:hover { background-color: #fbfbfb; }
    .hover-scale { transition: transform 0.2s; display: inline-block; }
    .hover-scale:hover { transform: scale(1.1); }
</style>

<main style="padding-bottom: 4rem; background-color: #ffffff;">

    <div class="container mt-3">
        <a href="index.php" class="text-decoration-none text-muted">
            <i class="fas fa-arrow-left"></i> Volver al catálogo
        </a>
    </div>

    <div id="detalle-contenido">
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin fa-2x"></i> Cargando datos...
        </div>
    </div>

    <div class="container mt-5 pt-4 border-top">
        <h3 class="mb-4 fw-bold">Reseñas y Opiniones</h3>

        <div class="row">
            <div class="col-12">

                <?php if ($usuario_logueado && $rol_usuario !== 'admin'): ?>
                    <div class="card mb-4 border shadow-sm">
                        <div class="card-body p-4">
                            <form method="POST" action="">
                                <label class="form-label fw-bold mb-0 text-dark">Deja tu valoración:</label>

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

                                <textarea name="texto_resena" class="form-control mb-3" rows="3"
                                    placeholder="¿Qué te ha parecido la historia?" required></textarea>

                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Sé respetuoso. Evita
                                        spoilers sin avisar.</small>
                                    <button type="submit" class="btn btn-primary fw-bold px-4 mt-2 mt-sm-0">Publicar Opinión</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php elseif (!$usuario_logueado): ?>
                    <div class="alert alert-light border text-center mb-4 py-4">
                        <i class="fas fa-lock fa-2x mb-2 text-muted opacity-50 d-block"></i>
                        <a href="login.php" class="fw-bold text-dark">Inicia sesión</a> para dejar tu valoración y reseña.
                    </div>
                <?php endif; ?>

                <?php if ($lista_resenas->num_rows > 0): ?>
                    <?php while ($res = $lista_resenas->fetch_assoc()): ?>
                        <div class="d-flex mb-3 border-bottom pb-3 bg-white">
                            <div class="flex-shrink-0">
                                <?php $foto = !empty($res['foto']) ? $res['foto'] : 'https://via.placeholder.com/50'; ?>
                                <img src="<?php echo $foto; ?>" class="rounded-circle border" width="50" height="50" style="object-fit:cover;">
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0 fw-bold text-dark">
                                        <?php echo htmlspecialchars($res['nombre']); ?>
                                        <?php if ($res['rol'] === 'admin'): ?>
                                            <span class="badge bg-danger ms-1" style="font-size:0.6em">ADMIN</span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($res['fecha'])); ?>
                                        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                            <a href="detalle.php?id=<?php echo $idObra; ?>&borrar_resena=<?php echo $res['id']; ?>"
                                                class="text-danger ms-2" onclick="return confirm('¿Borrar reseña?');" title="Borrar">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <div class="mb-2">
                                    <?php echo pintarEstrellas($res['puntuacion']); ?>
                                </div>

                                <p class="mb-0 text-secondary" style="white-space: pre-wrap; font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($res['texto']); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 border rounded bg-light mt-4">
                        <i class="far fa-star fa-3x mb-3 text-muted opacity-50"></i>
                        <p class="text-muted mb-0">Aún no hay reseñas. ¡Sé el primero en opinar!</p>
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
    
    // CORRECCIÓN VITAL: Pasamos la variable PHP como un texto (string) válido a JavaScript
    const rolUsuario = '<?php echo $rol_usuario; ?>';

    function generarEstrellasJS(nota) {
        let html = '';
        let nota_red = Math.round(nota);
        for (let i = 1; i <= 5; i++) {
            if (i <= nota_red) html += '<i class="fas fa-star"></i> ';
            else html += '<i class="far fa-star opacity-25"></i> ';
        }
        return html;
    }

    document.addEventListener("DOMContentLoaded", () => {
        const contenedor = document.getElementById('detalle-contenido');

        if (!obra) {
            contenedor.innerHTML = "<div class='alert alert-danger text-center mt-5'>Obra no encontrada.</div>";
            return;
        }

        const generosHTML = (obra.generos || []).map(g => `<span class="badge bg-light text-dark border me-1">${g}</span>`).join('');
        const imagen = obra.portada || 'https://via.placeholder.com/300x450';

        let botonFavHTML = '';
        // Si está logueado y NO es admin, pintamos el botón de favoritos
        if (estaLogueado && rolUsuario !== 'admin') {
            if (esFavorito) {
                botonFavHTML = `<a href="accion_favorito.php?id=${obra.id}&accion=quitar" class="btn btn-outline-danger ms-auto ms-md-3 rounded-pill btn-sm fw-bold"><i class="fas fa-heart"></i> Guardado</a>`;
            } else {
                botonFavHTML = `<a href="accion_favorito.php?id=${obra.id}&accion=poner" class="btn btn-outline-primary ms-auto ms-md-3 rounded-pill btn-sm fw-bold"><i class="far fa-heart"></i> Guardar</a>`;
            }
        }

        let capsHTML = '';
        if (obra.capitulos && obra.capitulos.length > 0) {
            capsHTML = obra.capitulos.map(cap => {
                const estaLeido = obra.leidos_por_usuario && obra.leidos_por_usuario.includes(cap.id);
                let iconoOjo = '';
                
                // Si está logueado y NO es admin, pintamos los ojitos de leído
                if (estaLogueado && rolUsuario !== 'admin') {
                    if (estaLeido) {
                        iconoOjo = `<a href="accion_leido.php?capId=${cap.id}&obraId=${obra.id}&accion=desmarcar" class="text-primary me-3 text-decoration-none" title="Marcar como NO leído"><i class="fas fa-eye fs-5"></i></a>`;
                    } else {
                        iconoOjo = `<a href="accion_leido.php?capId=${cap.id}&obraId=${obra.id}&accion=marcar" class="text-muted me-3 text-decoration-none" style="opacity: 0.3;" title="Marcar como leído"><i class="fas fa-eye-slash fs-5"></i></a>`;
                    }
                }
                const opacidadTexto = estaLeido ? 'opacity: 0.5;' : '';

                // El botón de Play vuelve a ser Azul (text-primary)
                return `
                <div class="capitulo-item d-flex justify-content-between align-items-center border-bottom p-3 hover-effect-light">
                    <div style="${opacidadTexto}">
                        <span class="fw-bold d-block text-dark">${cap.titulo}</span>
                        <small class="text-muted">${new Date(cap.fecha_subida).toLocaleDateString()}</small>
                    </div>
                    <div class="d-flex align-items-center">
                        ${iconoOjo}
                        <a href="visor.php?obraId=${obra.id}&capId=${cap.id}" class="text-primary hover-scale">
                            <i class="fas fa-play-circle fs-3"></i>
                        </a>
                    </div>
                </div>`;
            }).join('');
        } else {
            capsHTML = '<div class="alert alert-light text-center text-muted m-3 border">No hay capítulos subidos aún.</div>';
        }

        // PINTAR HTML AL 100% DE ANCHO Y PORTADA GRANDE
        contenedor.innerHTML = `
            <div class="container mt-4 mb-5">
                <div class="row">
                    <div class="col-md-4 col-lg-3 text-center text-md-start mb-4 mb-md-0">
                        <img src="${imagen}" class="w-100 rounded border shadow-sm" alt="Portada">
                    </div>
                    
                    <div class="col-md-8 col-lg-9 text-dark d-flex flex-column justify-content-center">
                        
                        <div class="d-flex align-items-center mb-1 flex-wrap">
                            <h1 class="fw-bold mb-0 display-6">${obra.titulo}</h1>
                            ${botonFavHTML} 
                        </div>
                        
                        <div class="d-flex align-items-center flex-wrap gap-3 mb-3 mt-2">
                            <div class="d-flex align-items-center">
                                <span class="text-warning fs-6 me-2">${generarEstrellasJS(obra.nota_media)}</span>
                                <span class="fw-bold text-dark fs-6">${obra.nota_media} <small class="text-muted fw-normal">/ 5</small></span>
                                <span class="ms-2 text-muted small">(${obra.total_votos} reseñas)</span>
                            </div>
                            
                            <div class="d-none d-sm-block border-start h-100 mx-2" style="width: 1px; background-color: #ddd;"></div>
                            
                            <div class="d-flex align-items-center text-muted small">
                                <span class="me-3" title="Visitas totales"><i class="fas fa-eye me-1"></i> ${obra.visitas}</span>
                                <span title="Fecha de publicación"><i class="fas fa-calendar-alt me-1"></i> ${new Date(obra.fecha_subida).toLocaleDateString()}</span>
                            </div>
                        </div>
                        
                        <p class="mb-2 fs-6">Autor: <strong>${obra.autor}</strong></p>
                        <div class="mb-4">${generosHTML}</div>
                        
                        <h5 class="fw-bold border-bottom pb-2">Sinopsis</h5>
                        <p class="text-secondary mt-2" style="line-height: 1.6; font-size: 1.05rem;">${obra.sinopsis || 'Sin sinopsis disponible.'}</p>
                    </div>
                </div>
            </div>
            
            <div class="container mt-5">
                <div class="row">
                    <div class="col-12">
                        <h3 class="border-bottom pb-2 mb-3 fw-bold">Lista de Capítulos <span class="text-muted fs-6">(${obra.capitulos.length})</span></h3>
                        <div class="contenedor-capitulos bg-white shadow-sm rounded border overflow-hidden w-100">
                            ${capsHTML}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
</script>

<?php include 'includes/footer.php'; ?>