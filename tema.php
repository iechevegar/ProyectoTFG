<?php
session_start();
require 'includes/db.php';

// Validar ID del tema
if (!isset($_GET['id'])) {
    header("Location: foro.php");
    exit();
}

$idTema = intval($_GET['id']);

// ---------------------------------------------------------
// 1. LÓGICA DE POST Y ACCIONES
// ---------------------------------------------------------

// A. PROCESAR NUEVA RESPUESTA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['respuesta'])) {
    if (isset($_SESSION['usuario'])) {
        $mensaje = trim($_POST['respuesta']);
        if (!empty($mensaje)) {
            // Obtener ID usuario actual
            $nombreUser = $_SESSION['usuario'];
            $resUser = $conn->query("SELECT id FROM usuarios WHERE nombre = '$nombreUser'");
            $userId = $resUser->fetch_assoc()['id'];

            // Insertar respuesta
            $stmt = $conn->prepare("INSERT INTO foro_respuestas (tema_id, usuario_id, mensaje) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $idTema, $userId, $mensaje);
            $stmt->execute();
            
            // Recargar página para ver la respuesta
            header("Location: tema.php?id=$idTema");
            exit();
        }
    } else {
        header("Location: login.php");
        exit();
    }
}

// B. BORRAR TEMA (Solo Admin)
if (isset($_GET['borrar_tema']) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    // Al borrar el tema, se borran las respuestas en cascada (según tu DB)
    $conn->query("DELETE FROM foro_temas WHERE id = $idTema");
    header("Location: foro.php");
    exit();
}

// C. BORRAR RESPUESTA (Solo Admin)
if (isset($_GET['borrar_resp']) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    $idResp = intval($_GET['borrar_resp']);
    $conn->query("DELETE FROM foro_respuestas WHERE id = $idResp");
    header("Location: tema.php?id=$idTema");
    exit();
}

// ---------------------------------------------------------
// 2. CONSULTAS DE DATOS
// ---------------------------------------------------------

// A. OBTENER DATOS DEL TEMA PRINCIPAL
$sqlTema = "SELECT t.*, u.nombre, u.foto, u.rol 
            FROM foro_temas t 
            JOIN usuarios u ON t.usuario_id = u.id 
            WHERE t.id = $idTema";
$resTema = $conn->query($sqlTema);
$tema = $resTema->fetch_assoc();

if (!$tema) {
    echo "<div class='container py-5'><h1>Tema no encontrado o eliminado.</h1><a href='foro.php'>Volver</a></div>";
    exit();
}

// B. OBTENER RESPUESTAS
$sqlResp = "SELECT r.*, u.nombre, u.foto, u.rol 
            FROM foro_respuestas r 
            JOIN usuarios u ON r.usuario_id = u.id 
            WHERE r.tema_id = $idTema 
            ORDER BY r.fecha ASC";
$respuestas = $conn->query($sqlResp);
?>

<?php include 'includes/header.php'; ?>

<main class="container py-5">
    
    <div class="mb-3">
        <a href="foro.php" class="text-decoration-none text-muted">
            <i class="fas fa-arrow-left"></i> Volver al Foro
        </a>
    </div>

    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <div>
                <span class="badge bg-secondary mb-1"><?php echo htmlspecialchars($tema['categoria']); ?></span>
                <h3 class="mb-0 text-primary fw-bold"><?php echo htmlspecialchars($tema['titulo']); ?></h3>
            </div>
            
            <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                <a href="tema.php?id=<?php echo $idTema; ?>&borrar_tema=1" 
                   class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de borrar TODO este hilo?');">
                    <i class="fas fa-trash-alt me-1"></i> Borrar Hilo
                </a>
            <?php endif; ?>
        </div>
        
        <div class="card-body">
            <div class="d-flex mb-3 align-items-center border-bottom pb-3">
                <?php $foto = !empty($tema['foto']) ? $tema['foto'] : 'https://via.placeholder.com/50'; ?>
                <img src="<?php echo $foto; ?>" class="rounded-circle me-3" width="50" height="50" style="object-fit:cover;">
                <div>
                    <strong class="d-block text-dark">
                        <?php echo htmlspecialchars($tema['nombre']); ?> 
                        <?php if($tema['rol'] === 'admin'): ?>
                            <span class="badge bg-danger" style="font-size:0.7em">ADMIN</span>
                        <?php endif; ?>
                    </strong>
                    
                    <div class="text-muted small">
                        Publicado el <?php echo date('d/m/Y H:i', strtotime($tema['fecha'])); ?>
                        
                        <?php if(!empty($tema['fecha_edicion'])): ?>
                            <span class="fst-italic ms-2 text-secondary">
                                (Editado el <?php echo date('d/m/y H:i', strtotime($tema['fecha_edicion'])); ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card-text fs-5 py-2" style="white-space: pre-wrap; color: #333;"><?php echo htmlspecialchars($tema['contenido']); ?></div>
            
            <?php if(isset($_SESSION['usuario']) && $_SESSION['usuario'] === $tema['nombre']): ?>
                <div class="mt-3 text-end">
                    <a href="editar_tema.php?id=<?php echo $tema['id']; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-pencil-alt me-1"></i> Editar Tema
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <h5 class="mb-3 ps-2 border-start border-4 border-secondary">
        Respuestas <span class="text-muted small">(<?php echo $respuestas->num_rows; ?>)</span>
    </h5>
    
    <?php if ($respuestas->num_rows > 0): ?>
        <?php while($resp = $respuestas->fetch_assoc()): ?>
            <div class="card mb-3 shadow-sm border-0 bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        
                        <div class="d-flex mb-2 align-items-center">
                            <?php $fotoR = !empty($resp['foto']) ? $resp['foto'] : 'https://via.placeholder.com/40'; ?>
                            <img src="<?php echo $fotoR; ?>" class="rounded-circle me-3" width="40" height="40" style="object-fit:cover;">
                            <div>
                                <span class="fw-bold text-dark">
                                    <?php echo htmlspecialchars($resp['nombre']); ?>
                                    <?php if($resp['rol'] === 'admin'): ?>
                                        <span class="badge bg-danger" style="font-size:0.6em">ADMIN</span>
                                    <?php endif; ?>
                                </span>
                                <br>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($resp['fecha'])); ?></small>
                            </div>
                        </div>

                        <div>
                            <?php if(isset($_SESSION['usuario']) && $_SESSION['usuario'] === $resp['nombre']): ?>
                                <a href="editar_respuesta.php?id=<?php echo $resp['id']; ?>" class="text-secondary me-2 text-decoration-none" title="Editar">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                            <?php endif; ?>

                            <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                <a href="tema.php?id=<?php echo $idTema; ?>&borrar_resp=<?php echo $resp['id']; ?>" 
                                   class="text-danger" title="Borrar respuesta" 
                                   onclick="return confirm('¿Borrar esta respuesta permanentemente?');">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-2 ps-5 text-dark">
                        <?php echo nl2br(htmlspecialchars($resp['mensaje'])); ?>
                        
                        <?php if(!empty($resp['fecha_edicion'])): ?>
                            <small class="text-muted fst-italic ms-2" style="font-size: 0.75rem;">
                                (Editado)
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-light text-center border py-4 text-muted">
            No hay respuestas aún. ¡Sé el primero en comentar!
        </div>
    <?php endif; ?>

    <div class="mt-5">
        <?php if(isset($_SESSION['usuario'])): ?>
            <div class="card shadow-sm border-0">
                <div class="card-body bg-white p-4">
                    <h5 class="card-title mb-3 fw-bold"><i class="fas fa-reply me-2"></i>Escribir Respuesta</h5>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <textarea name="respuesta" class="form-control bg-light" rows="4" placeholder="Participa en la discusión..." required></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-success px-4 fw-bold">
                                <i class="fas fa-paper-plane me-2"></i>Enviar Respuesta
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center shadow-sm">
                <i class="fas fa-lock me-2"></i>
                Debes <a href="login.php" class="fw-bold text-dark">iniciar sesión</a> para participar en el foro.
            </div>
        <?php endif; ?>
    </div>

</main>

<?php include 'includes/footer.php'; ?>