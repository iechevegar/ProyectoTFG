<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/funciones.php'; // Importamos la librería de utilidades para el parseo de cadenas (Slugs)

// =========================================================================================
// 1. MIDDLEWARE DE AUTENTICACIÓN (AUTH CHECK)
// =========================================================================================
// Protegemos la ruta: solo usuarios registrados pueden iniciar un nuevo tema de debate.
if (!isset($_SESSION['usuario'])) {
    header("Location: /login");
    exit();
}

$error = '';
$nombreUser = $_SESSION['usuario'];


// =========================================================================================
// 2. VERIFICACIÓN DE ESTADO DE CUENTA (SISTEMA DE BANEOS)
// =========================================================================================
// Usamos get_estado_usuario() que internamente usa prepared statement (Anti-SQLi).
$estadoUser = get_estado_usuario($conn);
$userId = $estadoUser['id'];

if (!$userId) {
    header("Location: /login");
    exit();
}

if ($estadoUser['suspendido']) {
    header("Location: /foro?error=cuenta_suspendida");
    exit();
}


// =========================================================================================
// 3. PROCESAMIENTO DEL PAYLOAD (CREACIÓN DE TEMA)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitización básica de los inputs del cliente eliminando espacios en blanco innecesarios
    $titulo = trim($_POST['titulo']);
    $categoria = $_POST['categoria'];
    $contenido = trim($_POST['contenido']);
    
    // Validación de negocio: El título y el contenido son campos obligatorios
    if (!empty($titulo) && !empty($contenido)) {

        // --- ALGORITMO DE GENERACIÓN DE RUTAS SEMÁNTICAS (SLUGS) ---
        // Transformamos el título (ej: "¿Qué opináis de Solo Leveling?") en una URL amigable
        // (ej: "que-opinais-de-solo-leveling") para mejorar el SEO y el enrutamiento.
        $slug = limpiarURL($titulo);
        
        // Comprobación de colisión de slug con prepared statement (Anti-SQLi).
        $stmtSlugT = $conn->prepare("SELECT id FROM foro_temas WHERE slug = ?");
        $stmtSlugT->bind_param("s", $slug);
        $stmtSlugT->execute();
        if ($stmtSlugT->get_result()->num_rows > 0) {
            $slug = $slug . '-' . rand(100, 999);
        }

        // --- PERSISTENCIA DE DATOS (PREPARED STATEMENTS) ---
        // Utilizamos sentencias preparadas para inyectar el contenido generado por el usuario.
        // Esto es CRÍTICO de cara a la seguridad para neutralizar ataques de Inyección SQL (SQLi).
        $stmt = $conn->prepare("INSERT INTO foro_temas (usuario_id, titulo, slug, contenido, categoria) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userId, $titulo, $slug, $contenido, $categoria);
        
        if ($stmt->execute()) {
            // Patrón PRG (Post/Redirect/Get)
            // Tras una mutación exitosa en la base de datos, redirigimos al cliente a la vista del recurso recién creado.
            // Esto evita reenvíos accidentales del formulario si el usuario pulsa "F5".
            header("Location: /foro/$slug");
            exit();
        } else {
            // Manejo de excepciones en caso de fallo del motor de base de datos
            $error = "Error interno al registrar el tema en la base de datos.";
        }
    } else {
        $error = "Fallo de validación: Se requiere rellenar todos los campos obligatorios.";
    }
}
?>
<?php include 'includes/header.php'; ?>

<main class="container py-5 foro-main-container">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">
            
            <div class="mb-4">
                <a href="/foro" class="text-decoration-none text-muted fw-bold hover-iori transition-colors">
                    <i class="fas fa-arrow-left me-1"></i> Volver al Foro principal
                </a>
            </div>

            <div class="card shadow-sm border-0 rounded-4 bg-white">
                
                <div class="card-header bg-white border-bottom pb-3 pt-4 px-4 px-md-5">
                    <h3 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <div class="bg-iori text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm foro-header-icon">
                            <i class="fas fa-comment-medical"></i>
                        </div>
                        Nuevo Tema de Discusión
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
                                <input type="text" name="titulo" class="form-control bg-light border-light shadow-sm foro-input-style" placeholder="Ej: ¿Qué opináis sobre el final de...?" maxlength="150" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Categoría *</label>
                                <select name="categoria" class="form-select bg-light border-light shadow-sm fw-semibold text-dark foro-input-style" style="cursor: pointer;">
                                    <option value="General">General</option>
                                    <option value="Teorías">Teorías</option>
                                    <option value="Noticias">Noticias</option>
                                    <option value="Recomendaciones">Recomendaciones</option>
                                    <option value="Off-Topic">Off-Topic</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Mensaje *</label>
                            <textarea name="contenido" class="form-control bg-light border-light shadow-sm foro-textarea-style" rows="8" placeholder="Escribe aquí tu teoría, duda o recomendación de forma detallada..." required></textarea>
                            <div class="form-text mt-2 text-muted fw-semibold" style="font-size: 0.85rem;">
                                <i class="fas fa-info-circle text-iori me-1"></i> Fomenta el debate constructivo. Sé respetuoso con el resto de la comunidad.
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-3 border-top pt-4">
                            <a href="/foro" class="btn bg-light text-dark border hover-bg-light fw-bold px-4 rounded-pill shadow-sm transition-colors">Cancelar</a>
                            <button type="submit" class="btn btn-iori fw-bold px-4 shadow-sm rounded-pill">
                                <i class="fas fa-paper-plane me-2"></i>Publicar Tema
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>