<?php
session_start();
require 'includes/db.php';
require 'includes/funciones.php';

// =========================================================================================
// 1. MIDDLEWARE DE AUTENTICACIÓN Y AUTORIZACIÓN (RBAC)
// =========================================================================================
// Restringimos el acceso de forma estricta. Si el cliente no presenta una sesión válida
// con el rol de 'admin', cortamos el flujo de ejecución (exit) y lo expulsamos.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /");
    exit();
}

$mensaje = '';

// Precargamos el catálogo de géneros desde la base de datos para renderizar dinámicamente
// los checkboxes en la vista. Ordenamos alfabéticamente para mejorar la UX del administrador.
$resGeneros = $conn->query("SELECT * FROM generos ORDER BY nombre ASC");

// =========================================================================================
// 2. PROCESAMIENTO DEL PAYLOAD (MÉTODO POST)
// =========================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- SANITIZACIÓN DE ENTRADAS (Anti-SQLi) ---
    // Limpiamos agresivamente todas las cadenas de texto recibidas del formulario
    // para neutralizar cualquier intento de Inyección SQL.
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $autor = $conn->real_escape_string($_POST['autor']);
    $sinopsis = $conn->real_escape_string($_POST['sinopsis']);
    $tipo_obra = $conn->real_escape_string($_POST['tipo_obra']);
    $demografia = $conn->real_escape_string($_POST['demografia']);
    $estado_publicacion = $conn->real_escape_string($_POST['estado_publicacion']);

    // --- ALGORITMO DE GENERACIÓN DE RUTAS SEMÁNTICAS (SLUGS) ---
    // Convertimos el título ingresado a un formato URL-friendly (ej: "Solo Leveling" -> "solo-leveling").
    $slug = limpiarURL($_POST['titulo']);
    
    // Implementamos una rutina de resolución de colisiones: consultamos si el slug ya existe.
    // De ser así, inyectamos entropía concatenando un sufijo numérico aleatorio para 
    // asegurar que no violemos la restricción UNIQUE de la columna 'slug' en la BD.
    $comprobacion = $conn->query("SELECT id FROM obras WHERE slug = '$slug'");
    if ($comprobacion && $comprobacion->num_rows > 0) {
        $slug = $slug . '-' . rand(100, 999);
    }

    $ruta_portada = '';

    // --- MANEJO DE I/O: SUBIDA DEL ARCHIVO FÍSICO (PORTADA) ---
    if (isset($_FILES['portada']) && $_FILES['portada']['error'] === 0) {
        $directorio_destino = 'assets/img/portadas/';
        $extension = pathinfo($_FILES['portada']['name'], PATHINFO_EXTENSION);
        
        // Renombramos el archivo adjuntando un timestamp UNIX (time()) para dos propósitos:
        // 1. Evitar sobreescrituras en disco si dos portadas se llaman "cover.jpg".
        // 2. Invalidar la caché del navegador de los usuarios de forma automática (Cache Busting).
        $nombre_archivo = 'portada_' . time() . '.' . $extension;
        $ruta_final = $directorio_destino . $nombre_archivo;

        if (move_uploaded_file($_FILES['portada']['tmp_name'], $ruta_final)) {
            $ruta_portada = $ruta_final; // Registramos la ruta relativa exitosa
        } else {
            $mensaje = '<div class="alert alert-danger">Error físico al subir la imagen al servidor. Verifique los permisos CHMOD.</div>';
        }
    } else {
        // Fallback (Plan B): Si el admin no sube imagen o hay error, asignamos un placeholder por defecto
        // para no romper el layout del frontend en el catálogo.
        $ruta_portada = 'https://via.placeholder.com/400x600?text=Sin+Portada';
    }

    // =========================================================================================
    // 3. PERSISTENCIA DE DATOS Y GESTIÓN RELACIONAL
    // =========================================================================================
    if (empty($mensaje)) {
        
        // FASE A: Inserción de la entidad Padre (Tabla 'obras')
        $sql = "INSERT INTO obras (titulo, slug, autor, sinopsis, portada, tipo_obra, demografia, estado_publicacion) 
                VALUES ('$titulo', '$slug', '$autor', '$sinopsis', '$ruta_portada', '$tipo_obra', '$demografia', '$estado_publicacion')";

        if ($conn->query($sql)) {
            // Recuperamos la Primary Key auto-generada para poder enlazar las relaciones foráneas
            $obra_id = $conn->insert_id;

            // FASE B: Inserción en Tabla Pivote (Relación Many-to-Many N:M para géneros)
            if (isset($_POST['generos']) && is_array($_POST['generos'])) {
                // Compilamos la sentencia SQL una sola vez (Prepared Statement) fuera del bucle
                // Esto optimiza drásticamente el rendimiento del motor de base de datos.
                $stmt_gen = $conn->prepare("INSERT INTO obra_genero (obra_id, genero_id) VALUES (?, ?)");
                
                foreach ($_POST['generos'] as $gen_id) {
                    $g_id = intval($gen_id);
                    $stmt_gen->bind_param("ii", $obra_id, $g_id); // Inyectamos las variables dinámicamente
                    $stmt_gen->execute();
                }
            }

            // Notificación visual de éxito, proporcionando un enlace de comprobación directa usando el Slug
            $mensaje = '<div class="alert alert-success shadow-sm border-success">
                            <i class="fas fa-check-circle me-2"></i> ¡Obra subida con éxito! 
                            <a href="/obra/' . $slug . '" class="alert-link ms-2 fw-bold">Ver obra publicada</a>
                        </div>';
        } else {
            $mensaje = '<div class="alert alert-danger">Error en la transacción SQL: ' . $conn->error . '</div>';
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4 admin-main-container">
    <div class="row justify-content-center">
        <div class="col-md-10">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="/admin" class="text-decoration-none text-muted mb-1 d-inline-block hover-iori transition-colors">
                        <i class="fas fa-arrow-left"></i> Volver al Panel
                    </a>
                    <h2 class="fw-bold text-dark m-0"><i class="fas fa-book-medical text-iori me-2"></i> Añadir Nueva Obra</h2>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card shadow-sm border-0 border-top border-iori border-3 bg-white rounded-4">
                <div class="card-body p-4">

                    <form action="" method="POST" enctype="multipart/form-data">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Título de la Obra *</label>
                                <input type="text" name="titulo" class="form-control bg-light border-light shadow-sm" required placeholder="Ej: Solo Leveling">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Autor(es) *</label>
                                <input type="text" name="autor" class="form-control bg-light border-light shadow-sm" required placeholder="Ej: Chugong">
                            </div>
                        </div>

                        <div class="row mb-4 bg-light p-3 rounded-4 border mx-0 border-light shadow-sm">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Tipo de Obra</label>
                                <select name="tipo_obra" class="form-select bg-white border-secondary shadow-sm">
                                    <option value="Desconocido" selected>Seleccionar Tipo...</option>
                                    <option value="Manga">Manga (Japonés)</option>
                                    <option value="Manhwa">Manhwa (Coreano)</option>
                                    <option value="Manhua">Manhua (Chino)</option>
                                    <option value="Donghua">Donghua (Animación)</option>
                                    <option value="Novela">Novela / Libro</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3 mb-md-0">
                                <label class="form-label fw-bold text-secondary small text-uppercase">Demografía</label>
                                <select name="demografia" class="form-select bg-white border-secondary shadow-sm">
                                    <option value="Desconocido" selected>Seleccionar Demografía...</option>
                                    <option value="Shounen">Shounen (Jóvenes)</option>
                                    <option value="Seinen">Seinen (Adultos)</option>
                                    <option value="Shoujo">Shoujo (Romance Joven)</option>
                                    <option value="Josei">Josei (Romance Adulto)</option>
                                    <option value="Kodomo">Kodomo (Infantil)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-iori small text-uppercase">Estado *</label>
                                <select name="estado_publicacion" class="form-select border-iori bg-white shadow-sm">
                                    <option value="En Emisión" selected>En Emisión</option>
                                    <option value="Hiatus">Hiatus (Pausado)</option>
                                    <option value="Finalizado">Finalizado</option>
                                    <option value="Cancelado">Cancelado</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase mb-3">Géneros de la obra *</label>
                            <div class="row bg-light p-3 rounded-4 border border-light shadow-sm mx-0">
                                <?php
                                // Renderizado dinámico del grid de checkboxes basado en el catálogo extraído al inicio
                                if ($resGeneros->num_rows > 0) {
                                    $resGeneros->data_seek(0); // Reiniciamos el puntero por buenas prácticas
                                    while ($g = $resGeneros->fetch_assoc()):
                                        ?>
                                        <div class="col-md-3 col-sm-4 col-6 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input border-secondary" type="checkbox"
                                                    name="generos[]" value="<?php echo $g['id']; ?>"
                                                    id="gen_<?php echo $g['id']; ?>">
                                                <label class="form-check-label text-dark fw-semibold" for="gen_<?php echo $g['id']; ?>">
                                                    <?php echo htmlspecialchars($g['nombre']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php
                                    endwhile;
                                } else {
                                    echo '<div class="col-12 text-muted">No hay géneros creados en el sistema.</div>';
                                }
                                ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Sinopsis</label>
                            <textarea name="sinopsis" class="form-control bg-light border-light shadow-sm" rows="5"
                                placeholder="Escribe un resumen de la trama..."></textarea>
                        </div>

                        <div class="mb-4 p-3 bg-light rounded-4 border border-light shadow-sm">
                            <label class="form-label fw-bold text-iori small text-uppercase"><i
                                    class="fas fa-file-upload me-1"></i> Subir Portada (JPG, PNG) *</label>
                            <input type="file" name="portada" class="form-control bg-white border-secondary" accept="image/*" required>
                        </div>

                        <div class="d-flex justify-content-end gap-3 border-top pt-4 mt-2">
                            <a href="/admin" class="btn bg-light text-dark border hover-bg-light fw-bold px-4 rounded-pill shadow-sm transition-colors">Cancelar</a>
                            <button type="submit" class="btn btn-iori fw-bold px-4 shadow-sm rounded-pill">
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