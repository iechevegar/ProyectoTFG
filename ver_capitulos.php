<?php
session_start();
require 'includes/db.php';

// SEGURIDAD
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// VALIDAR ID OBRA
if (!isset($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$idObra = intval($_GET['id']);

// OBTENER DATOS DE LA OBRA (Para el título)
$sqlObra = "SELECT titulo FROM obras WHERE id = $idObra";
$resObra = $conn->query($sqlObra);
$obra = $resObra->fetch_assoc();

if (!$obra)
    die("Obra no encontrada");

// OBTENER CAPÍTULOS
$sqlCaps = "SELECT * FROM capitulos WHERE obra_id = $idObra ORDER BY id DESC"; // Los más nuevos primero
$resCaps = $conn->query($sqlCaps);
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="admin.php" class="text-decoration-none text-muted mb-2 d-inline-block">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
            <h2>Gestión de: <span class="text-primary"><?php echo $obra['titulo']; ?></span></h2>
        </div>

        <a href="agregar_capitulo.php?id=<?php echo $idObra; ?>" class="btn btn-success">
            <i class="fas fa-plus me-2"></i>Nuevo Capítulo
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Título del Capítulo</th>
                        <th>Páginas</th>
                        <th>Fecha Subida</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resCaps->num_rows > 0): ?>
                        <?php while ($cap = $resCaps->fetch_assoc()): ?>
                            <?php
                            // Contar cuántas imágenes tiene (decodificando el JSON)
                            $imgs = json_decode($cap['contenido'], true);
                            $numPaginas = is_array($imgs) ? count($imgs) : 0;
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?php echo $cap['titulo']; ?></td>
                                <td><span class="badge bg-secondary"><?php echo $numPaginas; ?> págs</span></td>
                                <td class="text-muted small"><?php echo date('d/m/Y', strtotime($cap['fecha_subida'])); ?></td>
                                <td class="text-end pe-4">
                                    <a href="visor.php?obraId=<?php echo $idObra; ?>&capId=<?php echo $cap['id']; ?>&origen=admin"
                                        class="btn btn-sm btn-outline-primary me-1" title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <form method="POST" action="borrar_capitulo.php" class="d-inline"
                                        onsubmit="return confirm('¿Estás seguro de borrar este capítulo? Se eliminarán todas sus imágenes físicas.');">

                                        <input type="hidden" name="id" value="<?php echo $cap['id']; ?>">

                                        <input type="hidden" name="obra_id" value="<?php echo $idObra; ?>">

                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Borrar Capítulo">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fa-2x mb-3 d-block"></i>
                                No hay capítulos subidos en esta obra.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>