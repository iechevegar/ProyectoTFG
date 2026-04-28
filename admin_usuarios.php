<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. CONTROL DE ACCESO BASADO EN ROLES (RBAC)
// =========================================================================================
// Restringimos el acceso exclusivamente a usuarios con rol de administrador.
// Cualquier otro usuario es expulsado a la pantalla de login.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /login");
    exit();
}

$msg = '';
$tipo_msg = '';

// Guardamos el usuario de la sesión actual en memoria para aplicar reglas de validación (Anti-Self-Harm)
$adminActual = $_SESSION['usuario'];


// =========================================================================================
// 2. PROCESADOR DE ACCIONES DE GESTIÓN (POST)
// =========================================================================================
// Obligamos a que cualquier alteración de la base de datos venga por POST para prevenir
// manipulaciones accidentales o ataques CSRF mediante enlaces pasados por GET.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['id'])) {
    csrf_verify('/admin_usuarios');

    $idTarget = intval($_POST['id']);
    $accion   = $_POST['accion'];

    $stmtCheck = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
    $stmtCheck->bind_param("i", $idTarget);
    $stmtCheck->execute();
    $targetUser = $stmtCheck->get_result()->fetch_assoc();

    if (!$targetUser) {
        $msg = "Usuario no encontrado.";
        $tipo_msg = "danger";
    } elseif ($targetUser['nombre'] === $adminActual) {
        $msg = "Protección: No puedes modificarte a ti mismo.";
        $tipo_msg = "danger";
    } else {
        if ($accion === 'hacer_admin') {
            $s = $conn->prepare("UPDATE usuarios SET rol = 'admin' WHERE id = ?");
            $s->bind_param("i", $idTarget); $s->execute();
            $msg = "Usuario ascendido a Administrador."; $tipo_msg = "success";
        } elseif ($accion === 'quitar_admin') {
            $s = $conn->prepare("UPDATE usuarios SET rol = 'lector' WHERE id = ?");
            $s->bind_param("i", $idTarget); $s->execute();
            $msg = "Usuario degradado a Lector."; $tipo_msg = "warning";
        } elseif ($accion === 'borrar') {
            $s = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
            $s->bind_param("i", $idTarget); $s->execute();
            $msg = "Usuario eliminado correctamente."; $tipo_msg = "success";
        } elseif ($accion === 'suspender') {
            $fechaBloqueo = date('Y-m-d H:i:s', strtotime('+7 days'));
            $s = $conn->prepare("UPDATE usuarios SET fecha_desbloqueo = ? WHERE id = ?");
            $s->bind_param("si", $fechaBloqueo, $idTarget); $s->execute();
            $msg = "Usuario suspendido por 7 días."; $tipo_msg = "warning";
        } elseif ($accion === 'quitar_suspension') {
            $s = $conn->prepare("UPDATE usuarios SET fecha_desbloqueo = NULL WHERE id = ?");
            $s->bind_param("i", $idTarget); $s->execute();
            $msg = "Suspensión levantada."; $tipo_msg = "success";
        }
    }
}


// =========================================================================================
// 3. MOTOR DE BÚSQUEDA Y FILTRADO DINÁMICO
// =========================================================================================
$filtro_nombre = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$filtro_rol    = isset($_GET['rol'])      ? trim($_GET['rol'])      : 'todos';
$orden         = isset($_GET['orden'])    ? trim($_GET['orden'])    : 'recientes';

// Query builder seguro con prepared statements
$roles_validos = ['admin', 'lector', 'todos'];
if (!in_array($filtro_rol, $roles_validos)) $filtro_rol = 'todos';
$orden_sql = ($orden === 'antiguos') ? 'ORDER BY fecha_registro ASC' : 'ORDER BY fecha_registro DESC';

$sql_base = "SELECT * FROM usuarios WHERE 1=1";
$tipos  = "";
$params = [];

if (!empty($filtro_nombre)) {
    $sql_base .= " AND (nombre LIKE ? OR email LIKE ?)";
    $like = "%$filtro_nombre%";
    $tipos .= "ss";
    $params[] = &$like; $params[] = &$like;
}
if ($filtro_rol !== 'todos') {
    $sql_base .= " AND rol = ?";
    $tipos .= "s";
    $params[] = &$filtro_rol;
}
$sql_base .= " $orden_sql";

$stmtLista = $conn->prepare($sql_base);
if (!empty($tipos)) {
    $bind = array_merge([$tipos], $params);
    call_user_func_array([$stmtLista, 'bind_param'], $bind);
}
$stmtLista->execute();
$resultado = $stmtLista->get_result();

$usuarios_lista = [];
if ($resultado && $resultado->num_rows > 0) {
    while ($user = $resultado->fetch_assoc()) {
        $usuarios_lista[] = $user;
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-5 admin-main-container">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 border-bottom border-light pb-3">
        <div>
            <a href="/admin" class="text-decoration-none text-muted mb-2 d-inline-block fw-bold hover-iori transition-colors">
                <i class="fas fa-arrow-left me-1"></i> Volver al Panel
            </a>
            <h2 class="fw-bold text-dark m-0"><i class="fas fa-users-cog text-iori me-2"></i> Gestión de Usuarios</h2>
        </div>
    </div>

    <?php if($msg): ?>
        <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show shadow-sm rounded-4 border-<?php echo $tipo_msg; ?> bg-white border-start border-4">
            <?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4 border-0 rounded-4 bg-white">
        <div class="card-body p-4">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-secondary text-uppercase"><i class="fas fa-search me-1"></i> Buscar usuario</label>
                    <input type="text" name="busqueda" class="form-control bg-light border-light shadow-sm admin-filter-input" placeholder="Nombre o email..." value="<?php echo htmlspecialchars($filtro_nombre); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary text-uppercase"><i class="fas fa-tag me-1"></i> Rol</label>
                    <select name="rol" class="form-select bg-light border-light shadow-sm admin-filter-input" style="cursor: pointer;">
                        <option value="todos" <?php echo $filtro_rol=='todos'?'selected':''; ?>>Todos los roles</option>
                        <option value="admin" <?php echo $filtro_rol=='admin'?'selected':''; ?>>Administradores</option>
                        <option value="lector" <?php echo $filtro_rol=='lector'?'selected':''; ?>>Lectores</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary text-uppercase"><i class="fas fa-sort me-1"></i> Orden</label>
                    <select name="orden" class="form-select bg-light border-light shadow-sm admin-filter-input" style="cursor: pointer;">
                        <option value="recientes" <?php echo $orden=='recientes'?'selected':''; ?>>Más Recientes</option>
                        <option value="antiguos" <?php echo $orden=='antiguos'?'selected':''; ?>>Más Antiguos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark w-100 fw-bold shadow-sm admin-filter-input">Aplicar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden bg-white mb-5 d-none d-md-block">
        <div class="table-responsive">
            <table class="table align-middle mb-0 border-white admin-table-hover">
                <thead class="text-secondary small text-uppercase bg-light">
                    <tr>
                        <th class="ps-4 py-3 border-0">Usuario</th>
                        <th class="py-3 border-0 text-center">Rol / Estado</th>
                        <th class="py-3 border-0">Registro</th>
                        <th class="text-end pe-4 py-3 border-0">Acciones</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php if (count($usuarios_lista) > 0): ?>
                        <?php foreach($usuarios_lista as $user): ?>
                            
                            <?php 
                            // Lógica de pintado condicional de la fila según el estado del usuario
                            $esYoMismo = ($user['nombre'] === $adminActual); 
                            $estaSuspendido = (!empty($user['fecha_desbloqueo']) && strtotime($user['fecha_desbloqueo']) > time());
                            
                            // MEJORA UX: Generación de Avatar dinámico usando API externa si el usuario no ha subido foto
                            $foto = !empty($user['foto']) 
                                ? ((strpos($user['foto'], 'http') === 0) ? $user['foto'] : '/' . ltrim($user['foto'], '/')) 
                                : 'https://ui-avatars.com/api/?name=' . urlencode($user['nombre']) . '&background=0D8A92&color=fff&size=45&bold=true'; 
                            ?>

                            <tr class="<?php echo $esYoMismo ? 'bg-warning bg-opacity-10' : ''; ?>"> 
                                <td class="ps-4 py-3 border-light">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($foto); ?>" class="rounded-circle me-3 border border-2 <?php echo ($user['rol'] === 'admin') ? 'border-danger' : 'border-light'; ?> shadow-sm avatar-admin-list">
                                        <div>
                                            <span class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($user['nombre']); ?></span>
                                            <div class="small text-muted" style="font-size: 0.85rem;"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="py-3 border-light text-center">
                                    <?php if($user['rol'] === 'admin'): ?>
                                        <span class="badge bg-danger mb-1 shadow-sm px-3 rounded-pill" style="letter-spacing: 0.5px;">ADMIN</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark mb-1 shadow-sm px-3 rounded-pill" style="letter-spacing: 0.5px;">LECTOR</span>
                                    <?php endif; ?>
                                    
                                    <br>
                                    
                                    <?php if($estaSuspendido): ?>
                                        <span class="badge bg-warning text-dark border border-warning shadow-sm mt-1 px-2 rounded-pill" title="Hasta: <?php echo date('d/m/Y H:i', strtotime($user['fecha_desbloqueo'])); ?>">
                                            <i class="fas fa-ban me-1"></i> Suspendido
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="small text-secondary fw-semibold border-light py-3">
                                    <?php echo date('d M Y', strtotime($user['fecha_registro'])); ?>
                                </td>
                                
                                <td class="text-end pe-4 py-3 border-light">
                                    <?php if ($esYoMismo): ?>
                                        <span class="badge bg-white text-dark border border-secondary px-3 py-2 rounded-pill shadow-sm"><i class="fas fa-user me-1 text-iori"></i> Es tu cuenta</span>
                                    <?php else: ?>
                                        <form method="POST" action="" class="d-inline d-flex justify-content-end gap-1">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

                                            <?php if($user['rol'] === 'lector'): ?>
                                                <button type="submit" name="accion" value="hacer_admin" class="btn btn-sm btn-success shadow-sm rounded-3" title="Ascender a Admin" onclick="return confirm('¿Dar permisos de Administrador a este usuario?');">
                                                    <i class="fas fa-crown"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="accion" value="quitar_admin" class="btn btn-sm btn-warning text-dark shadow-sm rounded-3" title="Degradar a Lector" onclick="return confirm('¿Quitar permisos de administrador?');">
                                                    <i class="fas fa-user-shield"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($estaSuspendido): ?>
                                                <button type="submit" name="accion" value="quitar_suspension" class="btn btn-sm btn-success shadow-sm rounded-3" title="Quitar suspensión">
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="accion" value="suspender" class="btn btn-sm btn-secondary shadow-sm rounded-3" title="Suspender (7 días)" onclick="return confirm('¿Suspender a este usuario por 7 días? No podrá comentar ni participar en el foro.');">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            <?php endif; ?>

                                            <button type="submit" name="accion" value="borrar" class="btn btn-sm btn-danger shadow-sm rounded-3 ms-1" onclick="return confirm('ATENCIÓN: ¿Seguro que quieres eliminar este usuario permanentemente? Se borrarán sus comentarios y temas.');">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 bg-white">
                                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 admin-empty-state-icon">
                                    <i class="fas fa-search fa-2x text-muted opacity-50"></i>
                                </div>
                                <h5 class="fw-bold text-dark">No se encontraron usuarios</h5>
                                <p class="text-muted mb-0">Prueba con otros filtros de búsqueda.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-md-none d-flex flex-column gap-3 mb-5">
        <?php if (count($usuarios_lista) > 0): ?>
            <?php foreach($usuarios_lista as $user): ?>
                <?php 
                $esYoMismo = ($user['nombre'] === $adminActual); 
                $estaSuspendido = (!empty($user['fecha_desbloqueo']) && strtotime($user['fecha_desbloqueo']) > time());
                $foto = !empty($user['foto']) 
                    ? ((strpos($user['foto'], 'http') === 0) ? $user['foto'] : '/' . ltrim($user['foto'], '/')) 
                    : 'https://ui-avatars.com/api/?name=' . urlencode($user['nombre']) . '&background=0D8A92&color=fff&size=45&bold=true'; 
                ?>
                <div class="card shadow-sm border-0 rounded-4 bg-white <?php echo $esYoMismo ? 'border-start border-4 border-warning' : ''; ?>">
                    <div class="card-body p-3">
                        
                        <div class="d-flex align-items-center mb-3">
                            <img src="<?php echo htmlspecialchars($foto); ?>" class="rounded-circle me-3 border border-2 <?php echo ($user['rol'] === 'admin') ? 'border-danger' : 'border-light'; ?> shadow-sm avatar-admin-list">
                            <div class="flex-grow-1 overflow-hidden">
                                <h6 class="fw-bold text-dark mb-0 text-truncate"><?php echo htmlspecialchars($user['nombre']); ?></h6>
                                <small class="text-muted text-truncate d-block" style="font-size: 0.8rem;"><?php echo htmlspecialchars($user['email']); ?></small>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <?php if($user['rol'] === 'admin'): ?>
                                    <span class="badge bg-danger shadow-sm rounded-pill">ADMIN</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark shadow-sm rounded-pill">LECTOR</span>
                                <?php endif; ?>

                                <?php if($estaSuspendido): ?>
                                    <span class="badge bg-warning text-dark border border-warning shadow-sm rounded-pill ms-1">
                                        <i class="fas fa-ban me-1"></i> Suspendido
                                    </span>
                                <?php endif; ?>
                            </div>
                            <small class="text-secondary fw-semibold" style="font-size: 0.75rem;">
                                <i class="far fa-calendar-alt me-1"></i> <?php echo date('d M Y', strtotime($user['fecha_registro'])); ?>
                            </small>
                        </div>

                        <div class="border-top border-light pt-3">
                            <?php if ($esYoMismo): ?>
                                <div class="text-center">
                                    <span class="badge bg-light text-dark border border-secondary px-3 py-2 rounded-pill shadow-sm w-100">
                                        <i class="fas fa-user me-1 text-iori"></i> Es tu cuenta
                                    </span>
                                </div>
                            <?php else: ?>
                                <form method="POST" action="" class="d-flex flex-wrap gap-2 justify-content-center">
                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

                                    <?php if($user['rol'] === 'lector'): ?>
                                        <button type="submit" name="accion" value="hacer_admin" class="btn btn-sm btn-success flex-grow-1 shadow-sm rounded-pill fw-bold" onclick="return confirm('¿Dar permisos de Administrador a este usuario?');">
                                            <i class="fas fa-crown me-1"></i> Admin
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="accion" value="quitar_admin" class="btn btn-sm btn-warning text-dark flex-grow-1 shadow-sm rounded-pill fw-bold" onclick="return confirm('¿Quitar permisos de administrador?');">
                                            <i class="fas fa-user-shield me-1"></i> Lector
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($estaSuspendido): ?>
                                        <button type="submit" name="accion" value="quitar_suspension" class="btn btn-sm btn-success flex-grow-1 shadow-sm rounded-pill fw-bold">
                                            <i class="fas fa-unlock me-1"></i> Desbloquear
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="accion" value="suspender" class="btn btn-sm btn-secondary flex-grow-1 shadow-sm rounded-pill fw-bold" onclick="return confirm('¿Suspender a este usuario por 7 días?');">
                                            <i class="fas fa-user-slash me-1"></i> Suspender
                                        </button>
                                    <?php endif; ?>

                                    <button type="submit" name="accion" value="borrar" class="btn btn-sm btn-danger flex-grow-1 shadow-sm rounded-pill fw-bold" onclick="return confirm('ATENCIÓN: ¿Seguro que quieres eliminar este usuario permanentemente?');">
                                        <i class="fas fa-trash-alt me-1"></i> Borrar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card shadow-sm border-0 rounded-4 bg-white">
                <div class="card-body text-center py-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 admin-empty-state-icon">
                        <i class="fas fa-search fa-2x text-muted opacity-50"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No se encontraron usuarios</h5>
                    <p class="text-muted mb-0 small">Prueba con otros filtros de búsqueda.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>