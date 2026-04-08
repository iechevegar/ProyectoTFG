<?php
session_start();
require 'includes/db.php';
require 'includes/funciones.php'; // <-- IMPORTAMOS LA FUNCIÓN DE SLUGS

// SEGURIDAD
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /login");
    exit();
}

// Verificar que nos pasan un ID de obra
if (!isset($_GET['id'])) {
    header("Location: /admin");
    exit();
}

$idObra = intval($_GET['id']);
$mensaje = '';

// Obtener título de la obra (solo para mostrarlo y para la carpeta)
$sqlObra = "SELECT titulo, slug, portada FROM obras WHERE id = $idObra";
$resObra = $conn->query($sqlObra);
if ($resObra->num_rows === 0) {
    header("Location: /admin");
    exit();
}
$datosObra = $resObra->fetch_assoc();

// PROCESAR SUBIDA
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo_cap = $conn->real_escape_string(trim($_POST['titulo']));

    // --- MAGIA: CREACIÓN DEL SLUG DEL CAPÍTULO ---
    $slug_cap = limpiarURL($_POST['titulo']);

    // Comprobar que no exista ya otro capítulo con el mismo slug en ESTA misma obra
    $check_slug = $conn->query("SELECT id FROM capitulos WHERE obra_id = $idObra AND slug = '$slug_cap'");
    if ($check_slug && $check_slug->num_rows > 0) {
        $slug_cap = $slug_cap . '-' . rand(100, 999); // Desempate automático
    }
    // ---------------------------------------------

    $rutas_imagenes = [];
    $error_subida = false;

    // --- LÓGICA DE CARPETAS ESTRUCTURADAS ---
    $nombre_obra_carpeta = preg_replace('/[^a-zA-Z0-9]/', '_', trim($datosObra['titulo']));
    $nombre_obra_carpeta = preg_replace('/_+/', '_', $nombre_obra_carpeta);

    $nombre_cap_carpeta = preg_replace('/[^a-zA-Z0-9]/', '_', trim($titulo_cap));
    $nombre_cap_carpeta = preg_replace('/_+/', '_', $nombre_cap_carpeta);

    $carpeta_destino = "assets/img/capitulos/" . $nombre_obra_carpeta . "/" . $nombre_cap_carpeta . "/";

    if (!file_exists($carpeta_destino)) {
        mkdir($carpeta_destino, 0777, true);
    }
    // ----------------------------------------------

    // RECOPILAR Y ORDENAR ARCHIVOS
    if (isset($_FILES['paginas']) && !empty($_FILES['paginas']['name'][0])) {

        $archivos_subidos = [];
        $cantidad = count($_FILES['paginas']['name']);

        for ($i = 0; $i < $cantidad; $i++) {
            if ($_FILES['paginas']['error'][$i] === 0) {
                $archivos_subidos[] = [
                    'name' => $_FILES['paginas']['name'][$i],
                    'tmp_name' => $_FILES['paginas']['tmp_name'][$i],
                    'ext' => pathinfo($_FILES['paginas']['name'][$i], PATHINFO_EXTENSION)
                ];
            }
        }

        // Ordenamiento Natural 
        usort($archivos_subidos, function ($a, $b) {
            return strnatcmp($a['name'], $b['name']);
        });

        // MOVER ARCHIVOS AL SERVIDOR Y GUARDAR RUTAS
        foreach ($archivos_subidos as $index => $archivo) {
            $nombre_limpio = preg_replace("/[^a-zA-Z0-9.]/", "", basename($archivo['name']));
            $nombre_final = str_pad($index, 3, "0", STR_PAD_LEFT) . "_" . $nombre_limpio;
            $ruta_final = $carpeta_destino . $nombre_final;

            if (move_uploaded_file($archivo['tmp_name'], $ruta_final)) {
                $rutas_imagenes[] = $ruta_final;
            } else {
                $error_subida = true;
            }
        }
    }

    // GUARDAR EN BASE DE DATOS
    if (!$error_subida && count($rutas_imagenes) > 0) {
        $contenido_json = json_encode($rutas_imagenes);

        // AÑADIMOS EL SLUG A LA CONSULTA SQL
        $sql = "INSERT INTO capitulos (obra_id, titulo, slug, contenido) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $idObra, $titulo_cap, $slug_cap, $contenido_json);

        if ($stmt->execute()) {
            // URL limpia para ir directamente a verlo
            $url_ver = "/obra/" . $datosObra['slug'] . "/" . $slug_cap;

            // --- INICIO MAGIA DISCORD WEBHOOK ---
            $webhookurl = "https://discord.com/api/webhooks/1491397538534916287/AVIGHBvzEJ3nuqayD3Da0gZFU81bEW0KPIAz3ibJQjRi0qYBV0jbNtKQODiAJhDqWZaG";
            $dominio = "http://ioritz-tfg.gamer.gd";

            // DEFINIMOS LOS ENLACES SEPARADOS
            $url_portada = $dominio . "/" . ltrim($datosObra['portada'], '/');
            $url_obra_general = $dominio . "/obra/" . $datosObra['slug']; // Enlace a la obra
            $url_capitulo_directo = $dominio . $url_ver; // Enlace a leer el cap
            
            $mencion_rol = "<@&1491387526609502268>";
            $msg = [
                "content" => "🏮 **¡Nueva actualización en IoriScans!** " . $mencion_rol,
                "embeds" => [
                    [
                        // Este es el título clickeable (Te lleva a la info de la Obra)
                        "title" => "📚 ¡Ya disponible: " . htmlspecialchars($datosObra['titulo']) . "!",
                        "url" => $url_obra_general,

                        // Descripción arreglada con la variable correcta
                        "description" => "**Detalles del Capítulo:**\n" .
                            "📖 `" . htmlspecialchars($titulo_cap) . "`\n\n" .
                            "👇 ¡No pierdas tiempo!\n" .
                            "[Leer el capítulo ahora](" . $url_capitulo_directo . ")",

                        // Color rojo Refugio
                        "color" => hexdec("E50000"),

                        "thumbnail" => [
                            "url" => $url_portada
                        ],

                        "footer" => [
                            "text" => "IoriScans - Refugio de Lectores",
                            // Pon aquí la ruta real donde tengas guardado tu logo en InfinityFree
                            "icon_url" => $dominio . "/assets/img/logo.png"
                        ]
                    ]
                ]
            ];

            $json_data = json_encode($msg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $script_discord = "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    fetch('{$webhookurl}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({$json_data})
                    }).then(response => console.log('¡Discord notificado con éxito!'))
                      .catch(error => console.error('Error al notificar a Discord:', error));
                });
            </script>
            ";
            // --- FIN MAGIA DISCORD ---

            $mensaje = "<div class='alert alert-success shadow-sm'>
                            <i class='fas fa-check-circle me-2'></i> Capítulo subido correctamente.
                            <a href='$url_ver' class='alert-link ms-2 fw-bold'>Ir al visor</a>
                        </div>" . $script_discord;

        } else {
            $mensaje = "<div class='alert alert-danger shadow-sm'><i class='fas fa-exclamation-triangle me-2'></i> Error al guardar en la base de datos.</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-warning shadow-sm'><i class='fas fa-info-circle me-2'></i> Debes seleccionar al menos una imagen válida o revisar los permisos.</div>";
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="/ver_capitulos?id=<?php echo $idObra; ?>"
                        class="text-decoration-none text-muted mb-1 d-inline-block">
                        <i class="fas fa-arrow-left"></i> Volver a la Obra
                    </a>
                    <h2 class="fw-bold text-dark m-0"><i class="fas fa-upload text-success me-2"></i> Subir Capítulo
                    </h2>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card shadow-sm border-0 border-top border-success border-3 bg-white">
                <div class="card-body p-4">

                    <div
                        class="alert alert-light border border-primary border-start-5 border-end-0 border-top-0 border-bottom-0 mb-4">
                        <p class="mb-0">Obra destino: <strong
                                class="text-primary fs-5"><?php echo htmlspecialchars($datosObra['titulo']); ?></strong>
                        </p>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data">

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Título del Capítulo
                                *</label>
                            <input type="text" name="titulo" class="form-control bg-light"
                                placeholder="Ej: Capítulo 1: El despertar" required>
                        </div>

                        <div class="mb-4 p-3 bg-light rounded border">
                            <label class="form-label fw-bold text-primary small text-uppercase"><i
                                    class="fas fa-images me-1"></i> Páginas del Capítulo (JPG, PNG) *</label>
                            <input type="file" name="paginas[]" class="form-control bg-white" multiple accept="image/*"
                                required>

                            <div class="mt-3 text-muted small">
                                <ul class="mb-0 ps-3">
                                    <li class="mb-1">Puedes seleccionar varias imágenes a la vez manteniendo pulsado
                                        <strong>CTRL</strong> o arrastrando el ratón.
                                    </li>
                                    <li class="mb-1"><strong>Importante:</strong> Nombra tus archivos con números (ej:
                                        <code>01.jpg</code>, <code>02.jpg</code>) para que el sistema las ordene
                                        automáticamente.
                                    </li>
                                    <li>Las imágenes se guardarán de forma organizada en tu servidor.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 border-top pt-4 mt-2">
                            <a href="/ver_capitulos?id=<?php echo $idObra; ?>"
                                class="btn btn-light fw-bold px-4 border">Cancelar</a>
                            <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm">
                                <i class="fas fa-upload me-2"></i> Publicar Capítulo
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    /* RUTA ABSOLUTA PARA LOS ESTILOS SI FALTARAN */
    body {
        background-color: #fbfbfb;
    }
</style>

<?php include 'includes/footer.php'; ?>