<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. CAPA DE SEGURIDAD PERIMETRAL (MIDDLEWARE)
// =========================================================================================
// Validación estricta de sesión. Al ser un panel de gestión de identidad privada,
// cortamos el acceso inmediatamente si el cliente no aporta credenciales válidas.
if (!isset($_SESSION['usuario'])) {
    header("Location: /login");
    exit();
}

$mensaje = '';
$tipo_mensaje = ''; 
$active_tab = 'config'; // Definición del estado de la interfaz por defecto

// Interceptor de variables GET para la inyección de Feedback Visual (Flash Messages)
// generados desde otros scripts (como borrar_cuenta.php).
if (isset($_GET['error'])) {
    $mensaje = trim($_GET['error']);
    $tipo_mensaje = 'danger';
}

// =========================================================================================
// 2. EXTRACCIÓN DE IDENTIDAD (HYDRATION)
// =========================================================================================
// Recuperamos la entidad completa del usuario mediante sentencias preparadas para
// rellenar el panel de control y verificar contraseñas posteriormente.
$nombreUser = $_SESSION['usuario'];
$sql = "SELECT * FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nombreUser);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$userId = $usuario['id'];

// =========================================================================================
// 3. PROCESADOR TRANSACCIONAL (USER SETTINGS)
// =========================================================================================
// NOTA ARQUITECTÓNICA: Aunque el ecosistema principal del proyecto está diseñado para un consumo 
// meramente read-only (visor de obras, catálogo), este módulo actúa como una de las pocas 
// fronteras de mutación de estado permitidas, requiriendo validaciones de seguridad extremas.

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $active_tab = 'config'; // Forzamos el retorno a la pestaña de ajustes tras un POST

    // --- MÓDULO A: CARGA DE ARCHIVOS (DEFENSA EN 3 CAPAS) ---
    // Prevenimos ataques de inyección de malware (RCE) disfrazado de imágenes.
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === 0) {
        $file_tmp = $_FILES['foto_perfil']['tmp_name'];
        $file_name = $_FILES['foto_perfil']['name'];
        $file_size = $_FILES['foto_perfil']['size'];

        // CAPA 1: Hard-Limit de Tamaño (Prevención de ataques DoS por saturación de disco)
        $max_size = 2 * 1024 * 1024; // 2MB exactos en bytes

        if ($file_size > $max_size) {
            $mensaje = "Violación de políticas: El archivo excede el límite máximo de 2MB.";
            $tipo_mensaje = "danger";
        } else {
            // CAPA 2: Validación Lexicográfica (Extensión lógica)
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // CAPA 3: Validación Estructural (Inspección del Magic Number / MIME Type)
            // Es vital usar la clase finfo de PHP, ya que $_FILES['type'] puede ser falsificado
            // fácilmente por un atacante interceptando la petición HTTP.
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file_tmp);
            
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

            // Evaluación del conjunto de reglas estricto
            if (in_array($file_ext, $allowed_exts) && in_array($mime_type, $allowed_mimes)) {
                
                // Sanitización del Asset: Renombramos el archivo inyectando entropía (time) 
                // y el ID del usuario para evitar colisiones y sobrescrituras en el servidor.
                $nombre_archivo = "user_" . $userId . "_" . time() . "." . $file_ext;
                $ruta_destino = "assets/img/avatars/" . $nombre_archivo;
                
                if (move_uploaded_file($file_tmp, $ruta_destino)) {
                    // Actualización Atómica en la BD
                    $stmtFoto = $conn->prepare("UPDATE usuarios SET foto = ? WHERE id = ?");
                    $stmtFoto->bind_param("si", $ruta_destino, $userId);
                    
                    if ($stmtFoto->execute()) {
                        // Sincronización del estado de la sesión activa
                        $_SESSION['foto'] = $ruta_destino;
                        $usuario['foto'] = $ruta_destino;
                        $mensaje = "Asset visual procesado y actualizado con éxito.";
                        $tipo_mensaje = "success";
                    }
                } else {
                    $mensaje = "Error de I/O en el servidor físico al persistir la imagen.";
                    $tipo_mensaje = "danger";
                }
            } else {
                $mensaje = "Fallo de integridad: Estructura del archivo o formato no permitidos.";
                $tipo_mensaje = "danger";
            }
        }
    }

    // --- MÓDULO B: GESTIÓN DE CREDENCIALES (CRIPTOGRAFÍA) ---
    if (isset($_POST['pass_actual'])) {
        $pass_actual = $_POST['pass_actual'];
        $pass_nueva = $_POST['pass_nueva'];
        $pass_confirm = $_POST['pass_confirm'];

        // Verificación de Autenticidad Híbrida (Compatibilidad legacy + Bcrypt)
        if (password_verify($pass_actual, $usuario['password']) || $pass_actual === $usuario['password']) {
            if ($pass_nueva === $pass_confirm) {
                // Validación de Políticas de Contraseñas (Password Strength Policy)
                if (strlen($pass_nueva) >= 6) {
                    // Ciframos la nueva clave usando el algoritmo más fuerte disponible 
                    // en el servidor (actualmente Bcrypt) con un factor de coste automático.
                    $nuevo_hash = password_hash($pass_nueva, PASSWORD_DEFAULT);
                    
                    $stmtUp = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    $stmtUp->bind_param("si", $nuevo_hash, $userId);
                    
                    if ($stmtUp->execute()) {
                        $mensaje = "Hash criptográfico actualizado correctamente.";
                        $tipo_mensaje = "success";
                    }
                } else {
                    $mensaje = "Requisito de seguridad no cumplido: Mínimo 6 caracteres.";
                    $tipo_mensaje = "danger";
                }
            } else {
                $mensaje = "Error de confirmación: Las contraseñas proporcionadas difieren.";
                $tipo_mensaje = "danger";
            }
        } else {
            $mensaje = "Fallo de validación: La credencial actual es incorrecta.";
            $tipo_mensaje = "danger";
        }
    }
}

// =========================================================================================
// 4. EXTRACCIÓN DE ACTIVIDAD DEL USUARIO (PREPARED STATEMENTS)
// =========================================================================================
// Limitamos a 10 registros por sección para no saturar la memoria en perfiles muy activos.

// A. Comentarios en capítulos del visor
$stmtCom = $conn->prepare(
    "SELECT c.texto, c.fecha, cap.id as cap_id, cap.titulo as cap_titulo, cap.slug as cap_slug,
            o.id as obra_id, o.titulo as obra_titulo, o.slug as obra_slug
     FROM comentarios c
     JOIN capitulos cap ON c.capitulo_id = cap.id
     JOIN obras o ON cap.obra_id = o.id
     WHERE c.usuario_id = ? ORDER BY c.fecha DESC LIMIT 10"
);
$stmtCom->bind_param("i", $userId);
$stmtCom->execute();
$mis_comentarios = $stmtCom->get_result();

// B. Reseñas críticas de obras
$stmtRes = $conn->prepare(
    "SELECT r.texto, r.fecha, r.puntuacion, o.id as obra_id, o.titulo as obra_titulo, o.slug as obra_slug
     FROM resenas r
     JOIN obras o ON r.obra_id = o.id
     WHERE r.usuario_id = ? ORDER BY r.fecha DESC LIMIT 10"
);
$stmtRes->bind_param("i", $userId);
$stmtRes->execute();
$mis_resenas = $stmtRes->get_result();

// C. Temas del foro creados por el usuario
$stmtTemas = $conn->prepare(
    "SELECT id, titulo, slug, fecha, categoria FROM foro_temas
     WHERE usuario_id = ? ORDER BY fecha DESC LIMIT 10"
);
$stmtTemas->bind_param("i", $userId);
$stmtTemas->execute();
$mis_temas = $stmtTemas->get_result();

// D. Respuestas del usuario en hilos ajenos
$stmtResp = $conn->prepare(
    "SELECT r.mensaje, r.fecha, t.id as tema_id, t.titulo as tema_titulo, t.slug as tema_slug
     FROM foro_respuestas r
     JOIN foro_temas t ON r.tema_id = t.id
     WHERE r.usuario_id = ? ORDER BY r.fecha DESC LIMIT 10"
);
$stmtResp->bind_param("i", $userId);
$stmtResp->execute();
$mis_respuestas_foro = $stmtResp->get_result();

?>
<?php include 'includes/header.php'; ?>

<main class="container py-5 perfil-main-container">
    
    <div class="row mb-5 align-items-center bg-white p-4 rounded-4 shadow-sm border border-light">
        <div class="col-md-auto text-center mb-3 mb-md-0 position-relative">
            <?php 
            // FALLBACK UX DINÁMICO: Si la BD no contiene una ruta de imagen para este usuario, 
            // instanciamos una consulta a una API externa para renderizar un avatar con sus iniciales.
            $foto_mostrar = !empty($usuario['foto']) 
                ? (strpos($usuario['foto'], 'http') === 0 ? $usuario['foto'] : '/' . ltrim($usuario['foto'], '/')) 
                : 'https://ui-avatars.com/api/?name=' . urlencode($usuario['nombre']) . '&background=0D8A92&color=fff&size=150&font-size=0.33&bold=true'; 
            ?>
            <img src="<?php echo htmlspecialchars($foto_mostrar); ?>" class="rounded-circle border border-4 border-white shadow perfil-avatar-img">
            
            <?php if ($usuario['rol'] == 'admin'): ?>
                <span class="position-absolute bottom-0 start-50 translate-middle-x badge bg-danger rounded-pill shadow-sm perfil-admin-badge"><i class="fas fa-crown me-1"></i>ADMIN</span>
            <?php endif; ?>
        </div>
        <div class="col-md text-center text-md-start">
            <h2 class="mb-1 fw-bold text-dark display-6"><?php echo htmlspecialchars($usuario['nombre']); ?></h2>
            <p class="text-muted mb-2 fs-5"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($usuario['email']); ?></p>
            <div class="d-flex align-items-center justify-content-center justify-content-md-start">
                <span class="badge bg-light text-secondary border px-3 py-2 shadow-sm rounded-pill"><i class="fas fa-calendar-alt me-2"></i>Miembro desde <?php echo date('d M Y', strtotime($usuario['fecha_registro'])); ?></span>
            </div>
        </div>
    </div>

    <?php if($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show shadow-sm border-0 border-start border-4 border-<?php echo $tipo_mensaje; ?> bg-white">
            <i class="fas <?php echo ($tipo_mensaje === 'success') ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-danger'; ?> me-2"></i>
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active px-4 fw-bold rounded-pill" id="config-tab" data-bs-toggle="pill" data-bs-target="#config" type="button">
                <i class="fas fa-user-cog me-2"></i>Ajustes
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 fw-bold rounded-pill text-secondary" id="actividad-tab" data-bs-toggle="pill" data-bs-target="#actividad" type="button">
                <i class="fas fa-comment-dots me-2"></i>Web
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 fw-bold rounded-pill text-secondary" id="foro-tab" data-bs-toggle="pill" data-bs-target="#foro" type="button">
                <i class="fas fa-users me-2"></i>Foro
            </button>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        
        <div class="tab-pane fade show active" id="config" role="tabpanel">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100 border-0 rounded-4 overflow-hidden">
                        <div class="card-header bg-iori text-white py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-id-card me-2"></i>Datos del Perfil</h5>
                        </div>
                        <div class="card-body p-4 bg-white">
                            
                            <form method="POST" enctype="multipart/form-data" class="mb-4 pb-4 border-bottom">
                                <label class="form-label text-secondary fw-bold mb-2">Cambiar Foto de Perfil</label>
                                <div class="input-group shadow-sm">
                                    <input type="file" name="foto_perfil" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
                                    <button class="btn btn-iori fw-bold px-4" type="submit"><i class="fas fa-upload me-2"></i>Subir</button>
                                </div>
                                <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i>Formato JPG, PNG o WEBP. Máximo 2MB.</div>
                            </form>

                            <form method="POST">
                                <label class="form-label text-secondary fw-bold mb-3 mt-2"><i class="fas fa-key me-2 text-warning"></i>Cambiar Contraseña</label>
                                <div class="form-floating mb-3 shadow-sm">
                                    <input type="password" name="pass_actual" class="form-control border-light" id="pass_actual" placeholder="Actual" required>
                                    <label for="pass_actual">Contraseña Actual</label>
                                </div>
                                <div class="row g-3 mb-4">
                                    <div class="col-sm-6">
                                        <div class="form-floating shadow-sm">
                                            <input type="password" name="pass_nueva" class="form-control border-light" id="pass_nueva" placeholder="Nueva" required>
                                            <label for="pass_nueva">Nueva Clave (Mín. 6)</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-floating shadow-sm">
                                            <input type="password" name="pass_confirm" class="form-control border-light" id="pass_confirm" placeholder="Repetir" required>
                                            <label for="pass_confirm">Repetir Clave</label>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-dark w-100 fw-bold py-2 shadow-sm">Actualizar Hash</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden perfil-danger-card">
                        <div class="card-header py-3 perfil-danger-header">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Zona de Peligro</h5>
                        </div>
                        <div class="card-body p-4 d-flex flex-column justify-content-center">
                            <div class="text-center mb-4">
                                <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-3 perfil-danger-icon">
                                    <i class="fas fa-user-slash fa-2x"></i>
                                </div>
                                <h5 class="fw-bold text-dark">Eliminación Permanente de Entidad</h5>
                                <p class="text-muted mt-2">Al ejecutar esta acción, el motor de base de datos activará el borrado en cascada (ON DELETE CASCADE), purgando todo tu historial asociado. <strong>Acción no mitigable.</strong></p>
                            </div>
                            
                            <form action="/borrar_cuenta.php" method="POST" class="mt-auto" onsubmit="return confirm('¿Confirmas la aniquilación atómica de tu cuenta y todos sus registros vinculados?');">
                                <button type="submit" class="btn btn-outline-danger w-100 fw-bold py-2 shadow-sm rounded-pill">
                                    INICIAR PROTOCOLO DE PURGA
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="actividad" role="tabpanel">
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-comment-dots text-primary me-2"></i>Comentarios en Visor</h5>
                            <span class="badge bg-light text-secondary border"><?php echo $mis_comentarios->num_rows; ?> recuperados</span>
                        </div>
                        <div class="card-body p-0 d-flex flex-column">
                            <?php if($mis_comentarios->num_rows > 0): ?>
                                <div class="list-group list-group-flush flex-grow-1">
                                    <?php while($c = $mis_comentarios->fetch_assoc()): ?>
                                        <a href="/obra/<?php echo urlencode($c['obra_slug']); ?>/<?php echo urlencode($c['cap_slug']); ?>" class="list-group-item list-group-item-action p-3 border-bottom">
                                            <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                                <small class="fw-bold text-primary text-truncate pe-2 perfil-truncate-title">
                                                    <?php echo htmlspecialchars($c['obra_titulo']); ?> <span class="text-dark">› <?php echo htmlspecialchars($c['cap_titulo']); ?></span>
                                                </small>
                                                <small class="text-muted text-nowrap perfil-date-small"><i class="far fa-clock me-1"></i><?php echo date('d/m/Y', strtotime($c['fecha'])); ?></small>
                                            </div>
                                            <p class="mb-0 text-secondary perfil-text-clamp-2">
                                                "<?php echo htmlspecialchars($c['texto']); ?>"
                                            </p>
                                        </a>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-5 text-muted d-flex flex-column align-items-center justify-content-center h-100 flex-grow-1" style="min-height: 200px;">
                                    <i class="far fa-comment fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0">Historial de interacciones vacío.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-star text-warning me-2"></i>Auditoría de Reseñas</h5>
                            <span class="badge bg-light text-secondary border"><?php echo $mis_resenas->num_rows; ?> recuperadas</span>
                        </div>
                        <div class="card-body p-0 d-flex flex-column">
                            <?php if($mis_resenas->num_rows > 0): ?>
                                <div class="list-group list-group-flush flex-grow-1">
                                    <?php while($r = $mis_resenas->fetch_assoc()): ?>
                                        <a href="/obra/<?php echo urlencode($r['obra_slug']); ?>" class="list-group-item list-group-item-action p-3 border-bottom">
                                            <div class="d-flex w-100 justify-content-between align-items-start mb-1">
                                                <div>
                                                    <small class="fw-bold text-dark d-block mb-1 fs-6"><?php echo htmlspecialchars($r['obra_titulo']); ?></small>
                                                    <div class="text-warning mb-2 perfil-star-small">
                                                        <?php 
                                                        for($i=1; $i<=5; $i++) {
                                                            echo ($i <= $r['puntuacion']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star opacity-50"></i>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <small class="text-muted text-nowrap perfil-date-small"><i class="far fa-clock me-1"></i><?php echo date('d/m/Y', strtotime($r['fecha'])); ?></small>
                                            </div>
                                            <p class="mb-0 text-secondary perfil-text-clamp-3">
                                                <?php echo htmlspecialchars($r['texto']); ?>
                                            </p>
                                        </a>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-5 text-muted d-flex flex-column align-items-center justify-content-center h-100 flex-grow-1" style="min-height: 200px;">
                                    <i class="far fa-star fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0">Aún no hay reseñas generadas.</p>
                                    <a href="/" class="btn btn-outline-iori btn-sm mt-3 rounded-pill px-3">Explorar Catálogo</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="foro" role="tabpanel">
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-pencil-alt text-info me-2"></i>Nodos de Debate Creados</h5>
                        </div>
                        <div class="card-body p-0 d-flex flex-column">
                            <?php if($mis_temas->num_rows > 0): ?>
                                <div class="list-group list-group-flush flex-grow-1">
                                    <?php while($t = $mis_temas->fetch_assoc()): ?>
                                        <a href="/foro/<?php echo urlencode($t['slug']); ?>" class="list-group-item list-group-item-action p-3 border-bottom">
                                            <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                                                <span class="badge bg-light text-dark border px-2 py-1"><i class="fas fa-tag me-1 text-muted"></i><?php echo htmlspecialchars($t['categoria']); ?></span>
                                                <small class="text-muted perfil-date-small"><?php echo date('d/m/Y', strtotime($t['fecha'])); ?></small>
                                            </div>
                                            <h6 class="mb-0 fw-bold text-dark text-truncate"><?php echo htmlspecialchars($t['titulo']); ?></h6>
                                        </a>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-5 text-muted d-flex flex-column align-items-center justify-content-center h-100 flex-grow-1" style="min-height: 200px;">
                                    <i class="far fa-file-alt fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0">No se han registrado hilos iniciados.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-reply text-success me-2"></i>Participación Activa</h5>
                        </div>
                        <div class="card-body p-0 d-flex flex-column">
                            <?php if($mis_respuestas_foro->num_rows > 0): ?>
                                <div class="list-group list-group-flush flex-grow-1">
                                    <?php while($rf = $mis_respuestas_foro->fetch_assoc()): ?>
                                        <a href="/foro/<?php echo urlencode($rf['tema_slug']); ?>" class="list-group-item list-group-item-action p-3 border-bottom">
                                            <div class="d-flex w-100 justify-content-between align-items-start mb-2">
                                                <small class="fw-bold text-dark text-truncate pe-3">
                                                    <span class="text-muted fw-normal">Host: </span><?php echo htmlspecialchars($rf['tema_titulo']); ?>
                                                </small>
                                                <small class="text-muted text-nowrap perfil-date-small"><?php echo date('d/m/y', strtotime($rf['fecha'])); ?></small>
                                            </div>
                                            <div class="p-2 bg-light rounded text-secondary border perfil-response-text">
                                                <?php echo htmlspecialchars(mb_strimwidth($rf['mensaje'], 0, 100, "...")); ?>
                                            </div>
                                        </a>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-5 text-muted d-flex flex-column align-items-center justify-content-center h-100 flex-grow-1" style="min-height: 200px;">
                                    <i class="far fa-comments fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0">No existen registros de participación subordinada.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    // =========================================================================================
    // LÓGICA FRONTEND: PRESERVACIÓN DE ESTADO EN NAVEGACIÓN UI
    // =========================================================================================
    // Modificamos la API History del navegador para que la pestaña activa 
    // persista visualmente si el usuario recarga la página mediante el "hash" de la URL.
    document.addEventListener("DOMContentLoaded", function() {
        const hash = window.location.hash;
        if (hash) {
            const triggerEl = document.querySelector('button[data-bs-target="' + hash + '"]');
            if (triggerEl) {
                const tab = new bootstrap.Tab(triggerEl);
                tab.show();
            }
        }
        
        const tabBtns = document.querySelectorAll('button[data-bs-toggle="pill"]');
        tabBtns.forEach(btn => {
            btn.addEventListener('shown.bs.tab', function (e) {
                // Mutamos la URL de forma silenciosa sin disparar recarga de red
                window.history.replaceState(null, null, e.target.getAttribute('data-bs-target'));
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>