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
    <title>IoriScans</title>
    
    <link rel="stylesheet" href="/assets/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="/assets/css/styles.css?v=<?php echo time(); ?>">
</head>
<body class="d-flex flex-column min-vh-100"> 
    
<header class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="/">
            <img src="/assets/img/logo-ioriscans.png" alt="Logo" height="40" class="me-2" onerror="this.style.display='none';">
            <span class="text-iori">IoriScans</span>
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
                            <input type="text" name="q" class="form-control form-control-sm border-end-0 bg-light" placeholder="Buscar obra..." style="min-width: 200px;">
                            <button class="btn btn-light border border-start-0 btn-sm text-muted hover-iori" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </li>

                <?php if(isset($_SESSION['usuario'])): ?>
                    
                    <?php if(!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin'): ?>
                    <li class="nav-item me-2">
                        <a class="nav-link fw-bold text-iori" href="/biblioteca">
                            <i class="fas fa-bookmark me-1"></i> Biblioteca
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle btn btn-light border px-3 rounded-pill d-flex align-items-center gap-2 shadow-sm" href="#" role="button" data-bs-toggle="dropdown">
                            <?php if(isset($_SESSION['foto']) && !empty($_SESSION['foto'])): ?>
                                <img src="<?php echo htmlspecialchars($_SESSION['foto']); ?>" class="rounded-circle" width="24" height="24" style="object-fit: cover;">
                            <?php else: ?>
                                <i class="far fa-user text-iori"></i>
                            <?php endif; ?>
                            
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
                        </a>
                        
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                            <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                <li>
                                    <a class="dropdown-item text-iori fw-bold" href="/admin">
                                        <i class="fas fa-shield-alt me-2"></i>Panel Admin
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>

                            <li><a class="dropdown-item fw-semibold" href="/perfil">Mi Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger fw-semibold" href="/logout">
                                    <i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión
                                </a>
                            </li>
                        </ul>
                    </li>

                <?php else: ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-iori btn-sm px-4 py-2 rounded-pill shadow-sm fw-bold" href="/login">Iniciar Sesión</a>
                    </li>
                <?php endif; ?>

            </ul>
        </div>
    </div>
</header>