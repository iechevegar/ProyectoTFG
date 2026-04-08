<?php
session_start();
require 'includes/db.php';

// SEGURIDAD
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /login");
    exit();
}

// 1. ESTADÍSTICAS PARA EL DASHBOARD
$total_usuarios = $conn->query("SELECT COUNT(*) as total FROM usuarios")->fetch_assoc()['total'];
$total_obras = $conn->query("SELECT COUNT(*) as total FROM obras")->fetch_assoc()['total'];
$total_caps = $conn->query("SELECT COUNT(*) as total FROM capitulos")->fetch_assoc()['total'];
$total_visitas = $conn->query("SELECT SUM(visitas) as total FROM obras")->fetch_assoc()['total'];
if (!$total_visitas)
    $total_visitas = 0;

// 2. LISTA DE OBRAS (Tu tabla de siempre)
$sql = "SELECT * FROM obras ORDER BY id DESC";
$resultado = $conn->query($sql);
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-tachometer-alt text-primary"></i> Panel de Control</h2>

        <div class="d-flex gap-2">
            <a href="/admin_moderacion" class="btn btn-danger text-white shadow-sm fw-bold">
                <i class="fas fa-shield-alt me-2"></i>Moderación
            </a>

            <a href="/admin_usuarios" class="btn btn-dark text-white shadow-sm">
                <i class="fas fa-users-cog me-2"></i>Usuarios
            </a>
            <a href="/agregar_obra" class="btn btn-success text-white shadow-sm">
                <i class="fas fa-plus me-2"></i>Nueva Obra
            </a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-5">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">Usuarios</div>
                            <div class="h3 mb-0 fw-bold text-primary"><?php echo $total_usuarios; ?></div>
                        </div>
                        <i class="fas fa-users fa-2x text-gray-300 opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">Obras</div>
                            <div class="h3 mb-0 fw-bold text-success"><?php echo $total_obras; ?></div>
                        </div>
                        <i class="fas fa-book fa-2x text-gray-300 opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">Capítulos</div>
                            <div class="h3 mb-0 fw-bold text-info"><?php echo $total_caps; ?></div>
                        </div>
                        <i class="fas fa-layer-group fa-2x text-gray-300 opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">Visitas Totales</div>
                            <div class="h3 mb-0 fw-bold text-warning"><?php echo number_format($total_visitas); ?></div>
                        </div>
                        <i class="fas fa-eye fa-2x text-gray-300 opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <h4 class="mb-3 border-bottom pb-2">Gestión del Catálogo</h4>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Portada</th>
                            <th>Título</th>
                            <th>Autor</th>
                            <th>Géneros</th>
                            <th>Visitas</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado->num_rows > 0): ?>
                            <?php while ($obra = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php 
                                            // Aseguramos ruta absoluta para la portada
                                            $imgPortada = (strpos($obra['portada'], 'http') === 0) ? $obra['portada'] : '/' . ltrim($obra['portada'], '/');
                                        ?>
                                        <img src="<?php echo htmlspecialchars($imgPortada); ?>" class="rounded shadow-sm" width="50"
                                            height="75" style="object-fit: cover;">
                                    </td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($obra['titulo']); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($obra['autor']); ?></td>
                                    <td>
                                        <?php
                                        $tags = explode(',', $obra['generos']);
                                        $tags = array_slice($tags, 0, 2); // Solo mostrar 2 etiquetas
                                        foreach ($tags as $t) {
                                            echo "<span class='badge bg-secondary me-1' style='font-size:0.7em'>" . htmlspecialchars(trim($t)) . "</span>";
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="far fa-eye me-1"></i> <?php echo $obra['visitas']; ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="/ver_capitulos?id=<?php echo $obra['id']; ?>"
                                            class="btn btn-sm btn-info text-white me-1" title="Gestionar Capítulos">
                                            <i class="fas fa-layer-group"></i>
                                        </a>

                                        <a href="/editar_obra?id=<?php echo $obra['id']; ?>"
                                            class="btn btn-sm btn-warning text-white me-1" title="Editar">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>

                                        <form method="POST" action="/borrar_obra" class="d-inline"
                                            onsubmit="return confirm('¿Borrar esta obra?');">
                                            <input type="hidden" name="id" value="<?php echo $obra['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i
                                                    class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <p class="text-muted">No hay obras registradas.</p>
                                    <a href="/agregar_obra" class="btn btn-primary">Añadir la primera</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>