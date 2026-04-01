<?php
session_start();
require 'includes/db.php';

// SEGURIDAD: Solo Admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $autor = $conn->real_escape_string($_POST['autor']);
    $generos = $conn->real_escape_string($_POST['generos']);
    $sinopsis = $conn->real_escape_string($_POST['sinopsis']);
    
    $ruta_portada = ''; // Por defecto vacía

    // --- LÓGICA PROFESIONAL DE SUBIDA DE IMÁGENES ---
    // Comprobamos si se ha subido un archivo y si no hay errores (error 0)
    if (isset($_FILES['portada']) && $_FILES['portada']['error'] === 0) {
        
        $directorio_destino = 'assets/img/portadas/';
        
        // Extraemos la extensión original del archivo (ej: jpg, png)
        $extension = pathinfo($_FILES['portada']['name'], PATHINFO_EXTENSION);
        
        // Creamos un nombre ÚNICO para que no choquen si se llaman igual
        // Ejemplo: portada_16843254.jpg
        $nombre_archivo = 'portada_' . time() . '.' . $extension;
        
        $ruta_final = $directorio_destino . $nombre_archivo;

        // Movemos el archivo de la memoria temporal del servidor a nuestra carpeta física
        if (move_uploaded_file($_FILES['portada']['tmp_name'], $ruta_final)) {
            $ruta_portada = $ruta_final; // Esta es la ruta que guardaremos en la Base de Datos
        } else {
            $mensaje = '<div class="alert alert-danger">Error físico al subir la imagen al servidor. Revisa los permisos de la carpeta.</div>';
        }
    } else {
        // Si no sube imagen o hay error, le ponemos una genérica temporal para que no se rompa la web
        $ruta_portada = 'https://via.placeholder.com/400x600?text=Sin+Portada';
    }

    // Si todo va bien (o si ha usado la genérica), insertamos en la BD
    if (empty($mensaje)) {
        $sql = "INSERT INTO obras (titulo, autor, generos, sinopsis, portada) 
                VALUES ('$titulo', '$autor', '$generos', '$sinopsis', '$ruta_portada')";
        
        if ($conn->query($sql)) {
            $mensaje = '<div class="alert alert-success">¡Obra y portada subidas con éxito!</div>';
        } else {
            $mensaje = '<div class="alert alert-danger">Error en la base de datos: ' . $conn->error . '</div>';
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-5" style="max-width: 800px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0"><i class="fas fa-book-medical text-success me-2"></i> Añadir Nueva Obra</h2>
        <a href="admin.php" class="btn btn-outline-secondary btn-sm fw-bold">
            <i class="fas fa-arrow-left me-1"></i> Volver
        </a>
    </div>

    <?php echo $mensaje; ?>

    <div class="card shadow-sm border-0 bg-white">
        <div class="card-body p-4 p-md-5">
            
            <form action="" method="POST" enctype="multipart/form-data">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Título de la Obra *</label>
                        <input type="text" name="titulo" class="form-control" required placeholder="Ej: Solo Leveling">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Autor(es) *</label>
                        <input type="text" name="autor" class="form-control" required placeholder="Ej: Chugong">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Géneros *</label>
                    <input type="text" name="generos" class="form-control" required placeholder="Ej: Acción, Fantasía, Aventura (separados por coma)">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Sinopsis</label>
                    <textarea name="sinopsis" class="form-control" rows="4" placeholder="Escribe un resumen de la trama..."></textarea>
                </div>

                <div class="mb-4 p-3 bg-light border rounded">
                    <label class="form-label fw-bold text-primary"><i class="fas fa-file-upload me-2"></i>Subir Portada (JPG, PNG)</label>
                    <input type="file" name="portada" class="form-control" accept="image/*" required>
                    <small class="text-muted mt-1 d-block">La imagen se subirá directamente a la carpeta del servidor.</small>
                </div>

                <div class="text-end border-top pt-4 mt-2">
                    <button type="submit" class="btn btn-success btn-lg fw-bold px-5 shadow-sm">
                        <i class="fas fa-save me-2"></i> Publicar Obra
                    </button>
                </div>

            </form>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>