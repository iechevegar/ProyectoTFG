<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. CONTROL DE ACCESO BASADO EN ROLES (RBAC)
// =========================================================================================
// Blindamos el panel de control principal. Si la variable de sesión 'rol' no está 
// definida o no es estrictamente 'admin', abortamos la carga y redirigimos al login.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /login");
    exit();
}

// =========================================================================================
// 2. AGREGACIÓN DE MÉTRICAS GLOBALES (DASHBOARD)
// =========================================================================================
// Ejecutamos consultas de agregación (COUNT, SUM) para alimentar los KPIs del panel superior.
$total_usuarios = $conn->query("SELECT COUNT(*) as total FROM usuarios")->fetch_assoc()['total'];
$total_obras = $conn->query("SELECT COUNT(*) as total FROM obras")->fetch_assoc()['total'];
$total_caps = $conn->query("SELECT COUNT(*) as total FROM capitulos")->fetch_assoc()['total'];

// Controlamos el caso de que la suma de visitas devuelva NULL si la tabla obras está vacía
$total_visitas = $conn->query("SELECT SUM(visitas) as total FROM obras")->fetch_assoc()['total'];
if (!$total_visitas) {
    $total_visitas = 0;
}

// =========================================================================================
// 3. EXTRACCIÓN DE DATOS: CATÁLOGO DE OBRAS
// =========================================================================================
$sql = "SELECT o.*, 
        (SELECT GROUP_CONCAT(g.nombre SEPARATOR ',') 
         FROM obra_genero og 
         JOIN generos g ON og.genero_id = g.id 
         WHERE og.obra_id = o.id) as generos 
        FROM obras o 
        ORDER BY o.id DESC";
$resultado = $conn->query($sql);

// OPTIMIZACIÓN: En lugar de iterar directamente sobre el objeto $resultado de mysqli,
// volcamos los datos en un array asociativo en memoria ($obras_lista). 
// Esto nos permite implementar un diseño "Responsive Híbrido" reutilizando los mismos datos
// para pintar la tabla en escritorio y las tarjetas en móvil, ahorrando una segunda consulta a la BD.
$obras_lista = [];
if ($resultado->num_rows > 0) {
    while ($row = $resultado->fetch_assoc()) {
        $obras_lista[] = $row;
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4 admin-main-container">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <h2 class="fw-bold text-dark m-0"><i class="fas fa-tachometer-alt text-iori me-2"></i> Panel de Control</h2>

        <div class="d-flex flex-wrap gap-2">
            <a href="/admin_moderacion" class="btn btn-sm btn-danger text-white shadow-sm fw-bold rounded-pill px-3 py-2">
                <i class="fas fa-shield-alt me-1"></i>Moderación
            </a>
            <a href="/admin_usuarios" class="btn btn-sm btn-dark text-white shadow-sm fw-bold rounded-pill px-3 py-2">
                <i class="fas fa-users-cog me-1"></i>Usuarios
            </a>
            <a href="/agregar_obra" class="btn btn-sm btn-success text-white shadow-sm fw-bold rounded-pill px-3 py-2">
                <i class="fas fa-plus me-1"></i>Nueva Obra
            </a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4 border-success">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-5">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-primary h-100 bg-white rounded-4">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted fw-bold text-uppercase admin-stat-label">Usuarios</div>
                            <div class="h3 mb-0 fw-bold text-primary"><?php echo $total_usuarios; ?></div>
                        </div>
                        <i class="fas fa-users fa-2x text-primary opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-success h-100 bg-white rounded-4">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted fw-bold text-uppercase admin-stat-label">Obras</div>
                            <div class="h3 mb-0 fw-bold text-success"><?php echo $total_obras; ?></div>
                        </div>
                        <i class="fas fa-book fa-2x text-success opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-info h-100 bg-white rounded-4">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted fw-bold text-uppercase admin-stat-label">Capítulos</div>
                            <div class="h3 mb-0 fw-bold text-info"><?php echo $total_caps; ?></div>
                        </div>
                        <i class="fas fa-layer-group fa-2x text-info opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-warning h-100 bg-white rounded-4">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted fw-bold text-uppercase admin-stat-label">Visitas Totales</div>
                            <div class="h3 mb-0 fw-bold text-warning"><?php echo number_format($total_visitas); ?></div>
                        </div>
                        <i class="fas fa-eye fa-2x text-warning opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <h4 class="mb-3 border-bottom border-secondary border-2 pb-2 text-dark">Gestión del Catálogo</h4>

    <div class="card shadow-sm border-0 bg-white rounded-4 overflow-hidden d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 admin-table-hover">
                    <thead class="bg-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-4 border-0 py-3">Portada</th>
                            <th class="border-0 py-3">Título</th>
                            <th class="border-0 py-3">Autor</th>
                            <th class="border-0 py-3">Géneros</th>
                            <th class="border-0 py-3">Visitas</th>
                            <th class="text-end pe-4 border-0 py-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php if (count($obras_lista) > 0): ?>
                            <?php foreach ($obras_lista as $obra): ?>
                                <tr>
                                    <td class="ps-4 border-light">
                                        <?php 
                                            // Resolución de rutas absolutas vs relativas para las portadas
                                            $imgPortada = (strpos($obra['portada'], 'http') === 0) ? $obra['portada'] : '/' . ltrim($obra['portada'], '/');
                                        ?>
                                        <img src="<?php echo htmlspecialchars($imgPortada); ?>" class="rounded shadow-sm admin-cover-img" alt="Portada">
                                    </td>
                                    <td class="fw-bold text-dark border-light fs-6"><?php echo htmlspecialchars($obra['titulo']); ?></td>
                                    <td class="text-muted small border-light fw-semibold"><?php echo htmlspecialchars($obra['autor']); ?></td>
                                    <td class="border-light">
                                        <?php
                                        $tags = explode(',', $obra['generos']);
                                        $tags = array_slice($tags, 0, 2); // Restringimos visualmente a un máximo de 2 etiquetas para no romper el layout de la tabla
                                        foreach ($tags as $t) {
                                            if (trim($t) !== '') {
                                                echo "<span class='badge bg-secondary text-white me-1 px-2 py-1 rounded-pill admin-badge-small'>" . htmlspecialchars(trim($t)) . "</span>";
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td class="border-light">
                                        <span class="badge bg-light text-dark border px-2 py-1 shadow-sm">
                                            <i class="far fa-eye me-1 text-iori"></i> <?php echo number_format($obra['visitas']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 border-light">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="/ver_capitulos?id=<?php echo $obra['id']; ?>" class="btn btn-sm btn-info text-white admin-btn-circle transition-colors rounded-circle shadow-sm" title="Gestionar Capítulos">
                                                <i class="fas fa-layer-group"></i>
                                            </a>
                                            <a href="/editar_obra?id=<?php echo $obra['id']; ?>" class="btn btn-sm btn-warning text-white admin-btn-circle transition-colors rounded-circle shadow-sm" title="Editar">
                                                <i class="fas fa-pencil-alt"></i>
                                            </a>
                                            <form method="POST" action="/borrar_obra" class="d-inline" onsubmit="return confirm('¿Seguro que quieres borrar esta obra completamente? Se perderán sus capítulos y reseñas.');">
                                                <input type="hidden" name="id" value="<?php echo $obra['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger text-white admin-btn-circle rounded-circle shadow-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 bg-white">
                                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 admin-empty-state-icon">
                                        <i class="fas fa-book fa-2x text-muted opacity-50"></i>
                                    </div>
                                    <h5 class="fw-bold text-dark">Catálogo vacío</h5>
                                    <p class="text-muted mb-4">Aún no has añadido ninguna obra a la plataforma.</p>
                                    <a href="/agregar_obra" class="btn btn-iori fw-bold shadow-sm rounded-pill px-4">Añadir la primera</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-md-none d-flex flex-column gap-3">
        <?php if (count($obras_lista) > 0): ?>
            <?php foreach ($obras_lista as $obra): ?>
                <div class="card shadow-sm border-0 rounded-4 bg-white">
                    <div class="card-body p-3">
                        
                        <div class="d-flex gap-3 mb-3">
                            <?php $imgPortadaMobile = (strpos($obra['portada'], 'http') === 0) ? $obra['portada'] : '/' . ltrim($obra['portada'], '/'); ?>
                            <img src="<?php echo htmlspecialchars($imgPortadaMobile); ?>" class="rounded-3 shadow-sm admin-mobile-cover" alt="Portada">
                            
                            <div class="flex-grow-1 overflow-hidden">
                                <h6 class="fw-bold text-dark mb-1 text-truncate admin-mobile-title"><?php echo htmlspecialchars($obra['titulo']); ?></h6>
                                <small class="text-muted fw-semibold d-block mb-2 text-truncate admin-mobile-author"><i class="fas fa-pen-nib me-1 opacity-50"></i><?php echo htmlspecialchars($obra['autor']); ?></small>
                                
                                <div class="d-flex flex-wrap gap-1 mb-2 admin-mobile-tags-container">
                                    <?php
                                    $tags = explode(',', $obra['generos']);
                                    $tags = array_slice($tags, 0, 2); 
                                    foreach ($tags as $t) {
                                        if (trim($t) !== '') echo "<span class='badge bg-secondary text-white me-1 px-2 py-1 rounded-pill admin-mobile-tag'>" . htmlspecialchars(trim($t)) . "</span>";
                                    }
                                    ?>
                                </div>
                                <span class="badge bg-light text-dark border px-2 shadow-sm admin-mobile-views">
                                    <i class="far fa-eye me-1 text-iori"></i> <?php echo number_format($obra['visitas']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="d-flex gap-2 border-top border-light pt-3">
                            <a href="/ver_capitulos?id=<?php echo $obra['id']; ?>" class="btn btn-sm btn-info text-white flex-grow-1 fw-bold rounded-pill shadow-sm" title="Gestionar Capítulos">
                                <i class="fas fa-layer-group me-1"></i> Caps
                            </a>
                            <a href="/editar_obra?id=<?php echo $obra['id']; ?>" class="btn btn-sm btn-warning text-white flex-grow-1 fw-bold rounded-pill shadow-sm" title="Editar">
                                <i class="fas fa-pencil-alt me-1"></i> Editar
                            </a>
                            <form method="POST" action="/borrar_obra" class="flex-grow-1 d-flex" onsubmit="return confirm('¿Seguro que quieres borrar esta obra completamente?');">
                                <input type="hidden" name="id" value="<?php echo $obra['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger text-white w-100 fw-bold rounded-pill shadow-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                <i class="fas fa-book fa-3x text-muted opacity-25 mb-3"></i>
                <h5 class="fw-bold text-dark">Catálogo vacío</h5>
                <p class="text-muted small">Añade tu primera obra.</p>
            </div>
        <?php endif; ?>
    </div>

</main>

<?php include 'includes/footer.php'; ?>