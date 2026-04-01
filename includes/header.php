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
    <title>LectorApp</title>
    
    <link rel="stylesheet" href="assets/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo time(); ?>">
</head>
<body class="d-flex flex-column min-vh-100"> 
    
<header class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="fas fa-book-open me-2"></i>LectorApp
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Catálogo</a>
                </li>
                <li class="nav-item me-3">
                    <a class="nav-link" href="foro.php">Foro</a>
                </li>

                <li class="nav-item me-3 d-none d-lg-block">
                    <form action="index.php" method="GET" class="d-flex">
                        <div class="input-group">
                            <input type="text" name="q" class="form-control form-control-sm border-end-0 rounded-start" placeholder="Buscar obra..." style="min-width: 200px;">
                            <button class="btn btn-outline-secondary btn-sm border-start-0 rounded-end bg-white" type="submit">
                                <i class="fas fa-search text-muted"></i>
                            </button>
                        </div>
                    </form>
                </li>

                <?php if(isset($_SESSION['usuario'])): ?>
                    
                    <?php if(!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin'): ?>
                    <li class="nav-item me-2">
                        <a class="nav-link fw-bold text-primary" href="biblioteca.php">
                            <i class="fas fa-bookmark me-1"></i> Biblioteca
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle btn btn-light border px-3 rounded-pill d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                            <?php if(isset($_SESSION['foto']) && !empty($_SESSION['foto'])): ?>
                                <img src="<?php echo $_SESSION['foto']; ?>" class="rounded-circle" width="24" height="24" style="object-fit: cover;">
                            <?php else: ?>
                                <i class="far fa-user"></i>
                            <?php endif; ?>
                            
                            <span><?php echo $_SESSION['usuario']; ?></span>
                        </a>
                        
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                <li>
                                    <a class="dropdown-item text-primary fw-bold" href="admin.php">
                                        <i class="fas fa-shield-alt me-2"></i>Panel Admin
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>

                            <li><a class="dropdown-item" href="perfil.php">Mi Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión
                                </a>
                            </li>
                        </ul>
                    </li>

                <?php else: ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-primary btn-sm px-4 rounded-pill" href="login.php">Iniciar Sesión</a>
                    </li>
                <?php endif; ?>

            </ul>
        </div>
    </div>
</header>