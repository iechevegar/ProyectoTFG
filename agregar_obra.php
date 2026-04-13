<?php
session_start();
require 'includes/db.php';
require 'includes/funciones.php'; // <-- IMPORTAMOS LA FUNCIÓN MÁGICA

// SEGURIDAD: Solo Admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /");
    exit();
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $autor = $conn->real_escape_string($_POST['autor']);
    $generos = $conn->real_escape_string($_POST['generos']);
    $sinopsis = $conn->real_escape_string($_POST['sinopsis']);

    // RECOGEMOS LOS NUEVOS CAMPOS DEL SELECT
    $tipo_obra = $conn->real_escape_string($_POST['tipo_obra']);
    $demografia = $conn->real_escape_string($_POST['demografia']);

    // --- MAGIA: CREACIÓN DEL SLUG AUTOMÁTICO ---
    $slug = limpiarURL($_POST['titulo']);

    // Comprobar que no exista ya otra obra con el mismo slug exacto (anti-crasheo)
    $comprobacion = $conn->query("SELECT id FROM obras WHERE slug = '$slug'");
    if ($comprobacion && $comprobacion->num_rows > 0) {
        $slug = $slug . '-' . rand(100, 999); // Le añadimos un número aleatorio si se repite
    }
    // -------------------------------------------

    $ruta_portada = ''; // Por defecto vacía

    // --- LÓGICA PROFESIONAL DE SUBIDA DE IMÁGENES ---
    if (isset($_FILES['portada']) && $_FILES['portada']['error'] === 0) {

        $directorio_destino = 'assets/img/portadas/';
        $extension = pathinfo($_FILES['portada']['name'], PATHINFO_EXTENSION);
        $nombre_archivo = 'portada_' . time() . '.' . $extension;
        $ruta_final = $directorio_destino . $nombre_archivo;

        if (move_uploaded_file($_FILES['portada']['tmp_name'], $ruta_final)) {
            $ruta_portada = $ruta_final;
        } else {
            $mensaje = '<div class="alert alert-danger">Error físico al subir la imagen al servidor. Revisa los permisos de la carpeta.</div>';
        }
    } else {
        $ruta_portada = 'https://via.placeholder.com/400x600?text=Sin+Portada';
    }

    // Si todo va bien, insertamos en la BD añadiendo los nuevos campos ENUM
    if (empty($mensaje)) {
        $sql = "INSERT INTO obras (titulo, slug, autor, generos, sinopsis, portada, tipo_obra, demografia) 
                VALUES ('$titulo', '$slug', '$autor', '$generos', '$sinopsis', '$ruta_portada', '$tipo_obra', '$demografia')";

        if ($conn->query($sql)) {
            // Mensaje de éxito con un enlace directo a la obra recién creada
            $mensaje = '<div class="alert alert-success shadow-sm border-success">
                            <i class="fas fa-check-circle me-2"></i> ¡Obra subida con éxito! 
                            <a href="/obra/' . $slug . '" class="alert-link ms-2 fw-bold">Ver obra publicada</a>
                        </div>';
        } else {
            $mensaje = '<div class="alert alert-danger">Error en la base de datos: ' . $conn->error . '</div>';
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="/admin" class="text-decoration-none text-muted mb-1 d-inline-block">
                        <i class="fas fa-arrow-left"></i> Volver al Panel
                    </a>
                    <h2 class="fw-bold text-dark m-0"><i class="fas fa-book-medical text-success me-2"></i> Añadir Nueva
                        Obra</h2>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card shadow-sm border-0 border-top border-success border-3 bg-white">
                <div class="card-body p-4">

                    <form action="" method="POST" enctype="multipart/form-data">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Título de la Obra
                                    *</label>
                                <input type="text" name="titulo" class="form-control bg-light" required
                                    placeholder="Ej: Solo Leveling">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Autor(es)
                                    *</label>
                                <input type="text" name="autor" class="form-control bg-light" required
                                    placeholder="Ej: Chugong">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Tipo de
                                    Obra</label>
                                <select name="tipo_obra" class="form-select bg-light">
                                    <option value="Desconocido" selected>Seleccionar Tipo...</option>
                                    <option value="Manga">Manga (Japonés)</option>
                                    <option value="Manhwa">Manhwa (Coreano)</option>
                                    <option value="Manhua">Manhua (Chino)</option>
                                    <option value="Donghua">Donghua (Animación)</option>
                                    <option value="Novela">Novela / Libro</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Demografía</label>
                                <select name="demografia" class="form-select bg-light">
                                    <option value="Desconocido" selected>Seleccionar Demografía...</option>
                                    <option value="Shounen">Shounen (Acción/Aventura Joven)</option>
                                    <option value="Seinen">Seinen (Adulto/Maduro)</option>
                                    <option value="Shoujo">Shoujo (Romance/Drama Joven)</option>
                                    <option value="Josei">Josei (Romance/Adulto)</option>
                                    <option value="Kodomo">Kodomo (Infantil)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Géneros *</label>
                            <input type="text" name="generos" class="form-control bg-light" required
                                placeholder="Ej: Acción, Fantasía, Aventura (separados por coma)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Sinopsis</label>
                            <textarea name="sinopsis" class="form-control bg-light" rows="5"
                                placeholder="Escribe un resumen de la trama..."></textarea>
                        </div>

                        <div class="mb-4 p-3 bg-light rounded border">
                            <label class="form-label fw-bold text-primary small text-uppercase"><i
                                    class="fas fa-file-upload me-1"></i> Subir Portada (JPG, PNG) *</label>
                            <input type="file" name="portada" class="form-control" accept="image/*" required>
                            <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i> La imagen se subirá
                                directamente a la carpeta del servidor.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 border-top pt-4 mt-2">
                            <a href="/admin" class="btn btn-light fw-bold px-4 border">Cancelar</a>
                            <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm">
                                <i class="fas fa-save me-2"></i> Publicar Obra
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>