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

// Obtener título de la obra (solo para mostrarlo y para la carpeta)
$sqlObra = "SELECT titulo FROM obras WHERE id = $idObra";
$resObra = $conn->query($sqlObra);
if($resObra->num_rows === 0) {
    header("Location: admin.php");
    exit();
}
$datosObra = $resObra->fetch_assoc();

// PROCESAR SUBIDA
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo_cap = $conn->real_escape_string(trim($_POST['titulo']));
    
    $rutas_imagenes = [];
    $error_subida = false;

    // --- NUEVA LÓGICA DE CARPETAS ESTRUCTURADAS ---
    // 1. Limpiamos los nombres para evitar errores en el sistema de archivos (reemplaza espacios y símbolos por guiones bajos)
    $nombre_obra_carpeta = preg_replace('/[^a-zA-Z0-9]/', '_', trim($datosObra['titulo']));
    $nombre_obra_carpeta = preg_replace('/_+/', '_', $nombre_obra_carpeta); // Evita guiones bajos duplicados

    $nombre_cap_carpeta = preg_replace('/[^a-zA-Z0-9]/', '_', trim($titulo_cap));
    $nombre_cap_carpeta = preg_replace('/_+/', '_', $nombre_cap_carpeta);

    // 2. Construimos la ruta dinámica: assets/img/capitulos/Solo_Leveling/Capitulo_1/
    $carpeta_destino = "assets/img/capitulos/" . $nombre_obra_carpeta . "/" . $nombre_cap_carpeta . "/";

    // 3. Creamos la carpeta si no existe (el true indica que cree subcarpetas recursivamente si hace falta)
    if (!file_exists($carpeta_destino)) {
        mkdir($carpeta_destino, 0777, true);
    }
    // ----------------------------------------------

    // RECOPILAR Y ORDENAR ARCHIVOS
    if (isset($_FILES['paginas']) && !empty($_FILES['paginas']['name'][0])) {
        
        $archivos_subidos = [];
        $cantidad = count($_FILES['paginas']['name']);
        
        // Extraemos los datos a un array más manejable
        for ($i = 0; $i < $cantidad; $i++) {
            if ($_FILES['paginas']['error'][$i] === 0) {
                $archivos_subidos[] = [
                    'name' => $_FILES['paginas']['name'][$i],
                    'tmp_name' => $_FILES['paginas']['tmp_name'][$i],
                    'ext' => pathinfo($_FILES['paginas']['name'][$i], PATHINFO_EXTENSION)
                ];
            }
        }

        // Ordenamiento Natural (Para que la página 2 vaya antes que la 10 basándose en su nombre original)
        usort($archivos_subidos, function($a, $b) {
            return strnatcmp($a['name'], $b['name']);
        });

        // MOVER ARCHIVOS AL SERVIDOR Y GUARDAR RUTAS
        foreach ($archivos_subidos as $index => $archivo) {
            // Limpiamos el nombre original de la foto
            $nombre_limpio = preg_replace("/[^a-zA-Z0-9.]/", "", basename($archivo['name']));
            
            // Generamos el nombre final (Como ya están en su propia carpeta, los nombramos simplemente con un índice delante)
            $nombre_final = str_pad($index, 3, "0", STR_PAD_LEFT) . "_" . $nombre_limpio;
            $ruta_final = $carpeta_destino . $nombre_final;
            
            if (move_uploaded_file($archivo['tmp_name'], $ruta_final)) {
                $rutas_imagenes[] = $ruta_final; // Guardamos la ruta exacta para la Base de Datos
            } else {
                $error_subida = true;
            }
        }
    }

    // GUARDAR EN BASE DE DATOS
    if (!$error_subida && count($rutas_imagenes) > 0) {
        // Convertir array de rutas a JSON para la BD
        $contenido_json = json_encode($rutas_imagenes);
        
        $sql = "INSERT INTO capitulos (obra_id, titulo, contenido) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $idObra, $titulo_cap, $contenido_json);
        
        if ($stmt->execute()) {
            header("Location: admin.php?msg=Capítulo subido correctamente a " . urlencode($datosObra['titulo']));
            exit();
        } else {
            $mensaje = "<div class='alert alert-danger shadow-sm'><i class='fas fa-exclamation-triangle me-2'></i> Error al guardar en la base de datos.</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-warning shadow-sm'><i class='fas fa-info-circle me-2'></i> Debes seleccionar al menos una imagen válida o revisar los permisos.</div>";
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-5" style="max-width: 800px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Subir Capítulo</h2>
        <a href="admin.php" class="btn btn-outline-secondary btn-sm fw-bold">
            <i class="fas fa-arrow-left me-1"></i> Volver
        </a>
    </div>

    <?php echo $mensaje; ?>

    <div class="card shadow-sm border-0 bg-white">
        <div class="card-body p-4 p-md-5">
            
            <div class="alert alert-light border border-primary border-start-5 border-end-0 border-top-0 border-bottom-0 mb-4">
                <p class="mb-0">Obra destino: <strong class="text-primary fs-5"><?php echo htmlspecialchars($datosObra['titulo']); ?></strong></p>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                
                <div class="mb-4">
                    <label class="form-label fw-bold">Título del Capítulo *</label>
                    <input type="text" name="titulo" class="form-control" placeholder="Ej: Capítulo 1: El despertar" required>
                </div>

                <div class="mb-4 p-3 bg-light border rounded">
                    <label class="form-label fw-bold text-primary"><i class="fas fa-images me-2"></i>Páginas del Capítulo (JPG, PNG)</label>
                    <input type="file" name="paginas[]" class="form-control" multiple accept="image/*" required>
                    
                    <div class="mt-2 text-muted small">
                        <ul class="mb-0 ps-3">
                            <li>Puedes seleccionar varias imágenes a la vez manteniendo pulsado <strong>CTRL</strong> o arrastrando el ratón.</li>
                            <li><strong>Importante:</strong> Nombra tus archivos con números (ej: <code>01.jpg</code>, <code>02.jpg</code>) para que el sistema las ordene automáticamente.</li>
                            <li>Las imágenes se guardarán de forma organizada en <code>/assets/img/capitulos/NombreObra/NombreCapitulo/</code></li>
                        </ul>
                    </div>
                </div>

                <div class="text-end border-top pt-4 mt-2">
                    <button type="submit" class="btn btn-success btn-lg fw-bold px-5 shadow-sm">
                        <i class="fas fa-upload me-2"></i> Publicar Capítulo
                    </button>
                </div>
                
            </form>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>