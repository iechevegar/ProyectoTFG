<?php
// Iniciar sesión si no está iniciada
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
    // --- LÓGICA DE SEO DINÁMICO (OPEN GRAPH) ---
    $seo_titulo = "IoriScans - Tu refugio de Manga y Manhwa";
    $seo_desc = "Lee tus mangas y manhwas favoritos en español. Únete a la comunidad de IoriScans.";

    // Aquí cargamos tu nuevo PNG horizontal para que se vea perfecto al pasar el link por WhatsApp/Discord
    $seo_imagen = "http://ioritz-tfg.gamer.gd/assets/img/logo/logo-ioriscans-horizontal.png";

    $current_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $seo_url = "http://ioritz-tfg.gamer.gd" . $current_uri;

    if (isset($datos_obra) && is_array($datos_obra)) {
        $seo_titulo = $datos_obra['titulo'] . " | IoriScans";
        $seo_desc = mb_substr(strip_tags($datos_obra['sinopsis']), 0, 150) . "...";

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
</head>

<body class="d-flex flex-column min-vh-100">

    <header class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="/">
                <img src="/assets/img/logo/logo-ioriscans-horizontal.svg" alt="IoriScans" height="45"
                    onerror="this.style.display='none';">
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menuPrincipal">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">

                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="/">Catálogo</a>
                    </li>
                    <li class="nav-item me-3">
                        <a class="nav-link fw-semibold" href="/foro">Foro</a>
                    </li>

                    <li class="nav-item me-3 d-none d-lg-block">
                        <form action="/" method="GET" class="d-flex">
                            <div class="input-group shadow-sm">
                                <input type="text" name="q" class="form-control form-control-sm border-end-0 bg-light"
                                    placeholder="Buscar obra..." style="min-width: 200px;">
                                <button class="btn btn-light border border-start-0 btn-sm text-muted hover-iori"
                                    type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </li>

                    <?php if (isset($_SESSION['usuario'])): ?>

                        <?php if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin'): ?>
                            <li class="nav-item me-2">
                                <a class="nav-link fw-bold text-iori" href="/biblioteca">
                                    <i class="fas fa-bookmark me-1"></i> Biblioteca
                                </a>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item dropdown ms-lg-2">
                            <a class="nav-link dropdown-toggle btn btn-light border px-3 rounded-pill d-flex align-items-center gap-2 shadow-sm"
                                href="#" role="button" data-bs-toggle="dropdown">
                                <?php if (isset($_SESSION['foto']) && !empty($_SESSION['foto'])): ?>
                                    <img src="<?php echo htmlspecialchars($_SESSION['foto']); ?>" class="rounded-circle"
                                        width="24" height="24" style="object-fit: cover;">
                                <?php else: ?>
                                    <i class="far fa-user text-iori"></i>
                                <?php endif; ?>

                                <span
                                    class="fw-semibold text-dark"><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
                            </a>

                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 m-0">
                                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                    <li>
                                        <a class="dropdown-item text-iori fw-bold" href="/admin">
                                            <i class="fas fa-shield-alt me-2"></i>Panel Admin
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                <?php endif; ?>

                                <li><a class="dropdown-item fw-semibold" href="/perfil">Mi Perfil</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item text-danger fw-semibold" href="/logout">
                                        <i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión
                                    </a>
                                </li>
                            </ul>
                        </li>

                    <?php else: ?>
                        <li class="nav-item ms-lg-3">
                            <a class="btn btn-iori btn-sm px-4 py-2 rounded-pill shadow-sm fw-bold" href="/login">Iniciar
                                Sesión</a>
                        </li>
                    <?php endif; ?>

                </ul>
            </div>
        </div>
    </header>