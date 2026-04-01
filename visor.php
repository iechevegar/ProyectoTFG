<?php
require 'includes/db.php';
// Iniciamos sesión si no está iniciada (para saber quién comenta)
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Validaciones básicas
if (!isset($_GET['capId'])) die("Error: No se ha especificado un capítulo.");

$capId = intval($_GET['capId']);

// --- MARCAR COMO LEÍDO AUTOMÁTICAMENTE ---
// Solo se ejecuta si hay sesión iniciada Y EL ROL NO ES ADMIN
if (isset($_SESSION['usuario']) && isset($_SESSION['rol']) && $_SESSION['rol'] !== 'admin') {
    $nombreUser = $_SESSION['usuario'];
    $resUser = $conn->query("SELECT id FROM usuarios WHERE nombre = '$nombreUser'");
    if ($resUser && $resUser->num_rows > 0) {
        $userId = $resUser->fetch_assoc()['id'];
        $stmtLeido = $conn->prepare("INSERT IGNORE INTO capitulos_leidos (usuario_id, capitulo_id) VALUES (?, ?)");
        $stmtLeido->bind_param("ii", $userId, $capId);
        $stmtLeido->execute();
    }
}
// -----------------------------------------

$obraId = isset($_GET['obraId']) ? intval($_GET['obraId']) : 0;

// 2. LOGICA DE NAVEGACIÓN
$origen = isset($_GET['origen']) ? $_GET['origen'] : 'public';
$url_volver = ($origen === 'admin') ? "ver_capitulos.php?id=" . $obraId : "detalle.php?id=" . $obraId;

// 3. PROCESAR NUEVO COMENTARIO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentario'])) {
    if (isset($_SESSION['usuario'])) {
        $texto = trim($_POST['comentario']);
        if (!empty($texto)) {
            // Buscamos ID del usuario
            $nombreUser = $_SESSION['usuario'];
            $resUser = $conn->query("SELECT id FROM usuarios WHERE nombre = '$nombreUser'");
            $userId = $resUser->fetch_assoc()['id'];

            $stmtInsert = $conn->prepare("INSERT INTO comentarios (usuario_id, capitulo_id, texto) VALUES (?, ?, ?)");
            $stmtInsert->bind_param("iis", $userId, $capId, $texto);
            $stmtInsert->execute();
            
            // Recargamos para evitar reenvío del formulario
            header("Location: visor.php?capId=$capId&obraId=$obraId&origen=$origen");
            exit();
        }
    } else {
        // Si intenta comentar sin login
        header("Location: login.php");
        exit();
    }
}

// 4. BORRAR COMENTARIO (Solo Admin)
if (isset($_GET['borrar_comentario']) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    $idCom = intval($_GET['borrar_comentario']);
    $conn->query("DELETE FROM comentarios WHERE id = $idCom");
    header("Location: visor.php?capId=$capId&obraId=$obraId&origen=$origen");
    exit();
}

// 5. OBTENER DATOS CAPÍTULO Y NAVEGACIÓN (Anterior/Siguiente)
$sql = "SELECT c.*, o.titulo as titulo_obra FROM capitulos c JOIN obras o ON c.obra_id = o.id WHERE c.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $capId);
$stmt->execute();
$capitulo = $stmt->get_result()->fetch_assoc();

if (!$capitulo) die("<h1>Capítulo no encontrado.</h1>");

// Anterior / Siguiente
$sqlPrev = "SELECT id FROM capitulos WHERE obra_id = ? AND id < ? ORDER BY id DESC LIMIT 1";
$stmtPrev = $conn->prepare($sqlPrev);
$stmtPrev->bind_param("ii", $capitulo['obra_id'], $capId);
$stmtPrev->execute();
$idPrev = $stmtPrev->get_result()->fetch_assoc()['id'] ?? null;

$sqlNext = "SELECT id FROM capitulos WHERE obra_id = ? AND id > ? ORDER BY id ASC LIMIT 1";
$stmtNext = $conn->prepare($sqlNext);
$stmtNext->bind_param("ii", $capitulo['obra_id'], $capId);
$stmtNext->execute();
$idNext = $stmtNext->get_result()->fetch_assoc()['id'] ?? null;

// Decodificar imágenes
$lista_imagenes = json_decode($capitulo['contenido'], true);
if (!is_array($lista_imagenes)) $lista_imagenes = [];

// 6. OBTENER COMENTARIOS DEL CAPÍTULO
// Hacemos JOIN con usuarios para sacar nombre y foto
$sqlCom = "SELECT c.*, u.nombre, u.foto, u.rol 
           FROM comentarios c 
           JOIN usuarios u ON c.usuario_id = u.id 
           WHERE c.capitulo_id = $capId 
           ORDER BY c.fecha DESC";
$resCom = $conn->query($sqlCom);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $capitulo['titulo']; ?> - Visor</title>
    
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo time(); ?>">
    
    <style>
        /* Estilos específicos para la zona de comentarios */
        .zona-comentarios { max-width: 800px; margin: 0 auto; padding: 2rem 1rem; color: #ccc; }
        .caja-comentario { background: #1a1a1a; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; display: flex; gap: 1rem; }
        .avatar-comentario { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .info-comentario { flex: 1; }
        .nombre-usuario { font-weight: bold; color: white; margin-bottom: 0.2rem; display: block; }
        .fecha-comentario { font-size: 0.8rem; color: #666; }
        .texto-comentario { color: #ddd; margin-top: 0.5rem; }
        .form-comentario textarea { background: #262626; border: 1px solid #333; color: white; resize: none; }
        .form-comentario textarea:focus { background: #333; border-color: #2563eb; outline: none; box-shadow: none; }
    </style>
</head>
<body class="visor-body">

    <div class="visor-barra">
        <button class="btn-volver" id="btn-cerrar">
            <i class="fas fa-times"></i> <span class="d-none d-md-inline">Cerrar</span>
        </button>
        
        <span id="titulo-capitulo" style="font-weight:bold; font-size: 0.9rem;">
            <?php echo $capitulo['titulo']; ?>
        </span>
        
        <div style="display:flex; gap: 0.5rem;">
            <?php if($idPrev): ?>
                <a href="visor.php?capId=<?php echo $idPrev; ?>&obraId=<?php echo $obraId; ?>&origen=<?php echo $origen; ?>" class="btn btn-sm btn-secondary btn-mini-nav"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
                <button class="btn btn-sm btn-secondary btn-mini-nav" disabled style="opacity:0.3"><i class="fas fa-chevron-left"></i></button>
            <?php endif; ?>

            <?php if($idNext): ?>
                <a href="visor.php?capId=<?php echo $idNext; ?>&obraId=<?php echo $obraId; ?>&origen=<?php echo $origen; ?>" class="btn btn-sm btn-primary btn-mini-nav"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
                <button class="btn btn-sm btn-secondary btn-mini-nav" disabled style="opacity:0.3"><i class="fas fa-chevron-right"></i></button>
            <?php endif; ?>
        </div>
    </div>

    <div class="visor-contenido" id="contenedor-imagenes">
        <?php foreach ($lista_imagenes as $url): ?>
            <img src="<?php echo $url; ?>" class="pagina-manga" loading="lazy" alt="Página">
        <?php endforeach; ?>
        <?php if(empty($lista_imagenes)): ?>
            <div style="padding: 5rem; text-align: center; color: #666;"><i class="fas fa-images fa-2x mb-3"></i><br>Sin imágenes.</div>
        <?php endif; ?>
    </div>

    <div class="navegacion-capitulos">
        <?php if($idPrev): ?>
            <a href="visor.php?capId=<?php echo $idPrev; ?>&obraId=<?php echo $obraId; ?>&origen=<?php echo $origen; ?>" class="btn-nav-cap btn-anterior"><i class="fas fa-arrow-left"></i> Anterior</a>
        <?php else: ?>
            <div class="btn-nav-cap btn-disabled">Primer Capítulo</div>
        <?php endif; ?>

        <?php if($idNext): ?>
            <a href="visor.php?capId=<?php echo $idNext; ?>&obraId=<?php echo $obraId; ?>&origen=<?php echo $origen; ?>" class="btn-nav-cap btn-siguiente">Siguiente <i class="fas fa-arrow-right"></i></a>
        <?php else: ?>
            <div class="btn-nav-cap btn-disabled">Último Capítulo 🚫</div>
        <?php endif; ?>
    </div>

    <div class="zona-comentarios">
        <h4 class="mb-4"><i class="far fa-comments"></i> Comentarios (<?php echo $resCom->num_rows; ?>)</h4>

        <?php if(isset($_SESSION['usuario'])): ?>
            <form method="POST" action="" class="form-comentario mb-5">
                <div class="mb-2">
                    <textarea name="comentario" class="form-control" rows="3" placeholder="Escribe tu opinión sobre este capítulo..." required></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary btn-sm">Publicar Comentario</button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-dark text-center mb-5">
                <a href="login.php" class="text-info">Inicia sesión</a> para dejar un comentario.
            </div>
        <?php endif; ?>

        <?php if ($resCom->num_rows > 0): ?>
            <?php while($com = $resCom->fetch_assoc()): ?>
                <div class="caja-comentario">
                    <?php $fotoUser = !empty($com['foto']) ? $com['foto'] : 'https://via.placeholder.com/40'; ?>
                    <img src="<?php echo $fotoUser; ?>" class="avatar-comentario" alt="Avatar">
                    
                    <div class="info-comentario">
                        <div class="d-flex justify-content-between">
                            <span class="nombre-usuario">
                                <?php echo htmlspecialchars($com['nombre']); ?>
                                <?php if($com['rol'] === 'admin'): ?>
                                    <span class="badge bg-danger" style="font-size:0.6rem">ADMIN</span>
                                <?php endif; ?>
                            </span>
                            <span class="fecha-comentario">
                                <?php echo date('d/m/Y H:i', strtotime($com['fecha'])); ?>
                                
                                <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                    <a href="visor.php?capId=<?php echo $capId; ?>&obraId=<?php echo $obraId; ?>&origen=<?php echo $origen; ?>&borrar_comentario=<?php echo $com['id']; ?>" 
                                       class="text-danger ms-2 text-decoration-none" title="Borrar"
                                       onclick="return confirm('¿Borrar este comentario?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </span>
                        </div>
                        <p class="texto-comentario"><?php echo nl2br(htmlspecialchars($com['texto'])); ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center text-muted">Sé el primero en comentar.</p>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('btn-cerrar').onclick = () => {
            window.location.href = '<?php echo $url_volver; ?>';
        };
    </script>
</body>
</html>