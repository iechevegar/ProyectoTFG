<?php
session_start();
require_once 'includes/db.php';

// =========================================================================================
// 1. MIDDLEWARE DE AUTENTICACIÓN Y VALIDACIÓN DE ESTADO
// =========================================================================================
// Comprobación combinada: Se requiere un token de sesión activo y el parámetro 'id'
// del recurso objetivo. Si falta alguno, abortamos y devolvemos al índice del foro.
if (!isset($_SESSION['usuario']) || !isset($_GET['id'])) {
    header("Location: /foro");
    exit();
}

$idResp = intval($_GET['id']);
$nombreUser = $_SESSION['usuario'];

// =========================================================================================
// 2. CONTROL DE AUTORIZACIÓN (OWNERSHIP) Y EXTRACCIÓN DE CONTEXTO
// =========================================================================================
// OPTIMIZACIÓN SQL: En lugar de realizar múltiples consultas secuenciales, implementamos 
// una consulta relacional mediante INNER JOINs. Esto nos permite en una sola operación atómica:
// 1. Recuperar el payload del mensaje original.
// 2. Validar a nivel de base de datos que el usuario solicitante es el propietario legítimo 
//    del recurso (cláusula WHERE u.nombre = ?).
// 3. Extraer metadatos del tema padre (slug y título) indispensables para el enrutamiento y la UI.
$sql = "SELECT r.*, t.slug as tema_slug, t.titulo as tema_titulo 
        FROM foro_respuestas r 
        JOIN usuarios u ON r.usuario_id = u.id 
        JOIN foro_temas t ON r.tema_id = t.id
        WHERE r.id = ? AND u.nombre = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $idResp, $nombreUser);
$stmt->execute();
$resp = $stmt->get_result()->fetch_assoc();

// =========================================================================================
// 3. PROCESAMIENTO DE MUTACIÓN DE DATOS (MÉTODO POST)
// =========================================================================================
// Procesamos la actualización antes del bloque Fail-Fast y del HTML para asegurar
// que no se envíen cabeceras HTTP prematuras, permitiendo la redirección (header location).
if ($_SERVER["REQUEST_METHOD"] == "POST" && $resp) {
    $mensaje = trim($_POST['mensaje']);
    
    if (!empty($mensaje)) {
        // AUDIT TRAIL: Al mutar el registro, actualizamos explícitamente el timestamp 
        // de 'fecha_edicion' mediante la función nativa NOW() de MySQL. Esto aporta 
        // transparencia a la comunidad del foro indicando que el mensaje fue alterado.
        $sqlUp = "UPDATE foro_respuestas SET mensaje=?, fecha_edicion=NOW() WHERE id=?";
        $stmtUp = $conn->prepare($sqlUp);
        $stmtUp->bind_param("si", $mensaje, $idResp);
        
        if ($stmtUp->execute()) {
            // Patrón PRG (Post/Redirect/Get) implementado para enrutamiento semántico.
            // Utilizamos urlencode() sobre el slug recuperado como medida de seguridad pasiva 
            // frente a posibles caracteres especiales no parseados en la URL.
            header("Location: /foro/" . urlencode($resp['tema_slug']));
            exit();
        }
    }
}

// =========================================================================================
// 4. FALL-FAST DE SEGURIDAD (BARRERA DE ACCESO)
// =========================================================================================
// Si el objeto $resp está vacío, implica que el mensaje no existe o que un usuario malicioso 
// (B-IDOR / Insecure Direct Object Reference) está intentando editar el ID de otro usuario.
// Detenemos el hilo de ejecución de PHP instantáneamente.
if (!$resp) {
    die("<div style='text-align:center; margin-top: 50px; font-family: sans-serif;'><h2>Fallo de Autorización: No tienes permisos para mutar este recurso.</h2><a href='/foro'>Volver al foro</a></div>");
}

?>
<?php include 'includes/header.php'; ?>

<main class="container py-5 foro-main-container">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">
            
            <div class="mb-4">
                <a href="/foro/<?php echo urlencode($resp['tema_slug']); ?>" class="text-decoration-none text-muted fw-bold hover-iori transition-colors">
                    <i class="fas fa-arrow-left me-1"></i> Volver a la discusión
                </a>
            </div>

            <div class="card shadow-sm border-0 rounded-4 bg-white">
                
                <div class="card-header bg-white border-bottom pb-3 pt-4 px-4 px-md-5">
                    <h3 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <div class="bg-iori text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm foro-header-icon">
                            <i class="fas fa-pencil-alt"></i>
                        </div>
                        Editar Respuesta
                    </h3>
                    <p class="text-muted mt-2 mb-0 small"><span class="fw-semibold">En el tema:</span> <?php echo htmlspecialchars($resp['tema_titulo']); ?></p>
                </div>

                <div class="card-body p-4 p-md-5">
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Mensaje *</label>
                            <textarea name="mensaje" class="form-control bg-light border-light shadow-sm foro-textarea-style" rows="8" required><?php echo htmlspecialchars($resp['mensaje']); ?></textarea>
                            <div class="form-text mt-2 text-muted fw-semibold" style="font-size: 0.85rem;">
                                <i class="fas fa-info-circle text-iori me-1"></i> Al guardar, la plataforma adjuntará una marca de "Editado el...".
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-3 border-top pt-4">
                            <a href="/foro/<?php echo urlencode($resp['tema_slug']); ?>" class="btn bg-light text-dark border hover-bg-light fw-bold px-4 rounded-pill shadow-sm text-secondary transition-colors">Cancelar</a>
                            <button type="submit" class="btn btn-iori fw-bold px-4 shadow-sm rounded-pill">
                                <i class="fas fa-save me-2"></i>Actualizar Respuesta
                            </button>
                        </div>
                    </form>

                </div>
            </div>
            
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>