<?php
require 'includes/db.php';
// Iniciamos sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE)
    session_start();

// 1. Validaciones básicas POR SLUG (Ya no usamos capId en la URL)
if (!isset($_GET['obraSlug']) || !isset($_GET['capSlug']))
    die("Error: URL incompleta.");

$obraSlug = $_GET['obraSlug'];
$capSlug = $_GET['capSlug'];
$origen = isset($_GET['origen']) ? $_GET['origen'] : 'public';

// --- OBTENER LOS DATOS E IDs REALES DESDE LOS SLUGS ---
$sql_datos = "SELECT c.*, o.id as obraId, o.titulo as titulo_obra 
              FROM capitulos c 
              JOIN obras o ON c.obra_id = o.id 
              WHERE o.slug = ? AND c.slug = ?";
$stmt_datos = $conn->prepare($sql_datos);
$stmt_datos->bind_param("ss", $obraSlug, $capSlug);
$stmt_datos->execute();
$capitulo = $stmt_datos->get_result()->fetch_assoc();

if (!$capitulo) {
    header("Location: /404.php");
    exit();
}

$capId = $capitulo['id'];
$obraId = $capitulo['obraId'];
// ------------------------------------------------------

// --- 2. ESTADO DEL USUARIO Y SUSPENSIÓN ---
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
// -----------------------------------------

// --- 2.5 OBTENER LA ÚLTIMA PÁGINA LEÍDA ---
$ultima_pagina_leida = 1; // Por defecto empezamos en la 1

if ($userId) {
    $sqlProgreso = "SELECT ultima_pagina FROM capitulos_leidos WHERE usuario_id = ? AND capitulo_id = ?";
    $stmtProgreso = $conn->prepare($sqlProgreso);
    $stmtProgreso->bind_param("ii", $userId, $capId);
    $stmtProgreso->execute();
    $resProgreso = $stmtProgreso->get_result();

    if ($resProgreso->num_rows > 0) {
        $ultima_pagina_leida = intval($resProgreso->fetch_assoc()['ultima_pagina']);
    }
}
// ------------------------------------------

// 3. PROCESAR ACCIONES POST (Comentar o Borrar)
$url_redireccion = "/obra/$obraSlug/$capSlug" . ($origen === 'admin' ? "?origen=admin" : "");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. NUEVO COMENTARIO
    if (isset($_POST['comentario'])) {
        if ($userId) {
            // DEFENSA BACKEND: Si está suspendido, rechazamos la petición
            if ($estaSuspendido) {
                header("Location: $url_redireccion");
                exit();
            }

            $texto = trim($_POST['comentario']);
            if (!empty($texto)) {
                $stmtInsert = $conn->prepare("INSERT INTO comentarios (usuario_id, capitulo_id, texto) VALUES (?, ?, ?)");
                $stmtInsert->bind_param("iis", $userId, $capId, $texto);
                $stmtInsert->execute();

                header("Location: $url_redireccion");
                exit();
            }
        } else {
            header("Location: /login");
            exit();
        }
    }

    // B. BORRAR COMENTARIO (Solo Admin)
    if (isset($_POST['borrar_comentario']) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        $idCom = intval($_POST['borrar_comentario']);
        $conn->query("DELETE FROM comentarios WHERE id = $idCom");
        header("Location: $url_redireccion");
        exit();
    }
}

// 4. OBTENER NAVEGACIÓN (AHORA BUSCAMOS LOS SLUGS)
// Definimos a dónde vuelve el botón "Cerrar"
$url_volver = ($origen === 'admin') ? "/ver_capitulos.php?id=" . $obraId : "/obra/" . $obraSlug;

// Anterior / Siguiente
$sqlPrev = "SELECT slug FROM capitulos WHERE obra_id = ? AND id < ? ORDER BY id DESC LIMIT 1";
$stmtPrev = $conn->prepare($sqlPrev);
$stmtPrev->bind_param("ii", $obraId, $capId);
$stmtPrev->execute();
$slugPrev = $stmtPrev->get_result()->fetch_assoc()['slug'] ?? null;

$sqlNext = "SELECT slug FROM capitulos WHERE obra_id = ? AND id > ? ORDER BY id ASC LIMIT 1";
$stmtNext = $conn->prepare($sqlNext);
$stmtNext->bind_param("ii", $obraId, $capId);
$stmtNext->execute();
$slugNext = $stmtNext->get_result()->fetch_assoc()['slug'] ?? null;

// Rutas completas para JS
$urlPrevJS = $slugPrev ? "/obra/$obraSlug/$slugPrev" . ($origen === 'admin' ? "?origen=admin" : "") : "";
$urlNextJS = $slugNext ? "/obra/$obraSlug/$slugNext" . ($origen === 'admin' ? "?origen=admin" : "") : "";

// Decodificar imágenes
$lista_imagenes = json_decode($capitulo['contenido'], true);
if (!is_array($lista_imagenes))
    $lista_imagenes = [];

// 5. OBTENER COMENTARIOS DEL CAPÍTULO
$sqlCom = "SELECT c.*, u.nombre, u.foto, u.rol 
           FROM comentarios c 
           JOIN usuarios u ON c.usuario_id = u.id 
           WHERE c.capitulo_id = $capId 
           ORDER BY c.fecha DESC";
$resCom = $conn->query($sqlCom);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($capitulo['titulo']); ?> - Visor</title>

    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css?v=<?php echo time(); ?>">

    <style>
        .zona-comentarios {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
            color: #ccc;
        }

        .caja-comentario {
            background: #1a1a1a;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
        }

        .avatar-comentario {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .info-comentario {
            flex: 1;
        }

        .nombre-usuario {
            font-weight: bold;
            color: white;
            margin-bottom: 0.2rem;
            display: block;
        }

        .fecha-comentario {
            font-size: 0.8rem;
            color: #666;
        }

        .texto-comentario {
            color: #ddd;
            margin-top: 0.5rem;
        }

        .form-comentario textarea {
            background: #262626;
            border: 1px solid #333;
            color: white;
            resize: none;
        }

        .form-comentario textarea:focus {
            background: #333;
            border-color: #2563eb;
            outline: none;
            box-shadow: none;
        }
    </style>
</head>

<body class="visor-body">

    <div class="visor-barra">
        <button class="btn-volver" id="btn-cerrar">
            <i class="fas fa-times"></i> <span class="d-none d-md-inline">Cerrar</span>
        </button>

        <span id="titulo-capitulo" style="font-weight:bold; font-size: 0.9rem;">
            <?php echo htmlspecialchars($capitulo['titulo']); ?>
        </span>

        <div style="display:flex; gap: 0.5rem;">
            <?php $queryAdmin = ($origen === 'admin') ? '?origen=admin' : ''; ?>

            <?php if ($slugPrev): ?>
                <a href="<?php echo $urlPrevJS; ?>"
                    class="btn btn-sm btn-secondary btn-mini-nav"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
                <button class="btn btn-sm btn-secondary btn-mini-nav" disabled style="opacity:0.3"><i
                        class="fas fa-chevron-left"></i></button>
            <?php endif; ?>

            <?php if ($slugNext): ?>
                <a href="<?php echo $urlNextJS; ?>"
                    class="btn btn-sm btn-primary btn-mini-nav"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
                <button class="btn btn-sm btn-secondary btn-mini-nav" disabled style="opacity:0.3"><i
                        class="fas fa-chevron-right"></i></button>
            <?php endif; ?>
        </div>
    </div>

    <div class="visor-contenido" id="contenedor-imagenes">
        <?php $numero_pagina = 1; // Empezamos a contar las páginas ?>
        <?php foreach ($lista_imagenes as $url): ?>
            <?php
            $imgSrc = (strpos($url, 'http') === 0) ? $url : '/' . ltrim($url, '/');
            ?>
            <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="pagina-manga"
                data-pagina="<?php echo $numero_pagina; ?>" loading="lazy" alt="Página <?php echo $numero_pagina; ?>">
            <?php $numero_pagina++; // Sumamos 1 para la siguiente imagen ?>
        <?php endforeach; ?>

        <?php if (empty($lista_imagenes)): ?>
            <div style="padding: 5rem; text-align: center; color: #666;"><i class="fas fa-images fa-2x mb-3"></i><br>Sin
                imágenes.</div>
        <?php endif; ?>
    </div>

    <div class="navegacion-capitulos">
        <?php if ($slugPrev): ?>
            <a href="<?php echo $urlPrevJS; ?>"
                class="btn-nav-cap btn-anterior"><i class="fas fa-arrow-left"></i> Anterior</a>
        <?php else: ?>
            <div class="btn-nav-cap btn-disabled">Primer Capítulo</div>
        <?php endif; ?>

        <?php if ($slugNext): ?>
            <a href="<?php echo $urlNextJS; ?>"
                class="btn-nav-cap btn-siguiente">Siguiente <i class="fas fa-arrow-right"></i></a>
        <?php else: ?>
            <div class="btn-nav-cap btn-disabled">Último Capítulo 🚫</div>
        <?php endif; ?>
    </div>

    <div class="zona-comentarios">
        <h4 class="mb-4"><i class="far fa-comments"></i> Comentarios (<?php echo $resCom->num_rows; ?>)</h4>

        <?php if (isset($_SESSION['usuario'])): ?>

            <?php if ($estaSuspendido): ?>
                <div class="alert text-center mb-5 py-4"
                    style="background-color: #2a1111; border: 1px solid #ff4444; color: #ff8888; border-radius: 8px;">
                    <i class="fas fa-ban fa-2x mb-2 opacity-75"></i>
                    <h5 class="fw-bold">Participación Bloqueada</h5>
                    <p class="mb-0 small text-light opacity-75">Tu cuenta se encuentra en modo Solo Lectura por infracciones de
                        la comunidad.</p>
                    <small class="fw-bold d-block mt-2" style="color: #ffaaaa;">Podrás volver a comentar el:
                        <?php echo $fechaDesbloqueoStr; ?></small>
                </div>
            <?php else: ?>
                <form method="POST" action="" class="form-comentario mb-5">
                    <div class="mb-2">
                        <textarea name="comentario" class="form-control" rows="3"
                            placeholder="Escribe tu opinión sobre este capítulo..." required></textarea>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-sm">Publicar Comentario</button>
                    </div>
                </form>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-dark text-center mb-5">
                <a href="/login" class="text-info">Inicia sesión</a> para dejar un comentario.
            </div>
        <?php endif; ?>

        <?php if ($resCom->num_rows > 0): ?>
            <?php while ($com = $resCom->fetch_assoc()): ?>
                <div class="caja-comentario">
                    <?php
                    // Aplicamos el mismo truco a los avatares por si usan links externos o internos
                    $fotoUser = !empty($com['foto']) ? ((strpos($com['foto'], 'http') === 0) ? $com['foto'] : '/' . ltrim($com['foto'], '/')) : 'https://via.placeholder.com/40';
                    ?>
                    <img src="<?php echo htmlspecialchars($fotoUser); ?>" class="avatar-comentario" alt="Avatar">

                    <div class="info-comentario">
                        <div class="d-flex justify-content-between">
                            <span class="nombre-usuario">
                                <?php echo htmlspecialchars($com['nombre']); ?>
                                <?php if ($com['rol'] === 'admin'): ?>
                                    <span class="badge bg-danger" style="font-size:0.6rem">ADMIN</span>
                                <?php endif; ?>
                            </span>
                            <span class="fecha-comentario">
                                <?php echo date('d/m/Y H:i', strtotime($com['fecha'])); ?>

                                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                    <form method="POST" class="d-inline ms-2"
                                        onsubmit="return confirm('¿Borrar este comentario?');">
                                        <input type="hidden" name="borrar_comentario" value="<?php echo $com['id']; ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0 border-0 align-baseline"
                                            title="Borrar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </span>
                        </div>
                        <p class="texto-comentario"><?php echo nl2br(htmlspecialchars($com['texto'])); ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center text-muted">Sé el primero en comentar.</p>
        <?php endif; ?>
    </div>

    <script>
        // 1. Botón de cerrar
        document.getElementById('btn-cerrar').onclick = () => {
            window.location.href = '<?php echo $url_volver; ?>';
        };

        // --- NAVEGACIÓN POR TECLADO (FLECHAS) ---
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'INPUT') return;

            const urlPrev = '<?php echo $urlPrevJS; ?>';
            const urlNext = '<?php echo $urlNextJS; ?>';

            if (e.key === 'ArrowLeft' && urlPrev !== '') {
                window.location.href = urlPrev;
            } else if (e.key === 'ArrowRight' && urlNext !== '') {
                window.location.href = urlNext;
            }
        });

        // 2. Lógica del visor (Auto-scroll y Guardado exacto)
        document.addEventListener("DOMContentLoaded", () => {

            // --- AUTO-SCROLL A LA PÁGINA GUARDADA ---
            const paginaGuardada = <?php echo $ultima_pagina_leida; ?>;

            if (paginaGuardada > 1) {
                setTimeout(() => {
                    const imagenDestino = document.querySelector(`.pagina-manga[data-pagina="${paginaGuardada}"]`);
                    if (imagenDestino) {
                        imagenDestino.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        console.log("Retomando lectura en la página " + paginaGuardada);
                    }
                }, 500);
            }

            // --- DEBOUNCE Y GUARDADO DE PROGRESO ---
            const imagenes = document.querySelectorAll('.pagina-manga');
            let temporizadorGuardado; 
            const capId = <?php echo $capId; ?>;
            
            // Nueva variable para no perder el progreso si hacemos scroll hacia arriba
            let maxPaginaAlcanzada = paginaGuardada; 

            if (imagenes.length > 0) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {

                            // Convertimos el número a entero
                            const paginaActual = parseInt(entry.target.getAttribute('data-pagina'));

                            // TRUCO TFG: Solo activamos el guardado si el usuario sigue bajando
                            if (paginaActual > maxPaginaAlcanzada) {
                                maxPaginaAlcanzada = paginaActual;

                                clearTimeout(temporizadorGuardado);

                                temporizadorGuardado = setTimeout(() => {
                                    console.log("Usuario estable. Guardando página: " + maxPaginaAlcanzada);

                                    fetch('/marcar_leido_ajax.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: 'capId=' + capId + '&pagina=' + maxPaginaAlcanzada
                                    })
                                    .then(response => response.text())
                                    .then(data => console.log("Servidor:", data))
                                    .catch(error => console.error("Error al guardar:", error));

                                }, 2000);
                            }
                        }
                    });
                }, {
                    // EL ARREGLO ESTÁ AQUÍ: 
                    // 0.15 significa que con que se vea un 15% de la imagen, ya la detecta.
                    // Ideal para imágenes kilométricas de manhwa.
                    threshold: 0.15 
                });

                imagenes.forEach(img => observer.observe(img));
            }
        });
    </script>
</body>

</html>