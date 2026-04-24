<?php
session_start();
require 'includes/db.php';
require 'includes/funciones.php'; // Importamos librería para normalización de cadenas (Slugs)

// =========================================================================================
// 1. MIDDLEWARE DE AUTENTICACIÓN Y RECEPCIÓN DE PARÁMETROS
// =========================================================================================
// Requerimos sesión activa y el identificador del recurso. Aplicamos el patrón Fail-Fast.
if (!isset($_SESSION['usuario']) || !isset($_GET['id'])) {
    header("Location: /foro");
    exit();
}

$idTema = intval($_GET['id']);
$nombreUser = $_SESSION['usuario'];
$esAdmin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');
$error = '';

// =========================================================================================
// 2. CONTROL DE AUTORIZACIÓN BASADO EN ROLES (RBAC) Y PROPIEDAD (OWNERSHIP)
// =========================================================================================
// Diseñamos la consulta SQL de forma dinámica dependiendo del nivel de privilegios del usuario:
// - Si es administrador (Global Access), puede extraer y mutar cualquier tema.
// - Si es usuario estándar (Restricted Access), forzamos la cláusula "AND u.nombre = ?"
//   para garantizar a nivel de base de datos que solo extraiga el registro si es el propietario.
if ($esAdmin) {
    $sql = "SELECT t.*, u.nombre FROM foro_temas t JOIN usuarios u ON t.usuario_id = u.id WHERE t.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idTema);
} else {
    $sql = "SELECT t.*, u.nombre FROM foro_temas t JOIN usuarios u ON t.usuario_id = u.id WHERE t.id = ? AND u.nombre = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $idTema, $nombreUser);
}

$stmt->execute();
$tema = $stmt->get_result()->fetch_assoc();

// Bloqueo final: Si la consulta no devuelve resultados, significa que el usuario está
// intentando acceder a un ID que no le pertenece (prevención B-IDOR) o que el tema no existe.
if (!$tema) {
    die("<div class='container text-center mt-5'><h2>Fallo de Autorización: No tienes permisos para editar este tema.</h2><a href='/foro' class='btn btn-outline-primary mt-3'>Volver al foro</a></div>");
}


// =========================================================================================
// 3. PROCESAMIENTO DEL PAYLOAD Y MUTACIÓN DE DATOS (POST)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitización básica para evitar cadenas compuestas únicamente por espacios
    $titulo = trim($_POST['titulo']);
    $categoria = $_POST['categoria'];
    $contenido = trim($_POST['contenido']);
    
    if (!empty($titulo) && !empty($contenido)) {
        
        // --- REGENERACIÓN DINÁMICA DE RUTAS SEMÁNTICAS (SLUGS) ---
        // Dado que el título puede cambiar en la edición, debemos recalcular la URL amigable 
        // para mantener la coherencia y el SEO del enrutamiento.
        $slug = limpiarURL($titulo);
        
        // Resolución de Colisiones: Comprobamos si el nuevo slug ya pertenece a otro tema.
        // NOTA ARQUITECTÓNICA: Es vital añadir "AND id != $idTema" para que el motor SQL no 
        // detecte una colisión consigo mismo en caso de que el usuario no modifique el título original.
        $check_slug = $conn->query("SELECT id FROM foro_temas WHERE slug = '$slug' AND id != $idTema");
        if ($check_slug && $check_slug->num_rows > 0) {
            $slug = $slug . '-' . rand(100, 999); // Inyección de entropía temporal
        }

        // Ejecución de la mutación. Usamos NOW() para registrar en el Audit Trail el momento
        // exacto de la última modificación (visibilidad y transparencia para la comunidad).
        $sqlUp = "UPDATE foro_temas SET titulo=?, slug=?, contenido=?, categoria=?, fecha_edicion=NOW() WHERE id=?";
        $stmtUp = $conn->prepare($sqlUp);
        $stmtUp->bind_param("ssssi", $titulo, $slug, $contenido, $categoria, $idTema);
        
        if ($stmtUp->execute()) {
            // Enrutamiento predictivo (Patrón PRG): Redirigimos empleando el nuevo Slug re-calculado.
            header("Location: /foro/" . urlencode($slug));
            exit();
        } else {
            $error = "Fallo transaccional: Error al actualizar el tema en el motor de base de datos.";
        }
    } else {
        $error = "Fallo de validación: Rellena todos los campos obligatorios.";
    }
}

// Prevención de errores de enrutamiento (Fallback): Si por algún motivo de legacy data el tema 
// carece de slug, generamos una ruta de retorno segura hacia el índice principal del foro.
$ruta_volver = !empty($tema['slug']) ? '/foro/' . urlencode($tema['slug']) : '/foro';
?>
<?php include 'includes/header.php'; ?>

<main class="container py-5 foro-main-container">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">
            
            <div class="mb-4">
                <a href="<?php echo $ruta_volver; ?>" class="text-decoration-none text-muted fw-bold hover-iori">
                    <i class="fas fa-arrow-left me-1"></i> Volver al Tema
                </a>
            </div>

            <div class="card shadow-sm border-0 rounded-4 bg-white">
                
                <div class="card-header bg-white border-bottom pb-3 pt-4 px-4 px-md-5">
                    <h3 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <div class="bg-iori text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm foro-header-icon">
                            <i class="fas fa-pencil-alt"></i>
                        </div>
                        Editar Tema de Discusión
                    </h3>
                </div>

                <div class="card-body p-4 p-md-5">
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger shadow-sm rounded-4 border-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="row mb-4">
                            <div class="col-md-8 mb-3 mb-md-0">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Título del Debate *</label>
                                <input type="text" name="titulo" class="form-control bg-light border-light shadow-sm foro-input-style" value="<?php echo htmlspecialchars($tema['titulo']); ?>" maxlength="150" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Categoría *</label>
                                <select name="categoria" class="form-select bg-light border-light shadow-sm fw-semibold text-dark foro-input-style">
                                    <?php 
                                    // Generación de matriz de categorías e inyección condicional del atributo 'selected'
                                    $cats = ['General', 'Teorías', 'Noticias', 'Recomendaciones', 'Off-Topic'];
                                    foreach($cats as $c) {
                                        $selected = ($c == $tema['categoria']) ? 'selected' : '';
                                        echo "<option value='$c' $selected>$c</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Mensaje *</label>
                            <textarea name="contenido" class="form-control bg-light border-light shadow-sm foro-textarea-style" rows="8" required><?php echo htmlspecialchars($tema['contenido']); ?></textarea>
                            <div class="form-text mt-2 text-muted fw-semibold" style="font-size: 0.85rem;">
                                <i class="fas fa-info-circle text-iori me-1"></i> Al guardar, el motor adjuntará la marca de "Editado el..." automáticamente.
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-3 border-top pt-4">
                            <a href="<?php echo $ruta_volver; ?>" class="btn bg-light text-dark border hover-bg-light fw-bold px-4 rounded-pill shadow-sm text-secondary border">Cancelar</a>
                            <button type="submit" class="btn btn-iori fw-bold px-4 shadow-sm rounded-pill text-white">
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