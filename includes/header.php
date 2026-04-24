<?php
// =========================================================================================
// 1. INICIALIZACIÓN DE SESIÓN SEGURA
// =========================================================================================
// Comprobamos el estado del motor de sesiones antes de invocar session_start().
// Al ser el header un archivo que se incluye (require/include) en múltiples vistas, 
// esta validación previene el error fatal "Session had already been started".
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php
    // =========================================================================================
    // 2. SEO DINÁMICO Y PROTOCOLO OPEN GRAPH (SMO - Social Media Optimization)
    // =========================================================================================
    // Definimos metadatos por defecto (Fallback) para páginas estáticas como el índice o el foro.
    $seo_titulo = "IoriScans - Tu refugio de Manga y Manhwa";
    $seo_desc = "Lee tus mangas y manhwas favoritos en español. Únete a la comunidad de IoriScans.";
    $seo_imagen = "http://ioritz-tfg.gamer.gd/assets/img/logo/logo-ioriscans-horizontal.png";

    // Capturamos la URI actual para la etiqueta og:url, vital para la indexación de buscadores
    $current_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $seo_url = "http://ioritz-tfg.gamer.gd" . $current_uri;

    // Hidratación de Metadatos Contextuales: 
    // Si el script padre (ej: detalle.php) ha inyectado el array $datos_obra, 
    // sobrescribimos los metadatos globales con los datos específicos de la entidad.
    if (isset($datos_obra) && is_array($datos_obra)) {
        $seo_titulo = $datos_obra['titulo'] . " | IoriScans";
        // Sanitizamos la sinopsis eliminando etiquetas HTML y truncando a 150 caracteres (Estándar SEO)
        $seo_desc = mb_substr(strip_tags($datos_obra['sinopsis']), 0, 150) . "...";

        // Resolución de URL de la portada (Soporte dual para CDN externo o Path local)
        if (strpos($datos_obra['portada'], 'http') === 0) {
            $seo_imagen = $datos_obra['portada'];
        } else {
            $seo_imagen = "http://ioritz-tfg.gamer.gd/" . ltrim($datos_obra['portada'], '/');
        }
    }
    ?>

    <title><?php echo htmlspecialchars($seo_titulo); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seo_desc); ?>">

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($seo_titulo); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seo_desc); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($seo_imagen); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($seo_url); ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@IoriScans">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($seo_titulo); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($seo_desc); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($seo_imagen); ?>">

    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/logo/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/logo/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/logo/favicon/favicon-16x16.png">
    <link rel="manifest" href="/assets/img/logo/favicon/site.webmanifest">
    <link rel="shortcut icon" href="/assets/img/logo/favicon/favicon.ico">
    
    <link rel="stylesheet" href="/assets/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="/assets/css/styles.css?v=<?php echo time(); ?>">

    <script>
        const savedTheme = localStorage.getItem('ioriscans_theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
</head>

<body class="d-flex flex-column min-vh-100">

    <header class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top shadow-sm py-2">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="/">
                <img src="/assets/img/logo/logo-ioriscans-horizontal.svg" alt="IoriScans" class="header-logo"
                    onerror="this.style.display='none';">
            </a>

            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#menuPrincipal">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menuPrincipal">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">

                    <li class="nav-item">
                        <a class="nav-link fw-semibold px-lg-3" href="/">Catálogo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold px-lg-3" href="/foro">Foro</a>
                    </li>

                    <li class="nav-item mx-lg-3 my-2 my-lg-0">
                        <form action="/" method="GET" class="d-flex">
                            <div class="input-group shadow-sm w-100">
                                <input type="text" name="q" class="form-control form-control-sm border-end-0 bg-light search-input-header"
                                    placeholder="Buscar obra...">
                                <button class="btn bg-light border border-start-0 btn-sm text-muted hover-iori px-3 search-btn-header"
                                    type="submit" title="Buscar">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </li>

                    <li class="nav-item me-lg-3 my-2 my-lg-0 d-flex align-items-center justify-content-start">
                        <button id="btnThemeToggle" class="btn rounded-circle shadow-sm d-flex align-items-center justify-content-center theme-toggle-btn" style="width: 38px; height: 38px; padding: 0;" title="Alternar Modo Oscuro">
                            <i class="fas fa-moon" id="themeIcon"></i>
                        </button>
                    </li>

                    <?php if (isset($_SESSION['usuario'])): ?>

                        <?php if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin'): ?>
                            <li class="nav-item me-lg-2 my-2 my-lg-0">
                                <a class="nav-link fw-bold text-iori" href="/biblioteca">
                                    <i class="fas fa-bookmark me-1"></i> Biblioteca
                                </a>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item dropdown mt-2 mt-lg-0">
                            <a class="nav-link dropdown-toggle btn btn-light border px-3 rounded-pill d-inline-flex align-items-center gap-2 shadow-sm w-100 w-lg-auto justify-content-between search-btn-header"
                                href="#" role="button" data-bs-toggle="dropdown">

                                <div class="d-flex align-items-center gap-2">
                                    <?php
                                    // Generación de Avatar: Fallback dinámico hacia API de iniciales si el campo es nulo
                                    $nav_avatar = '';
                                    if (isset($_SESSION['foto']) && !empty($_SESSION['foto'])) {
                                        $nav_avatar = (strpos($_SESSION['foto'], 'http') === 0) ? $_SESSION['foto'] : '/' . ltrim($_SESSION['foto'], '/');
                                    } else {
                                        $nav_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['usuario']) . '&background=0D8A92&color=fff&size=64&font-size=0.4&bold=true';
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($nav_avatar); ?>"
                                        class="rounded-circle border border-1 border-secondary shadow-sm nav-avatar" alt="Avatar">

                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
                                </div>
                            </a>

                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 m-0 mt-lg-1">
                                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                    <li>
                                        <a class="dropdown-item text-iori fw-bold" href="/admin">
                                            <i class="fas fa-shield-alt me-2"></i>Panel Admin
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>

                                <li><a class="dropdown-item fw-semibold" href="/perfil"><i class="fas fa-user-circle me-2 text-secondary"></i>Mi Perfil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger fw-semibold" href="/logout">
                                        <i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión
                                    </a>
                                </li>
                            </ul>
                        </li>

                    <?php else: ?>
                        <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                            <a class="btn btn-iori btn-sm px-4 py-2 rounded-pill shadow-sm fw-bold w-100 w-lg-auto text-center"
                                href="/login">
                                Iniciar Sesión
                            </a>
                        </li>
                    <?php endif; ?>

                </ul>
            </div>
        </div>
    </header>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btnThemeToggle = document.getElementById('btnThemeToggle');
            const themeIcon = document.getElementById('themeIcon');
            const htmlElement = document.documentElement;

            // Sincronización inicial de la iconografía basada en el estado inyectado por PHP
            if (htmlElement.getAttribute('data-bs-theme') === 'dark') {
                themeIcon.classList.replace('fa-moon', 'fa-sun');
            }

            // Bind del evento de mutación de estado
            btnThemeToggle.addEventListener('click', () => {
                const currentTheme = htmlElement.getAttribute('data-bs-theme');
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                
                // Aplicamos la mutación en el DOM y persistimos la preferencia del cliente
                htmlElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('ioriscans_theme', newTheme);

                // Alternancia de la iconografía visual
                if (newTheme === 'dark') {
                    themeIcon.classList.replace('fa-moon', 'fa-sun');
                } else {
                    themeIcon.classList.replace('fa-sun', 'fa-moon');
                }
            });
        });
    </script>