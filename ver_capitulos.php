<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. MIDDLEWARE DE AUTORIZACIÓN (RBAC)
// =========================================================================================
// Dado que el frontend público es de solo lectura (Read-Only), este panel de administración 
// actúa como la única pasarela de mutación de datos. Restringimos el acceso estrictamente a administradores.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /login");
    exit();
}

// =========================================================================================
// 2. VALIDACIÓN DE PARÁMETROS Y SANEAMIENTO (ANTI-SQLi)
// =========================================================================================
// Verificamos la existencia del parámetro identificador en la URL.
if (!isset($_GET['id'])) {
    header("Location: /admin");
    exit();
}

// TYPE CASTING: Forzamos la conversión del parámetro GET a un número entero mediante intval().
// Esta es una técnica de saneamiento muy robusta; si un atacante intenta inyectar 
// código SQL (ej: ?id=5 OR 1=1), PHP lo transformará automáticamente en un 5, neutralizando el ataque.
$idObra = intval($_GET['id']);


// =========================================================================================
// 3. EXTRACCIÓN DE DATOS Y PATRÓN FAIL-FAST
// =========================================================================================
// Obtenemos los metadatos de la obra padre para construir la interfaz contextual.
$sqlObra = "SELECT titulo, slug, portada FROM obras WHERE id = $idObra";
$resObra = $conn->query($sqlObra);
$obra = $resObra->fetch_assoc();

// Patrón Fail-Fast: Si el ID proporcionado es válido numéricamente pero no existe en la BD 
// (ej: fue borrado por otro admin), abortamos la ejecución de la UI inmediatamente y mostramos un error.
if (!$obra) {
    die("<div class='container text-center mt-5'><h2>Error 404: Obra no encontrada en el catálogo.</h2><a href='/admin' class='btn btn-primary mt-3'>Volver al panel</a></div>");
}

// Obtenemos el catálogo de capítulos vinculados a esta obra. 
// Ordenamos de forma descendente (ORDER BY id DESC) para mostrar los lanzamientos más recientes primero.
$sqlCaps = "SELECT * FROM capitulos WHERE obra_id = $idObra ORDER BY id DESC"; 
$resCaps = $conn->query($sqlCaps);
?>

<?php include 'includes/header.php'; ?>

<main class="container py-5 admin-main-container">
    
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="row g-0 align-items-center">
                <div class="col-auto d-none d-md-block">
                    <?php 
                        // Resolución de rutas dinámicas (locales vs externas)
                        $imgPortada = (strpos($obra['portada'], 'http') === 0) ? $obra['portada'] : '/' . ltrim($obra['portada'], '/');
                    ?>
                    <img src="<?php echo htmlspecialchars($imgPortada); ?>" alt="Portada" class="admin-cap-cover">
                </div>
                
                <div class="col p-4">
                    <div class="mb-2">
                        <a href="/admin" class="text-decoration-none text-muted small fw-bold hover-iori text-uppercase transition-colors">
                            <i class="fas fa-arrow-left me-1"></i> Volver al Panel Principal
                        </a>
                    </div>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                        <div>
                            <h2 class="fw-bold text-dark m-0 display-6 fs-3">Gestión de Capítulos</h2>
                            <p class="text-iori fw-bold fs-5 mb-0 mt-1"><?php echo htmlspecialchars($obra['titulo']); ?></p>
                        </div>
                        <div class="mt-3 mt-md-0">
                            <a href="/agregar_capitulo?id=<?php echo $idObra; ?>" class="btn btn-iori fw-bold shadow-sm rounded-pill px-4 py-2">
                                <i class="fas fa-plus-circle me-2"></i>Añadir Nuevo Capítulo
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4 border-success mb-4">
            <i class="fas fa-check-circle me-2 text-success"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden bg-white">
        <div class="table-responsive">
            <table class="table align-middle mb-0 border-white admin-table-hover">
                <thead class="text-secondary small text-uppercase bg-light">
                    <tr>
                        <th class="ps-4 py-3 border-0">Título del Capítulo</th>
                        <th class="py-3 border-0">Páginas</th>
                        <th class="py-3 border-0">Fecha de Subida</th>
                        <th class="text-end pe-4 py-3 border-0">Acciones</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php if ($resCaps->num_rows > 0): ?>
                        <?php while ($cap = $resCaps->fetch_assoc()): ?>
                            <?php
                            // OPTIMIZACIÓN SCHEMALESS: En lugar de tener una tabla separada para contar páginas,
                            // decodificamos el payload JSON de la columna 'contenido' al vuelo y contamos los nodos.
                            // Esto ahorra un JOIN masivo en la base de datos.
                            $imgs = json_decode($cap['contenido'], true);
                            $numPaginas = is_array($imgs) ? count($imgs) : 0;
                            ?>
                            <tr>
                                <td class="ps-4 py-3 border-light fw-bold text-dark fs-6">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light text-iori rounded-3 d-flex align-items-center justify-content-center me-3 admin-cap-icon">
                                            <i class="far fa-file-alt"></i>
                                        </div>
                                        <?php echo htmlspecialchars($cap['titulo']); ?>
                                    </div>
                                </td>
                                
                                <td class="py-3 border-light">
                                    <span class="badge bg-light text-secondary border border-secondary px-3 py-2 rounded-pill shadow-sm admin-badge-small">
                                        <i class="fas fa-images me-1"></i> <?php echo $numPaginas; ?> págs
                                    </span>
                                </td>
                                
                                <td class="py-3 border-light text-muted fw-semibold small">
                                    <i class="far fa-calendar-alt me-1"></i> <?php echo date('d M Y, H:i', strtotime($cap['fecha_subida'])); ?>
                                </td>
                                
                                <td class="text-end pe-4 py-3 border-light">
                                    <div class="d-flex justify-content-end gap-2">
                                        
                                        <a href="/obra/<?php echo urlencode($obra['slug']); ?>/<?php echo urlencode($cap['slug']); ?>"
                                            class="btn btn-sm btn-outline-primary border-0 bg-primary bg-opacity-10 rounded-circle admin-btn-circle transition-colors" title="Previsualizar en Visor" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <form method="POST" action="/borrar_capitulo" class="d-inline"
                                            onsubmit="return confirm('¿Borrar este capítulo? El motor purgará también los archivos del servidor de forma irreversible.');">

                                            <input type="hidden" name="id" value="<?php echo $cap['id']; ?>">
                                            <input type="hidden" name="obra_id" value="<?php echo $idObra; ?>">

                                            <button type="submit" class="btn btn-sm btn-soft-danger rounded-circle admin-btn-circle" title="Borrar Capítulo">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                        
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 bg-white">
                                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 admin-empty-state-icon">
                                    <i class="fas fa-folder-open fa-2x text-muted opacity-50"></i>
                                </div>
                                <h5 class="fw-bold text-dark">No hay capítulos subidos</h5>
                                <p class="text-muted mb-4">Esta obra todavía no tiene contenido disponible para los lectores.</p>
                                <a href="/agregar_capitulo?id=<?php echo $idObra; ?>" class="btn btn-iori fw-bold shadow-sm rounded-pill px-4">
                                    Subir el primer capítulo
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>