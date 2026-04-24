<?php
require 'includes/db.php';

// =========================================================================================
// 1. INICIALIZACIÓN Y ENRUTAMIENTO SEMÁNTICO (DUAL SLUG)
// =========================================================================================
// Iniciamos sesión asegurándonos primero de que no haya sido iniciada previamente
// por algún otro archivo incluido, evitando errores fatales de PHP.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validación de Entrada: El visor requiere imperativamente conocer la obra y el capítulo.
if (!isset($_GET['obraSlug']) || !isset($_GET['capSlug'])) {
    die("Error de Enrutamiento: Parámetros de URL insuficientes.");
}

$obraSlug = $_GET['obraSlug'];
$capSlug = $_GET['capSlug'];
$origen = isset($_GET['origen']) ? $_GET['origen'] : 'public';

// --- RESOLUCIÓN DE IDENTIDADES (SLUG A ID) ---
// Transformamos los slugs amigables (SEO) en identificadores relacionales (Primary Keys)
// mediante un JOIN. Esto nos permite usar IDs numéricos para el resto de transacciones,
// lo cual es infinitamente más rápido a nivel de motor de base de datos.
$sql_datos = "SELECT c.*, o.id as obraId, o.titulo as titulo_obra 
              FROM capitulos c 
              JOIN obras o ON c.obra_id = o.id 
              WHERE o.slug = ? AND c.slug = ?";
$stmt_datos = $conn->prepare($sql_datos);
$stmt_datos->bind_param("ss", $obraSlug, $capSlug);
$stmt_datos->execute();
$capitulo = $stmt_datos->get_result()->fetch_assoc();

// Patrón Fail-Fast: 404 Lógico si las entidades no concuerdan
if (!$capitulo) {
    header("Location: /404.php");
    exit();
}

$capId = $capitulo['id'];
$obraId = $capitulo['obraId'];

// =========================================================================================
// 2. MIDDLEWARE DE ESTADO Y POLÍTICAS DE COMUNIDAD (SOFT-BANS)
// =========================================================================================
$estaSuspendido = false;
$fechaDesbloqueoStr = '';
$userId = null;

if (isset($_SESSION['usuario'])) {
    $nombreUser = $_SESSION['usuario'];
    $resUser = $conn->query("SELECT id, fecha_desbloqueo FROM usuarios WHERE nombre = '$nombreUser'");

    if ($resUser && $resUser->num_rows > 0) {
        $userData = $resUser->fetch_assoc();
        $userId = $userData['id'];

        // Verificamos si existe una restricción temporal activa para bloquear la participación
        if (!empty($userData['fecha_desbloqueo']) && strtotime($userData['fecha_desbloqueo']) > time()) {
            $estaSuspendido = true;
            $fechaDesbloqueoStr = date('d/m/Y H:i', strtotime($userData['fecha_desbloqueo']));
        }
    }
}

// =========================================================================================
// 3. RECUPERACIÓN DE ESTADO (MARCAPÁGINAS EN TIEMPO REAL)
// =========================================================================================
$ultima_pagina_leida = 1;

if ($userId) {
    // Extraemos el High-Water Mark (última página alcanzada) para esta obra y usuario.
    // Esto se usará posteriormente en el Frontend para hacer un Auto-Scroll inteligente.
    $sqlProgreso = "SELECT ultima_pagina FROM capitulos_leidos WHERE usuario_id = ? AND capitulo_id = ?";
    $stmtProgreso = $conn->prepare($sqlProgreso);
    $stmtProgreso->bind_param("ii", $userId, $capId);
    $stmtProgreso->execute();
    $resProgreso = $stmtProgreso->get_result();

    if ($resProgreso->num_rows > 0) {
        $ultima_pagina_leida = intval($resProgreso->fetch_assoc()['ultima_pagina']);
    }
}

// =========================================================================================
// 4. CONTROLADOR DE MUTACIONES (COMENTARIOS Y MODERACIÓN)
// =========================================================================================
// Construimos la URL de redirección preservando el origen (si venía del panel admin o no)
$url_redireccion = "/obra/$obraSlug/$capSlug" . ($origen === 'admin' ? "?origen=admin" : "");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- CREACIÓN DE COMENTARIO ---
    if (isset($_POST['comentario'])) {
        if ($userId) {
            // Capa de Defensa: Evitamos que usuarios suspendidos fuercen peticiones POST
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

    // --- HERRAMIENTA DE MODERACIÓN (BORRADO ADMINISTRATIVO) ---
    if (isset($_POST['borrar_comentario']) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        $idCom = intval($_POST['borrar_comentario']);
        $conn->query("DELETE FROM comentarios WHERE id = $idCom");
        header("Location: $url_redireccion");
        exit();
    }
}

// =========================================================================================
// 5. NAVEGACIÓN SECUENCIAL (SISTEMA NEXT/PREV)
// =========================================================================================
$url_volver = ($origen === 'admin') ? "/ver_capitulos.php?id=" . $obraId : "/obra/" . $obraSlug;

// Calculamos los nodos adyacentes (Capítulo anterior y siguiente) en base a su Primary Key
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

$urlPrevJS = $slugPrev ? "/obra/$obraSlug/$slugPrev" . ($origen === 'admin' ? "?origen=admin" : "") : "";
$urlNextJS = $slugNext ? "/obra/$obraSlug/$slugNext" . ($origen === 'admin' ? "?origen=admin" : "") : "";

// Decodificación del Blob JSON que contiene las rutas de las páginas
$lista_imagenes = json_decode($capitulo['contenido'], true);
if (!is_array($lista_imagenes)) {
    $lista_imagenes = [];
}

// Extracción de la sección de comentarios
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
    <title><?php echo htmlspecialchars($capitulo['titulo_obra'] . " - " . $capitulo['titulo']); ?></title>

    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css?v=<?php echo time(); ?>">

    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/logo/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/logo/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/logo/favicon/favicon-16x16.png">
</head>

<body class="visor-body">

    <div class="visor-barra" id="navbarVisor">
        <button class="btn-volver" id="btn-cerrar">
            <i class="fas fa-arrow-left"></i> <span class="d-none d-md-inline">Volver a la ficha</span>
        </button>

        <span class="titulo-visor">
            <span class="text-iori me-2 d-none d-sm-inline"><?php echo htmlspecialchars($capitulo['titulo_obra']); ?></span>
            <?php echo htmlspecialchars($capitulo['titulo']); ?>
        </span>

        <div class="visor-nav-container">
            <?php if ($slugPrev): ?>
                <a href="<?php echo $urlPrevJS; ?>" class="btn-mini-nav" title="Capítulo Anterior"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
                <button class="btn-mini-nav disabled"><i class="fas fa-chevron-left"></i></button>
            <?php endif; ?>

            <?php if ($slugNext): ?>
                <a href="<?php echo $urlNextJS; ?>" class="btn-mini-nav active" title="Capítulo Siguiente"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
                <button class="btn-mini-nav disabled"><i class="fas fa-chevron-right"></i></button>
            <?php endif; ?>
        </div>
    </div>

    <div class="visor-contenido" id="contenedor-imagenes">
        <?php $numero_pagina = 1; ?>
        <?php foreach ($lista_imagenes as $url): ?>
            <?php $imgSrc = (strpos($url, 'http') === 0) ? $url : '/' . ltrim($url, '/'); ?>
            <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="pagina-manga"
                data-pagina="<?php echo $numero_pagina; ?>" loading="lazy" alt="Página <?php echo $numero_pagina; ?>">
            <?php $numero_pagina++; ?>
        <?php endforeach; ?>

        <?php if (empty($lista_imagenes)): ?>
            <div class="visor-empty-state">
                <i class="fas fa-image fa-3x mb-3 opacity-50"></i><br>
                <h5 class="fw-bold">No hay imágenes disponibles</h5>
                <p>El administrador aún no ha subido las páginas de este capítulo.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($lista_imagenes)): ?>
        <div class="navegacion-capitulos">
            <?php if ($slugPrev): ?>
                <a href="<?php echo $urlPrevJS; ?>" class="btn-nav-cap btn-anterior"><i class="fas fa-arrow-left"></i> Anterior</a>
            <?php else: ?>
                <div class="btn-nav-cap btn-disabled">Primer Capítulo</div>
            <?php endif; ?>

            <?php if ($slugNext): ?>
                <a href="<?php echo $urlNextJS; ?>" class="btn-nav-cap btn-siguiente">Siguiente <i class="fas fa-arrow-right"></i></a>
            <?php else: ?>
                <div class="btn-nav-cap btn-disabled"><i class="fas fa-flag-checkered"></i> Último Capítulo</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="zona-comentarios">
        <h4 class="mb-4 fw-bold border-bottom border-dark pb-3"><i class="far fa-comments text-iori me-2"></i> Discusión (<?php echo $resCom->num_rows; ?>)</h4>

        <?php if (isset($_SESSION['usuario'])): ?>

            <?php if ($estaSuspendido): ?>
                <div class="alert text-center mb-5 py-4 visor-alert-danger">
                    <i class="fas fa-ban fa-2x mb-2 opacity-75"></i>
                    <h5 class="fw-bold">Participación Bloqueada</h5>
                    <p class="mb-0 small text-light opacity-75">Tu cuenta se encuentra en modo Solo Lectura por infracciones de la comunidad.</p>
                    <small class="fw-bold d-block mt-2">Podrás volver a comentar el: <?php echo $fechaDesbloqueoStr; ?></small>
                </div>
            <?php else: ?>
                <form method="POST" action="" class="form-comentario mb-5">
                    <div class="mb-3">
                        <textarea name="comentario" class="form-control" rows="3" placeholder="¿Qué te ha parecido este capítulo? Deja tu opinión..." required></textarea>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-publicar text-white shadow-sm"><i class="fas fa-paper-plane me-2"></i>Publicar</button>
                    </div>
                </form>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert text-center mb-5 py-4 visor-alert-login">
                <i class="fas fa-lock fa-2x mb-3 text-iori"></i>
                <h5 class="fw-bold text-white">Únete a la conversación</h5>
                <p class="mb-4">Necesitas una cuenta para comentar y guardar tu progreso de lectura.</p>
                <a href="/login" class="btn rounded-pill px-5 py-2 fw-bold text-white shadow visor-btn-login">Iniciar sesión / Registrarse</a>
            </div>
        <?php endif; ?>

        <?php if ($resCom->num_rows > 0): ?>
            <?php while ($com = $resCom->fetch_assoc()): ?>
                <div class="caja-comentario">
                    <?php
                    $fotoUser = !empty($com['foto']) ? ((strpos($com['foto'], 'http') === 0) ? $com['foto'] : '/' . ltrim($com['foto'], '/')) : 'https://via.placeholder.com/45';
                    ?>
                    <img src="<?php echo htmlspecialchars($fotoUser); ?>" class="avatar-comentario" alt="Avatar">

                    <div class="info-comentario">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="nombre-usuario">
                                <?php echo htmlspecialchars($com['nombre']); ?>
                                <?php if ($com['rol'] === 'admin'): ?>
                                    <span class="badge bg-danger ms-1" style="font-size:0.65rem; vertical-align: middle;">ADMIN</span>
                                <?php endif; ?>
                            </span>
                            <span class="fecha-comentario">
                                <?php echo date('d/m/Y H:i', strtotime($com['fecha'])); ?>
                                
                                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                    <form method="POST" class="d-inline ms-2" onsubmit="return confirm('¿Seguro que quieres borrar este comentario permanentemente?');">
                                        <input type="hidden" name="borrar_comentario" value="<?php echo $com['id']; ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0 border-0 align-baseline" title="Borrar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </span>
                        </div>
                        <p class="texto-comentario mb-0"><?php echo nl2br(htmlspecialchars($com['texto'])); ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="far fa-comment-dots fa-3x text-muted mb-3 opacity-25"></i>
                <p class="text-muted fs-5">No hay comentarios aún. ¡Sé el primero en opinar!</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('btn-cerrar').onclick = () => {
            window.location.href = '<?php echo $url_volver; ?>';
        };

        // --- ACCESIBILIDAD: NAVEGACIÓN POR TECLADO (Keybinding) ---
        // Permite a los usuarios cambiar de capítulo usando las flechas direccionales,
        // interceptando el evento a nivel de documento pero excluyendo inputs y textareas.
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

        document.addEventListener("DOMContentLoaded", () => {

            // --- LÓGICA UI: AUTO-HIDE NAVBAR ---
            // Oculta la barra de navegación superior al hacer scroll hacia abajo para
            // maximizar el área de lectura (Modo Inmersivo) y la muestra al subir.
            let lastScrollTop = 0;
            const navbar = document.getElementById("navbarVisor");

            window.addEventListener("scroll", function () {
                let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                if (scrollTop > lastScrollTop && scrollTop > 100) {
                    navbar.style.transform = "translateY(-100%)";
                } else {
                    navbar.style.transform = "translateY(0)";
                }
                lastScrollTop = scrollTop;
            });

            // --- ESTADO DE SESIÓN: AUTO-SCROLL A LECTURA PREVIA ---
            // Utiliza el High-Water Mark recuperado de la BD al inicio del script.
            const paginaGuardada = <?php echo $ultima_pagina_leida; ?>;

            if (paginaGuardada > 1) {
                // Delay artificial para dar tiempo al navegador a renderizar las imágenes
                // y evitar cálculos de offset incorrectos.
                setTimeout(() => {
                    const imagenDestino = document.querySelector(`.pagina-manga[data-pagina="${paginaGuardada}"]`);
                    if (imagenDestino) {
                        imagenDestino.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        console.log("UX Optimizada: Retomando lectura en la página " + paginaGuardada);
                    }
                }, 500);
            }

            // --- TRACKING AVANZADO: INTERSECTION OBSERVER + DEBOUNCING ---
            // Arquitectura vital: Monitoreamos qué imagen está actualmente en la pantalla del usuario.
            // Para no saturar el servidor con una petición AJAX por cada imagen visualizada, 
            // aplicamos el patrón "Debounce" (esperamos 2 segundos de inactividad antes de guardar)
            // y una restricción lógica (solo guardamos si es el progreso máximo).
            const imagenes = document.querySelectorAll('.pagina-manga');
            let temporizadorGuardado;
            const capId = <?php echo $capId; ?>;
            let maxPaginaAlcanzada = paginaGuardada;

            if (imagenes.length > 0) {
                // Instanciamos la API nativa del navegador para observar elementos
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        // isIntersecting verifica si al menos el 15% (threshold) de la imagen está visible
                        if (entry.isIntersecting) {
                            const paginaActual = parseInt(entry.target.getAttribute('data-pagina'));

                            // Restricción High-Water Mark a nivel de cliente
                            if (paginaActual > maxPaginaAlcanzada) {
                                maxPaginaAlcanzada = paginaActual;
                                
                                // Limpiamos el temporizador anterior si el usuario sigue haciendo scroll rápido (Debounce)
                                clearTimeout(temporizadorGuardado);

                                // Lanzamos la sincronización de telemetría tras 2000ms de pausa
                                temporizadorGuardado = setTimeout(() => {
                                    fetch('/marcar_leido_ajax.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: 'capId=' + capId + '&pagina=' + maxPaginaAlcanzada
                                    })
                                        .then(response => response.text())
                                        .then(data => console.log("Sincronización I/O: " + data))
                                        .catch(error => console.error("Error de Red en Telemetría:", error));
                                }, 2000);
                            }
                        }
                    });
                }, { threshold: 0.15 });

                // Registramos todos los nodos de imágenes en el observador
                imagenes.forEach(img => observer.observe(img));
            }
        });
    </script>
</body>

</html>