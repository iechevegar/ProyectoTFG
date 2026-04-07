<?php
session_start();
require 'includes/db.php';

// Si no está logueado, fuera
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = ''; 
$active_tab = 'config'; // Pestaña por defecto

// Verificar mensajes de error/éxito
if (isset($_GET['error'])) {
    $mensaje = $_GET['error'];
    $tipo_mensaje = 'danger';
}

// 1. OBTENER DATOS USUARIO
$nombreUser = $_SESSION['usuario'];
$sql = "SELECT * FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nombreUser);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$userId = $usuario['id'];

// 2. PROCESAR FORMULARIOS (Configuración Segura)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $active_tab = 'config';

    // --- SUBIR FOTO (3 CAPAS DE SEGURIDAD) ---
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === 0) {
        $file_tmp = $_FILES['foto_perfil']['tmp_name'];
        $file_name = $_FILES['foto_perfil']['name'];
        $file_size = $_FILES['foto_perfil']['size'];

        // CAPA 1: Limitar tamaño a 2MB
        $max_size = 2 * 1024 * 1024; 

        if ($file_size > $max_size) {
            $mensaje = "El archivo es demasiado grande (Máximo 2MB).";
            $tipo_mensaje = "danger";
        } else {
            // CAPA 2: Validar extensión lógica
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // CAPA 3: Validar MIME Type real (ADN del archivo)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

            // Si pasa las capas de seguridad...
            if (in_array($file_ext, $allowed_exts) && in_array($mime_type, $allowed_mimes)) {
                // Renombramos de forma segura manteniendo su extensión real
                $nombre_archivo = "user_" . $userId . "_" . time() . "." . $file_ext;
                $ruta_destino = "assets/img/avatars/" . $nombre_archivo;
                
                if (move_uploaded_file($file_tmp, $ruta_destino)) {
                    $stmtFoto = $conn->prepare("UPDATE usuarios SET foto = ? WHERE id = ?");
                    $stmtFoto->bind_param("si", $ruta_destino, $userId);
                    if ($stmtFoto->execute()) {
                        $_SESSION['foto'] = $ruta_destino;
                        $usuario['foto'] = $ruta_destino;
                        $mensaje = "Foto de perfil actualizada de forma segura.";
                        $tipo_mensaje = "success";
                    }
                } else {
                    $mensaje = "Error físico en el servidor al guardar la imagen.";
                    $tipo_mensaje = "danger";
                }
            } else {
                $mensaje = "Formato no permitido. Por seguridad, solo se aceptan imágenes JPG, PNG o WEBP.";
                $tipo_mensaje = "danger";
            }
        }
    }

    // --- CAMBIAR CONTRASEÑA ---
    if (isset($_POST['pass_actual'])) {
        $pass_actual = $_POST['pass_actual'];
        $pass_nueva = $_POST['pass_nueva'];
        $pass_confirm = $_POST['pass_confirm'];

        if (password_verify($pass_actual, $usuario['password']) || $pass_actual === $usuario['password']) {
            if ($pass_nueva === $pass_confirm) {
                // Mejora: Subimos la seguridad a 6 caracteres mínimos
                if (strlen($pass_nueva) >= 6) {
                    $nuevo_hash = password_hash($pass_nueva, PASSWORD_DEFAULT);
                    $stmtUp = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    $stmtUp->bind_param("si", $nuevo_hash, $userId);
                    if ($stmtUp->execute()) {
                        $mensaje = "Contraseña actualizada correctamente.";
                        $tipo_mensaje = "success";
                    }
                } else {
                    $mensaje = "Por seguridad, la contraseña debe tener al menos 6 caracteres.";
                    $tipo_mensaje = "danger";
                }
            } else {
                $mensaje = "Las nuevas contraseñas no coinciden.";
                $tipo_mensaje = "danger";
            }
        } else {
            $mensaje = "Contraseña actual incorrecta.";
            $tipo_mensaje = "danger";
        }
    }
}

// 3. CONSULTAS PARA EL HISTORIAL (Limitamos a los últimos 10)

// A. Comentarios en Capítulos
$sqlCom = "SELECT c.texto, c.fecha, cap.id as cap_id, cap.titulo as cap_titulo, o.id as obra_id, o.titulo as obra_titulo 
           FROM comentarios c 
           JOIN capitulos cap ON c.capitulo_id = cap.id 
           JOIN obras o ON cap.obra_id = o.id 
           WHERE c.usuario_id = $userId ORDER BY c.fecha DESC LIMIT 10";
$mis_comentarios = $conn->query($sqlCom);

// B. Reseñas en Obras
$sqlRes = "SELECT r.texto, r.fecha, o.id as obra_id, o.titulo as obra_titulo 
           FROM resenas r 
           JOIN obras o ON r.obra_id = o.id 
           WHERE r.usuario_id = $userId ORDER BY r.fecha DESC LIMIT 10";
$mis_resenas = $conn->query($sqlRes);

// C. Temas creados en Foro
$sqlTemas = "SELECT id, titulo, fecha, categoria FROM foro_temas WHERE usuario_id = $userId ORDER BY fecha DESC LIMIT 10";
$mis_temas = $conn->query($sqlTemas);

// D. Respuestas en Foro
$sqlRespuestas = "SELECT r.mensaje, r.fecha, t.id as tema_id, t.titulo as tema_titulo 
                  FROM foro_respuestas r 
                  JOIN foro_temas t ON r.tema_id = t.id 
                  WHERE r.usuario_id = $userId ORDER BY r.fecha DESC LIMIT 10";
$mis_respuestas_foro = $conn->query($sqlRespuestas);

?>
<?php include 'includes/header.php'; ?>

<main class="container py-5">
    
    <div class="row mb-4 align-items-center">
        <div class="col-md-auto text-center">
            <?php $foto_mostrar = !empty($usuario['foto']) ? $usuario['foto'] : 'https://via.placeholder.com/150'; ?>
            <img src="<?php echo $foto_mostrar; ?>" class="rounded-circle border shadow-sm" width="100" height="100" style="object-fit: cover;">
        </div>
        <div class="col-md">
            <h2 class="mb-0 fw-bold"><?php echo htmlspecialchars($usuario['nombre']); ?></h2>
            <p class="text-muted mb-1"><?php echo htmlspecialchars($usuario['email']); ?></p>
            <span class="badge <?php echo $usuario['rol'] == 'admin' ? 'bg-danger' : 'bg-info'; ?>">
                <?php echo ucfirst($usuario['rol']); ?>
            </span>
            <span class="text-muted small ms-2">Miembro desde <?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></span>
        </div>
    </div>

    <?php if($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" id="config-tab" data-bs-toggle="tab" data-bs-target="#config" type="button">
                <i class="fas fa-cog me-2"></i>Configuración
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="actividad-tab" data-bs-toggle="tab" data-bs-target="#actividad" type="button">
                <i class="fas fa-comment-dots me-2"></i>Mis Comentarios
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="foro-tab" data-bs-toggle="tab" data-bs-target="#foro" type="button">
                <i class="fas fa-users me-2"></i>Actividad Foro
            </button>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        
        <div class="tab-pane fade show active" id="config" role="tabpanel">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white fw-bold">Actualizar Datos</div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" class="mb-4 pb-3 border-bottom">
                                <label class="form-label small fw-bold">Cambiar Foto (Max 2MB)</label>
                                <div class="input-group">
                                    <input type="file" name="foto_perfil" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
                                    <button class="btn btn-outline-primary" type="submit">Subir</button>
                                </div>
                            </form>

                            <form method="POST">
                                <label class="form-label small fw-bold text-primary">Cambiar Contraseña</label>
                                <div class="mb-2">
                                    <input type="password" name="pass_actual" class="form-control form-control-sm" placeholder="Actual" required>
                                </div>
                                <div class="mb-2">
                                    <input type="password" name="pass_nueva" class="form-control form-control-sm" placeholder="Nueva (Mín. 6 caracteres)" required>
                                </div>
                                <div class="mb-3">
                                    <input type="password" name="pass_confirm" class="form-control form-control-sm" placeholder="Repetir Nueva" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-sm">Actualizar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card border-danger shadow-sm">
                        <div class="card-header bg-danger text-white fw-bold">Zona de Peligro</div>
                        <div class="card-body">
                            <p class="small text-muted">Si eliminas tu cuenta, se borrarán todos tus datos, favoritos, comentarios y participación en el foro de forma permanente.</p>
                            
                            <form action="borrar_cuenta.php" method="POST" onsubmit="return confirm('¿Estás COMPLETAMENTE SEGURO? No hay vuelta atrás.');">
                                <button type="submit" class="btn btn-outline-danger w-100 fw-bold">
                                    <i class="fas fa-user-slash me-2"></i>Eliminar Cuenta Permanentemente
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
                    <h5 class="mb-3 border-bottom pb-2">Comentarios en Capítulos</h5>
                    <?php if($mis_comentarios->num_rows > 0): ?>
                        <div class="list-group shadow-sm">
                            <?php while($c = $mis_comentarios->fetch_assoc()): ?>
                                <a href="visor.php?obraId=<?php echo $c['obra_id']; ?>&capId=<?php echo $c['cap_id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <small class="fw-bold text-primary"><?php echo $c['obra_titulo']; ?> - <?php echo $c['cap_titulo']; ?></small>
                                        <small class="text-muted"><?php echo date('d/m/y', strtotime($c['fecha'])); ?></small>
                                    </div>
                                    <p class="mb-1 small text-truncate"><?php echo htmlspecialchars($c['texto']); ?></p>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small">No has comentado ningún capítulo aún.</p>
                    <?php endif; ?>
                </div>

                <div class="col-lg-6 mb-4">
                    <h5 class="mb-3 border-bottom pb-2">Mis Reseñas</h5>
                    <?php if($mis_resenas->num_rows > 0): ?>
                        <div class="list-group shadow-sm">
                            <?php while($r = $mis_resenas->fetch_assoc()): ?>
                                <a href="detalle.php?id=<?php echo $r['obra_id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <small class="fw-bold text-success"><?php echo $r['obra_titulo']; ?></small>
                                        <small class="text-muted"><?php echo date('d/m/y', strtotime($r['fecha'])); ?></small>
                                    </div>
                                    <p class="mb-1 small text-truncate">"<?php echo htmlspecialchars($r['texto']); ?>"</p>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small">No has escrito reseñas aún.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="foro" role="tabpanel">
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <h5 class="mb-3 border-bottom pb-2">Temas Creados</h5>
                    <?php if($mis_temas->num_rows > 0): ?>
                        <div class="list-group shadow-sm">
                            <?php while($t = $mis_temas->fetch_assoc()): ?>
                                <a href="tema.php?id=<?php echo $t['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <small class="fw-bold"><?php echo htmlspecialchars($t['titulo']); ?></small>
                                        <span class="badge bg-secondary" style="font-size:0.6em"><?php echo $t['categoria']; ?></span>
                                    </div>
                                    <small class="text-muted">Creado el <?php echo date('d/m/Y', strtotime($t['fecha'])); ?></small>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small">No has creado ningún tema.</p>
                    <?php endif; ?>
                </div>

                <div class="col-lg-6 mb-4">
                    <h5 class="mb-3 border-bottom pb-2">Mis Respuestas</h5>
                    <?php if($mis_respuestas_foro->num_rows > 0): ?>
                        <div class="list-group shadow-sm">
                            <?php while($rf = $mis_respuestas_foro->fetch_assoc()): ?>
                                <a href="tema.php?id=<?php echo $rf['tema_id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <small class="fw-bold text-dark">En: <?php echo htmlspecialchars($rf['tema_titulo']); ?></small>
                                        <small class="text-muted"><?php echo date('d/m/y', strtotime($rf['fecha'])); ?></small>
                                    </div>
                                    <p class="mb-1 small text-truncate text-secondary">
                                        <i class="fas fa-reply me-1"></i> <?php echo htmlspecialchars($rf['mensaje']); ?>
                                    </p>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small">No has respondido en ningún tema.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</main>

<?php include 'includes/footer.php'; ?>