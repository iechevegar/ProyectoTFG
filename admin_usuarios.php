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

// 2. PROCESAR ACCIONES
if (isset($_GET['accion']) && isset($_GET['id'])) {
    $idTarget = intval($_GET['id']);
    $accion = $_GET['accion'];
    
    // Consultamos quién es el usuario objetivo para ver si eres tú mismo
    $sqlCheck = "SELECT nombre FROM usuarios WHERE id = $idTarget";
    $resCheck = $conn->query($sqlCheck);
    $targetUser = $resCheck->fetch_assoc();

    // --- BLINDAJE DE SEGURIDAD ---
    // Si el nombre del objetivo es igual al tuyo -> PROHIBIDO
    if ($targetUser['nombre'] === $adminActual) {
        $msg = "❌ PROTECCIÓN: No puedes borrarte ni quitarte el admin a ti mismo.";
        $tipo_msg = "danger";
    } else {
        // Si no eres tú, procedemos normal
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
        }
    }
}

// 3. FILTROS Y BÚSQUEDA (Igual que antes)
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
        <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show">
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
                        <th>Rol</th>
                        <th>Registro</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado->num_rows > 0): ?>
                        <?php while($user = $resultado->fetch_assoc()): ?>
                            
                            <?php $esYoMismo = ($user['nombre'] === $adminActual); ?>

                            <tr class="<?php echo $esYoMismo ? 'table-warning' : ''; ?>"> <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <?php $foto = !empty($user['foto']) ? $user['foto'] : 'https://via.placeholder.com/40'; ?>
                                        <img src="<?php echo $foto; ?>" class="rounded-circle me-3" width="40" height="40" style="object-fit:cover;">
                                        <div>
                                            <span class="fw-bold"><?php echo $user['nombre']; ?></span>
                                            <div class="small text-muted"><?php echo $user['email']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if($user['rol'] === 'admin'): ?>
                                        <span class="badge bg-danger">ADMIN</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">Lector</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?php echo date('d/m/Y', strtotime($user['fecha_registro'])); ?>
                                </td>
                                <td class="text-end pe-4">
                                    
                                    <?php if ($esYoMismo): ?>
                                        <span class="badge bg-secondary"><i class="fas fa-user"></i> Tú</span>
                                    
                                    <?php else: ?>
                                        <?php if($user['rol'] === 'lector'): ?>
                                            <a href="admin_usuarios.php?accion=hacer_admin&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-success me-1" title="Ascender">
                                                <i class="fas fa-crown"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="admin_usuarios.php?accion=quitar_admin&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-warning me-1" title="Degradar">
                                                <i class="fas fa-user-shield"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="admin_usuarios.php?accion=borrar&id=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('¿Seguro?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php endif; ?>

                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-5">Sin resultados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>