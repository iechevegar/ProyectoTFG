<?php
session_start();
require 'includes/db.php';

// SEGURIDAD
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Verificar que nos pasan un ID de obra
if (!isset($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$idObra = intval($_GET['id']);
$mensaje = '';

// Obtener título de la obra (solo para mostrarlo)
$sqlObra = "SELECT titulo FROM obras WHERE id = $idObra";
$resObra = $conn->query($sqlObra);
$datosObra = $resObra->fetch_assoc();

// PROCESAR SUBIDA
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo_cap = trim($_POST['titulo']);
    
    // Array para guardar las rutas de las imágenes subidas
    $rutas_imagenes = [];
    $error_subida = false;

    // Crear carpeta para capítulos si no existe
    $carpeta_destino = "assets/img/capitulos/";
    if (!file_exists($carpeta_destino)) {
        mkdir($carpeta_destino, 0777, true);
    }

    // Procesar múltiples archivos
    if (isset($_FILES['paginas'])) {
        $cantidad = count($_FILES['paginas']['name']);
        
        for ($i = 0; $i < $cantidad; $i++) {
            if ($_FILES['paginas']['error'][$i] === 0) {
                // Generar nombre único: tiempo_indice_nombreOriginal
                $nombre_archivo = time() . "_{$i}_" . basename($_FILES['paginas']['name'][$i]);
                $ruta_final = $carpeta_destino . $nombre_archivo;
                
                if (move_uploaded_file($_FILES['paginas']['tmp_name'][$i], $ruta_final)) {
                    $rutas_imagenes[] = $ruta_final;
                } else {
                    $error_subida = true;
                }
            }
        }
    }

    if (!$error_subida && count($rutas_imagenes) > 0) {
        // Convertir array de rutas a JSON para la BD
        $contenido_json = json_encode($rutas_imagenes);
        
        $sql = "INSERT INTO capitulos (obra_id, titulo, contenido) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $idObra, $titulo_cap, $contenido_json);
        
        if ($stmt->execute()) {
            header("Location: admin.php?msg=Capítulo subido correctamente");
            exit();
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al guardar en BD.</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-warning'>Debes seleccionar al menos una imagen válida.</div>";
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Subir Capítulo a: <span class="text-primary"><?php echo $datosObra['titulo']; ?></span></h3>
                <a href="admin.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>

            <?php echo $mensaje; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form method="POST" action="" enctype="multipart/form-data">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Título del Capítulo</label>
                            <input type="text" name="titulo" class="form-control" placeholder="Ej: Capítulo 1: El comienzo" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Páginas del Capítulo (Imágenes)</label>
                            <input type="file" name="paginas[]" class="form-control" multiple accept="image/*" required>
                            <div class="form-text">Puedes seleccionar varias imágenes a la vez manteniendo pulsado CTRL.</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-upload me-2"></i>Publicar Capítulo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>