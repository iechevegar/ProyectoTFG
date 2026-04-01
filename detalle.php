<?php 
session_start();
require 'includes/db.php';

// 1. Validar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$idObra = intval($_GET['id']);

// CONTADOR DE VISITAS
// Sumamos 1 visita cada vez que se carga la página
$conn->query("UPDATE obras SET visitas = visitas + 1 WHERE id = $idObra");

$datos_obra = null;
$mensaje_resena = '';

// --- LOGICA DE RESEÑAS ---

// A) PROCESAR NUEVA RESEÑA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['texto_resena'])) {
    if (isset($_SESSION['usuario'])) {
        $texto = trim($_POST['texto_resena']);
        if (!empty($texto)) {
            // Obtener ID usuario
            $nombreUser = $_SESSION['usuario'];
            $resUser = $conn->query("SELECT id FROM usuarios WHERE nombre = '$nombreUser'");
            $userId = $resUser->fetch_assoc()['id'];

            // Insertar
            $stmt = $conn->prepare("INSERT INTO resenas (usuario_id, obra_id, texto) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $userId, $idObra, $texto);
            if($stmt->execute()){
                // Recargar para evitar reenvío
                header("Location: detalle.php?id=$idObra");
                exit();
            }
        }
    } else {
        header("Location: login.php");
        exit();
    }
}

// B) BORRAR RESEÑA (Solo Admin)
if (isset($_GET['borrar_resena']) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    $idResena = intval($_GET['borrar_resena']);
    $conn->query("DELETE FROM resenas WHERE id = $idResena");
    header("Location: detalle.php?id=$idObra");
    exit();
}

// C) OBTENER DATOS DE LA OBRA
$sql = "SELECT * FROM obras WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idObra);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $datos_obra = $resultado->fetch_assoc();
    $datos_obra['generos'] = array_map('trim', explode(',', $datos_obra['generos']));

    // Lógica Favoritos
    $es_favorito = false;
    $usuario_logueado = isset($_SESSION['usuario']);
    
    if ($usuario_logueado) {
        $nombreUser = $_SESSION['usuario'];
        $resUser = $conn->query("SELECT id FROM usuarios WHERE nombre = '$nombreUser'");
        $userRow = $resUser->fetch_assoc();
        if ($userRow) {
            $userId = $userRow['id'];
            $resFav = $conn->query("SELECT id FROM favoritos WHERE usuario_id = $userId AND obra_id = $idObra");
            if ($resFav->num_rows > 0) $es_favorito = true;
        }
    }

    // Obtener Capítulos
    $resCaps = $conn->query("SELECT id, titulo, fecha_subida FROM capitulos WHERE obra_id = $idObra ORDER BY id ASC");
    $capitulos = [];
    while($cap = $resCaps->fetch_assoc()) { $capitulos[] = $cap; }
    $datos_obra['capitulos'] = $capitulos;
}

// D) CARGAR LAS RESEÑAS DE ESTA OBRA
$sqlResenas = "SELECT r.*, u.nombre, u.foto, u.rol 
               FROM resenas r 
               JOIN usuarios u ON r.usuario_id = u.id 
               WHERE r.obra_id = $idObra 
               ORDER BY r.fecha DESC";
$lista_resenas = $conn->query($sqlResenas);

include 'includes/header.php'; 
?>

<main style="padding-bottom: 4rem;">
    <div class="container mt-3">
        <a href="index.php" class="text-decoration-none text-muted">
            <i class="fas fa-arrow-left"></i> Volver al catálogo
        </a>
    </div>
    
    <div id="detalle-contenido">
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-spinner fa-spin fa-2x"></i> Cargando datos...
        </div>
    </div>

    <div class="container mt-5 pt-4 border-top">
        <h3 class="mb-4"><i class="fas fa-star text-warning"></i> Reseñas y Opiniones</h3>

        <div class="row">
            <div class="col-md-8">
                
                <?php if($usuario_logueado): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <form method="POST" action="">
                                <label class="form-label fw-bold small">Deja tu reseña:</label>
                                <textarea name="texto_resena" class="form-control mb-2" rows="3" placeholder="¿Qué te ha parecido esta obra?" required></textarea>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary btn-sm">Publicar Opinión</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light border text-center mb-4">
                        <a href="login.php" class="fw-bold">Inicia sesión</a> para escribir una reseña.
                    </div>
                <?php endif; ?>

                <?php if ($lista_resenas->num_rows > 0): ?>
                    <?php while($res = $lista_resenas->fetch_assoc()): ?>
                        <div class="d-flex mb-4">
                            <div class="flex-shrink-0">
                                <?php $foto = !empty($res['foto']) ? $res['foto'] : 'https://via.placeholder.com/50'; ?>
                                <img src="<?php echo $foto; ?>" class="rounded-circle" width="50" height="50" style="object-fit:cover;">
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 fw-bold">
                                        <?php echo htmlspecialchars($res['nombre']); ?>
                                        <?php if($res['rol'] === 'admin'): ?>
                                            <span class="badge bg-danger" style="font-size:0.6em">ADMIN</span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($res['fecha'])); ?>
                                        <?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                            <a href="detalle.php?id=<?php echo $idObra; ?>&borrar_resena=<?php echo $res['id']; ?>" 
                                               class="text-danger ms-2" onclick="return confirm('¿Borrar reseña?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <p class="mb-0 mt-1 text-secondary" style="white-space: pre-wrap;"><?php echo htmlspecialchars($res['texto']); ?></p>
                            </div>
                        </div>
                        <hr class="text-muted" style="opacity: 0.1">
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted">Aún no hay reseñas. ¡Sé el primero en opinar!</p>
                <?php endif; ?>

            </div>
            
            <div class="col-md-4 d-none d-md-block">
                <div class="p-3 bg-light rounded">
                    <h6 class="fw-bold"><i class="fas fa-info-circle"></i> Normas</h6>
                    <p class="small text-muted mb-0">Sé respetuoso. Las reseñas con spoilers o insultos serán eliminadas por los administradores.</p>
                </div>
            </div>
        </div>
    </div>

</main>

<script>
    // --- LÓGICA DE VISUALIZACIÓN DE LA OBRA ---
    const obra = <?php echo json_encode($datos_obra); ?>;
    const esFavorito = <?php echo json_encode($es_favorito); ?>;
    const estaLogueado = <?php echo json_encode($usuario_logueado); ?>;

    document.addEventListener("DOMContentLoaded", () => {
        const contenedor = document.getElementById('detalle-contenido');

        if (!obra) {
            contenedor.innerHTML = "<h1 style='text-align:center; margin-top:2rem'>Obra no encontrada.</h1>";
            return;
        }

        const generosHTML = (obra.generos || []).map(g => `<span class="tag">${g}</span>`).join(' ');
        const imagen = obra.portada || 'https://via.placeholder.com/300x450';

        // Botón Favoritos
        let botonFavHTML = '';
        if (estaLogueado) {
            if (esFavorito) {
                botonFavHTML = `<a href="accion_favorito.php?id=${obra.id}&accion=quitar" class="btn btn-outline-danger ms-3 rounded-pill"><i class="fas fa-heart"></i> Guardado</a>`;
            } else {
                botonFavHTML = `<a href="accion_favorito.php?id=${obra.id}&accion=poner" class="btn btn-outline-primary ms-3 rounded-pill"><i class="far fa-heart"></i> Guardar</a>`;
            }
        }

        // Lista Capítulos
        let capsHTML = '';
        if (obra.capitulos && obra.capitulos.length > 0) {
            capsHTML = obra.capitulos.map(cap => `
                <a href="visor.php?obraId=${obra.id}&capId=${cap.id}" class="capitulo-item">
                    <div>
                        <span class="fw-bold d-block">${cap.titulo}</span>
                        <small class="text-muted">${new Date(cap.fecha_subida).toLocaleDateString()}</small>
                    </div>
                    <i class="fas fa-play-circle text-primary fs-4"></i>
                </a>
            `).join('');
        } else {
            capsHTML = '<p class="text-muted p-3">No hay capítulos disponibles.</p>';
        }

        // PINTAR HTML PRINCIPAL
        contenedor.innerHTML = `
            <div class="detalle-header">
                <img src="${imagen}" class="detalle-img shadow" alt="Portada">
                <div class="detalle-info">
                    <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
                        <h1 class="detalle-titulo mb-0">${obra.titulo}</h1>
                        ${botonFavHTML} 
                    </div>
                    
                    <p class="mb-3">Autor: <strong>${obra.autor}</strong></p>
                    <div class="mb-4">${generosHTML}</div>
                    
                    <h5 class="fw-bold">Sinopsis</h5>
                    <p class="detalle-sinopsis text-muted">${obra.sinopsis || 'Sin sinopsis.'}</p>
                </div>
            </div>
            
            <div class="container mt-4">
                <h3 class="border-bottom pb-2 mb-3">Capítulos <span class="text-muted fs-6">(${obra.capitulos.length})</span></h3>
                <div class="contenedor-capitulos shadow-sm">
                    ${capsHTML}
                </div>
            </div>
        `;
    });
</script>

<?php include 'includes/footer.php'; ?>