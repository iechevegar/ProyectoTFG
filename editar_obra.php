<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/funciones.php'; // Importamos librería de formateo (Slugs)

// =========================================================================================
// 1. MIDDLEWARE DE AUTENTICACIÓN (RBAC)
// =========================================================================================
// Bloqueamos el acceso al módulo de edición a cualquier usuario que no sea administrador.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /login");
    exit();
}


// =========================================================================================
// 2. RECUPERACIÓN DEL ESTADO ACTUAL (HYDRATION)
// =========================================================================================
// Requerimos imperativamente el ID de la obra por GET para saber qué registro vamos a editar.
if (!isset($_GET['id'])) {
    header("Location: /admin");
    exit();
}

$id = intval($_GET['id']);
$mensaje = '';

// Extracción de los metadatos principales de la obra mediante Prepared Statements (Anti-SQLi)
$sql = "SELECT * FROM obras WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();
$obra = $resultado->fetch_assoc();

// Patrón Fail-Fast: Si el ID proporcionado no existe en la BD, abortamos la carga de la vista.
if (!$obra) {
    die("Error 404: Obra no encontrada en el registro de la base de datos.");
}

// --- RECUPERACIÓN DE RELACIONES MANY-TO-MANY (M:N) ---
// Extraemos los géneros que tiene asignados actualmente la obra desde la tabla pivote.
// Guardamos estos IDs en un array plano para poder pre-marcar (checked) los checkboxes en la UI.
$sql_gen_actuales = "SELECT genero_id FROM obra_genero WHERE obra_id = ?";
$stmt_gen = $conn->prepare($sql_gen_actuales);
$stmt_gen->bind_param("i", $id);
$stmt_gen->execute();
$res_gen_actuales = $stmt_gen->get_result();
$generos_actuales = [];
while ($row_g = $res_gen_actuales->fetch_assoc()) {
    $generos_actuales[] = $row_g['genero_id'];
}

// Precargamos el catálogo completo de géneros disponibles en el sistema para renderizar el formulario.
$resGeneros = $conn->query("SELECT * FROM generos ORDER BY nombre ASC");


// =========================================================================================
// 3. PROCESAMIENTO DEL PAYLOAD DE ACTUALIZACIÓN (POST)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify('/editar_obra');

    $titulo             = trim($_POST['titulo']);
    $autor              = trim($_POST['autor']);
    $sinopsis           = trim($_POST['sinopsis']);
    $tipo_obra          = trim($_POST['tipo_obra']);
    $demografia         = trim($_POST['demografia']);
    $estado_publicacion = trim($_POST['estado_publicacion']);

    // --- RECALCULO DE SLUG ---
    $slug = limpiarURL($titulo);

    // Comprobación de colisión excluyendo la propia obra (prepared statement).
    $stmtSlug = $conn->prepare("SELECT id FROM obras WHERE slug = ? AND id != ?");
    $stmtSlug->bind_param("si", $slug, $id);
    $stmtSlug->execute();
    if ($stmtSlug->get_result()->num_rows > 0) {
        $slug = $slug . '-' . rand(100, 999);
    }

    // --- MANTENIMIENTO DEL FILE SYSTEM (UPDATE CONDICIONAL) ---
    // Por defecto, conservamos la ruta de la portada que ya estaba guardada en la base de datos.
    $ruta_portada = $obra['portada'];
    
    // Solo si el usuario adjunta un NUEVO archivo en el input=file sin errores, procesamos el reemplazo.
    if (isset($_FILES['portada']) && $_FILES['portada']['error'] === 0) {
        $nombre_archivo = time() . "_" . $_FILES['portada']['name']; // Cache busting
        $ruta_destino = "assets/img/portadas/" . $nombre_archivo;
        
        if (move_uploaded_file($_FILES['portada']['tmp_name'], $ruta_destino)) {
            $ruta_portada = $ruta_destino;
            // NOTA: Para una optimización I/O estricta, aquí se podría implementar un unlink() 
            // de la imagen antigua ($obra['portada']) para no dejar basura en el servidor.
        }
    }

    // --- PERSISTENCIA: ACTUALIZACIÓN DE LA ENTIDAD PADRE ---
    $sql_update = "UPDATE obras SET titulo=?, slug=?, autor=?, sinopsis=?, portada=?, tipo_obra=?, demografia=?, estado_publicacion=? WHERE id=?";
    $stmt_up = $conn->prepare($sql_update);
    $stmt_up->bind_param("ssssssssi", $titulo, $slug, $autor, $sinopsis, $ruta_portada, $tipo_obra, $demografia, $estado_publicacion, $id);

    if ($stmt_up->execute()) {

        // --- PERSISTENCIA: ACTUALIZACIÓN DE ENTIDADES RELACIONADAS (WIPE & REPLACE) ---
        // Wipe & Replace de la tabla pivote con prepared statement.
        $stmtDel = $conn->prepare("DELETE FROM obra_genero WHERE obra_id = ?");
        $stmtDel->bind_param("i", $id);
        $stmtDel->execute();

        if (isset($_POST['generos']) && is_array($_POST['generos'])) {
            $stmt_insert_gen = $conn->prepare("INSERT INTO obra_genero (obra_id, genero_id) VALUES (?, ?)");
            foreach ($_POST['generos'] as $gen_id) {
                $g_id = intval($gen_id);
                $stmt_insert_gen->bind_param("ii", $id, $g_id);
                $stmt_insert_gen->execute();
            }
        }

        // Finalizamos la transacción con un redireccionamiento PRG (Post/Redirect/Get) al panel.
        header("Location: /admin?msg=" . urlencode("Obra actualizada correctamente en el catálogo."));
        exit();
    } else {
        $mensaje = "<div class='alert alert-danger shadow-sm'><i class='fas fa-exclamation-triangle me-2'></i> Error interno al ejecutar la sentencia UPDATE.</div>";
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4 admin-main-container">
    <div class="row justify-content-center">
        <div class="col-md-10">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="/admin" class="text-decoration-none text-muted mb-1 d-inline-block hover-iori transition-colors">
                        <i class="fas fa-arrow-left"></i> Volver al Panel
                    </a>
                    <h2 class="fw-bold text-dark m-0"><i class="fas fa-edit text-warning me-2"></i> Editar Obra</h2>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card shadow-sm border-0 border-top border-warning border-3 bg-white rounded-4">
                <div class="card-body p-4">
                    
                    <form method="POST" action="" enctype="multipart/form-data">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Título</label>
                                <input type="text" name="titulo" class="form-control bg-light border-light shadow-sm"
                                    value="<?php echo htmlspecialchars($obra['titulo']); ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Autor</label>
                                <input type="text" name="autor" class="form-control bg-light border-light shadow-sm"
                                    value="<?php echo htmlspecialchars($obra['autor']); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-4 bg-light p-3 rounded-4 border border-light shadow-sm mx-0">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Tipo de Obra</label>
                                <select name="tipo_obra" class="form-select bg-white border-secondary shadow-sm">
                                    <option value="Desconocido" <?php echo ($obra['tipo_obra'] == 'Desconocido') ? 'selected' : ''; ?>>Seleccionar Tipo...</option>
                                    <option value="Manga" <?php echo ($obra['tipo_obra'] == 'Manga') ? 'selected' : ''; ?>>Manga (Japonés)</option>
                                    <option value="Manhwa" <?php echo ($obra['tipo_obra'] == 'Manhwa') ? 'selected' : ''; ?>>Manhwa (Coreano)</option>
                                    <option value="Manhua" <?php echo ($obra['tipo_obra'] == 'Manhua') ? 'selected' : ''; ?>>Manhua (Chino)</option>
                                    <option value="Donghua" <?php echo ($obra['tipo_obra'] == 'Donghua') ? 'selected' : ''; ?>>Donghua (Animación)</option>
                                    <option value="Novela" <?php echo ($obra['tipo_obra'] == 'Novela') ? 'selected' : ''; ?>>Novela / Libro</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3 mb-md-0">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Demografía</label>
                                <select name="demografia" class="form-select bg-white border-secondary shadow-sm">
                                    <option value="Desconocido" <?php echo ($obra['demografia'] == 'Desconocido') ? 'selected' : ''; ?>>Seleccionar Demografía...</option>
                                    <option value="Shounen" <?php echo ($obra['demografia'] == 'Shounen') ? 'selected' : ''; ?>>Shounen (Jóvenes)</option>
                                    <option value="Seinen" <?php echo ($obra['demografia'] == 'Seinen') ? 'selected' : ''; ?>>Seinen (Adultos)</option>
                                    <option value="Shoujo" <?php echo ($obra['demografia'] == 'Shoujo') ? 'selected' : ''; ?>>Shoujo (Romance Joven)</option>
                                    <option value="Josei" <?php echo ($obra['demografia'] == 'Josei') ? 'selected' : ''; ?>>Josei (Romance Adulto)</option>
                                    <option value="Kodomo" <?php echo ($obra['demografia'] == 'Kodomo') ? 'selected' : ''; ?>>Kodomo (Infantil)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-warning small text-uppercase">Estado *</label>
                                <select name="estado_publicacion" class="form-select border-warning bg-white shadow-sm">
                                    <option value="En Emisión" <?php echo ($obra['estado_publicacion'] == 'En Emisión' || empty($obra['estado_publicacion'])) ? 'selected' : ''; ?>>En Emisión</option>
                                    <option value="Hiatus" <?php echo ($obra['estado_publicacion'] == 'Hiatus') ? 'selected' : ''; ?>>Hiatus (Pausado)</option>
                                    <option value="Finalizado" <?php echo ($obra['estado_publicacion'] == 'Finalizado') ? 'selected' : ''; ?>>Finalizado</option>
                                    <option value="Cancelado" <?php echo ($obra['estado_publicacion'] == 'Cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase mb-3">Géneros de la obra *</label>
                            <div class="row bg-light p-3 rounded-4 border border-light shadow-sm mx-0">
                                <?php
                                if ($resGeneros->num_rows > 0) {
                                    $resGeneros->data_seek(0);
                                    while ($g = $resGeneros->fetch_assoc()):
                                        // Evaluamos in-time si el ID del género renderizado está presente 
                                        // en el array plano de géneros actuales que extrajimos en el paso 2.
                                        $isChecked = in_array($g['id'], $generos_actuales) ? 'checked' : '';
                                        ?>
                                        <div class="col-md-3 col-sm-4 col-6 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input border-secondary" type="checkbox"
                                                    name="generos[]" value="<?php echo $g['id']; ?>"
                                                    id="gen_<?php echo $g['id']; ?>" <?php echo $isChecked; ?>>
                                                <label class="form-check-label text-dark fw-semibold" for="gen_<?php echo $g['id']; ?>">
                                                    <?php echo htmlspecialchars($g['nombre']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php
                                    endwhile;
                                }
                                ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Sinopsis</label>
                            <textarea name="sinopsis" class="form-control bg-light border-light shadow-sm"
                                rows="5"><?php echo htmlspecialchars($obra['sinopsis']); ?></textarea>
                        </div>

                        <div class="row mb-4 p-3 bg-light rounded-4 border border-light shadow-sm mx-0">
                            <div class="col-md-4 text-center text-md-start mb-3 mb-md-0">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Portada Actual</label><br>
                                <?php
                                // Resolución dual: Soporta tanto rutas relativas internas como URLs de CDNs/APIs externas
                                $imgActual = (strpos($obra['portada'], 'http') === 0) ? $obra['portada'] : '/' . ltrim($obra['portada'], '/');
                                ?>
                                <img src="<?php echo htmlspecialchars($imgActual); ?>" class="img-thumbnail shadow-sm admin-edit-cover bg-white" alt="Portada vigente">
                            </div>
                            <div class="col-md-8 d-flex flex-column justify-content-center">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Cambiar Portada (Opcional)</label>
                                <input type="file" name="portada" class="form-control bg-white border-secondary shadow-sm" accept="image/*">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-3 border-top pt-4 mt-2">
                            <a href="/admin" class="btn bg-light text-dark border hover-bg-light fw-bold px-4 rounded-pill shadow-sm transition-colors">Cancelar</a>
                            <button type="submit" class="btn btn-warning text-dark fw-bold px-4 rounded-pill shadow-sm">
                                <i class="fas fa-save me-2"></i>Guardar Cambios
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>