<?php
session_start();
require 'includes/db.php';

// 1. SEGURIDAD: Solo admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';
$tipo_msg = '';

// Obtener el nombre del admin actual para las comparaciones
$adminActual = $_SESSION['usuario'];

// 2. PROCESAR ACCIONES (AHORA CON SEGURIDAD POST ANTI-CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['id'])) {
    $idTarget = intval($_POST['id']);
    $accion = $_POST['accion'];
    
    // Consultamos quién es el usuario objetivo
    $sqlCheck = "SELECT nombre FROM usuarios WHERE id = $idTarget";
    $resCheck = $conn->query($sqlCheck);
    $targetUser = $resCheck->fetch_assoc();

    // --- BLINDAJE DE SEGURIDAD ---
    if ($targetUser['nombre'] === $adminActual) {
        $msg = "❌ PROTECCIÓN: No puedes borrarte, suspenderte ni quitarte el admin a ti mismo.";
        $tipo_msg = "danger";
    } else {
        if ($accion === 'hacer_admin') {
            $conn->query("UPDATE usuarios SET rol = 'admin' WHERE id = $idTarget");
            $msg = "Usuario ascendido a Administrador.";
            $tipo_msg = "success";
        } elseif ($accion === 'quitar_admin') {
            $conn->query("UPDATE usuarios SET rol = 'lector' WHERE id = $idTarget");
            $msg = "Usuario degradado a Lector.";
            $tipo_msg = "warning";
        } elseif ($accion === 'borrar') {
            $conn->query("DELETE FROM usuarios WHERE id = $idTarget");
            $msg = "Usuario eliminado correctamente.";
            $tipo_msg = "success";
        } elseif ($accion === 'suspender') {
            // Calcula la fecha exacta de dentro de 7 días
            $fechaBloqueo = date('Y-m-d H:i:s', strtotime('+7 days'));
            $stmt = $conn->prepare("UPDATE usuarios SET fecha_desbloqueo = ? WHERE id = ?");
            $stmt->bind_param("si", $fechaBloqueo, $idTarget);
            if($stmt->execute()) {
                $msg = "Usuario suspendido (Modo Lectura) por 7 días.";
                $tipo_msg = "warning";
            }
        } elseif ($accion === 'quitar_suspension') {
            $conn->query("UPDATE usuarios SET fecha_desbloqueo = NULL WHERE id = $idTarget");
            $msg = "Suspensión levantada. El usuario ya puede comentar.";
            $tipo_msg = "success";
        }
    }
}

// 3. FILTROS Y BÚSQUEDA
$filtro_nombre = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$filtro_rol = isset($_GET['rol']) ? $_GET['rol'] : 'todos';
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'recientes';

$sql = "SELECT * FROM usuarios WHERE 1=1"; 

if (!empty($filtro_nombre)) {
    $sql .= " AND (nombre LIKE '%$filtro_nombre%' OR email LIKE '%$filtro_nombre%')";
}
if ($filtro_rol !== 'todos') {
    $sql .= " AND rol = '$filtro_rol'";
}
if ($orden === 'antiguos') {
    $sql .= " ORDER BY fecha_registro ASC";
} else {
    $sql .= " ORDER BY fecha_registro DESC"; 
}

$resultado = $conn->query($sql);
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="admin.php" class="text-decoration-none text-muted mb-1 d-inline-block">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
            <h2><i class="fas fa-users-cog text-dark"></i> Gestión de Usuarios</h2>
        </div>
    </div>

    <?php if($msg): ?>
        <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show shadow-sm">
            <?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4 bg-light border-0">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold small">Buscar usuario</label>
                    <input type="text" name="busqueda" class="form-control" placeholder="Nombre..." value="<?php echo htmlspecialchars($filtro_nombre); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Rol</label>
                    <select name="rol" class="form-select">
                        <option value="todos" <?php echo $filtro_rol=='todos'?'selected':''; ?>>Todos</option>
                        <option value="admin" <?php echo $filtro_rol=='admin'?'selected':''; ?>>Admins</option>
                        <option value="lector" <?php echo $filtro_rol=='lector'?'selected':''; ?>>Lectores</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Orden</label>
                    <select name="orden" class="form-select">
                        <option value="recientes" <?php echo $orden=='recientes'?'selected':''; ?>>Recientes</option>
                        <option value="antiguos" <?php echo $orden=='antiguos'?'selected':''; ?>>Antiguos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark w-100">Aplicar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Usuario</th>
                        <th>Rol / Estado</th>
                        <th>Registro</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado->num_rows > 0): ?>
                        <?php while($user = $resultado->fetch_assoc()): ?>
                            
                            <?php 
                            $esYoMismo = ($user['nombre'] === $adminActual); 
                            // Comprobamos si está suspendido comparando fechas
                            $estaSuspendido = (!empty($user['fecha_desbloqueo']) && strtotime($user['fecha_desbloqueo']) > time());
                            ?>

                            <tr class="<?php echo $esYoMismo ? 'table-warning' : ''; ?>"> 
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <?php $foto = !empty($user['foto']) ? $user['foto'] : 'https://via.placeholder.com/40'; ?>
                                        <img src="<?php echo $foto; ?>" class="rounded-circle me-3 border" width="40" height="40" style="object-fit:cover;">
                                        <div>
                                            <span class="fw-bold"><?php echo htmlspecialchars($user['nombre']); ?></span>
                                            <div class="small text-muted"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if($user['rol'] === 'admin'): ?>
                                        <span class="badge bg-danger mb-1 d-inline-block">ADMIN</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark mb-1 d-inline-block">Lector</span>
                                    <?php endif; ?>
                                    
                                    <br>
                                    
                                    <?php if($estaSuspendido): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-ban me-1"></i> Suspendido</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?php echo date('d/m/Y', strtotime($user['fecha_registro'])); ?>
                                </td>
                                <td class="text-end pe-4">
                                    
                                    <?php if ($esYoMismo): ?>
                                        <span class="badge bg-secondary px-3 py-2"><i class="fas fa-user me-1"></i> Es tu cuenta</span>
                                    <?php else: ?>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

                                            <?php if($user['rol'] === 'lector'): ?>
                                                <button type="submit" name="accion" value="hacer_admin" class="btn btn-sm btn-outline-success me-1" title="Ascender a Admin"><i class="fas fa-crown"></i></button>
                                            <?php else: ?>
                                                <button type="submit" name="accion" value="quitar_admin" class="btn btn-sm btn-outline-warning me-1" title="Degradar a Lector" onclick="return confirm('¿Quitar permisos de administrador?');"><i class="fas fa-user-shield"></i></button>
                                            <?php endif; ?>

                                            <?php if ($estaSuspendido): ?>
                                                <button type="submit" name="accion" value="quitar_suspension" class="btn btn-sm btn-outline-secondary me-1" title="Quitar suspensión"><i class="fas fa-unlock"></i></button>
                                            <?php else: ?>
                                                <button type="submit" name="accion" value="suspender" class="btn btn-sm btn-outline-secondary me-1" title="Suspender (7 días)" onclick="return confirm('¿Suspender a este usuario por 7 días? No podrá comentar ni participar en el foro.');"><i class="fas fa-user-slash"></i></button>
                                            <?php endif; ?>

                                            <button type="submit" name="accion" value="borrar" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Seguro que quieres eliminar este usuario permanentemente?');"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    <?php endif; ?>

                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted"><i class="fas fa-search fa-2x mb-3 opacity-25 d-block"></i> Sin resultados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>