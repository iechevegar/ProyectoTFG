<?php
session_start();
require 'includes/db.php';

// 1. SEGURIDAD: Solo admin puede entrar
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$mensaje = '';

// 2. PROCESAR EL FORMULARIO (Cuando se pulsa Guardar)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $titulo = trim($_POST['titulo']);
    $autor = trim($_POST['autor']);
    $generos = trim($_POST['generos']);
    $sinopsis = trim($_POST['sinopsis']);
    
    // 3. GESTIÓN DE LA IMAGEN (PORTADA)
    $ruta_portada = ''; // Si no sube imagen, se queda vacío
    
    if (isset($_FILES['portada']) && $_FILES['portada']['error'] === 0) {
        $nombre_archivo = time() . "_" . $_FILES['portada']['name']; // Añadimos tiempo para evitar nombres duplicados
        $ruta_destino = "assets/img/portadas/" . $nombre_archivo;
        
        // Mover el archivo de la memoria temporal a nuestra carpeta
        if (move_uploaded_file($_FILES['portada']['tmp_name'], $ruta_destino)) {
            $ruta_portada = $ruta_destino; // Esta es la ruta que guardaremos en la BD
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al subir la imagen.</div>";
        }
    }

    // 4. INSERTAR EN BASE DE DATOS
    if (empty($mensaje)) {
        $sql = "INSERT INTO obras (titulo, autor, generos, sinopsis, portada) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $titulo, $autor, $generos, $sinopsis, $ruta_portada);
        
        if ($stmt->execute()) {
            // Éxito: Redirigir al panel
            header("Location: admin.php?msg=Obra creada correctamente");
            exit();
        } else {
            $mensaje = "<div class='alert alert-danger'>Error en la base de datos: " . $conn->error . "</div>";
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Nueva Obra</h1>
                <a href="admin.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>

            <?php echo $mensaje; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form method="POST" action="" enctype="multipart/form-data">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Título de la Obra</label>
                            <input type="text" name="titulo" class="form-control" required placeholder="Ej: Solo Leveling">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Autor</label>
                            <input type="text" name="autor" class="form-control" required placeholder="Nombre del autor">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Géneros (separados por coma)</label>
                            <input type="text" name="generos" class="form-control" placeholder="Ej: Acción, Fantasía, Magia">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Sinopsis</label>
                            <textarea name="sinopsis" class="form-control" rows="4" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Portada</label>
                            <input type="file" name="portada" class="form-control" accept="image/*" required>
                            <div class="form-text">Formatos: JPG, PNG, WEBP.</div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Guardar Obra</button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>