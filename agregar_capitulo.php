<?php
session_start();
require 'includes/db.php';
require 'includes/funciones.php'; // Importamos la librería de utilidades compartidas (generación de slugs, etc.)

// =========================================================================================
// 1. CONTROL DE ACCESO (RBAC) Y VALIDACIÓN DE PARÁMETROS
// =========================================================================================
// Restringimos la ejecución exclusivamente a administradores logueados.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /login");
    exit();
}

// Verificamos la integridad de la petición: Es imperativo recibir el ID de la obra padre.
if (!isset($_GET['id'])) {
    header("Location: /admin");
    exit();
}

$idObra = intval($_GET['id']);
$mensaje = '';

// Extraemos los metadatos de la obra padre para construir posteriormente
// las rutas relativas del sistema de archivos y los embeds de Discord.
$sqlObra = "SELECT titulo, slug, portada FROM obras WHERE id = $idObra";
$resObra = $conn->query($sqlObra);
if ($resObra->num_rows === 0) {
    header("Location: /admin");
    exit();
}
$datosObra = $resObra->fetch_assoc();


// =========================================================================================
// 2. PROCESAMIENTO DEL FORMULARIO MULTIPART (SUBIDA DE ARCHIVOS)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo_cap = $conn->real_escape_string(trim($_POST['titulo']));

    // --- ALGORITMO DE SLUGS Y PREVENCIÓN DE COLISIONES ---
    // Generamos el slug a partir del título para las URLs amigables.
    $slug_cap = limpiarURL($_POST['titulo']);

    // Comprobamos si ya existe un capítulo con el mismo slug para esta misma obra.
    // Si existe una colisión, introducimos entropía (un sufijo numérico aleatorio) para evitar el error de base de datos.
    $check_slug = $conn->query("SELECT id FROM capitulos WHERE obra_id = $idObra AND slug = '$slug_cap'");
    if ($check_slug && $check_slug->num_rows > 0) {
        $slug_cap = $slug_cap . '-' . rand(100, 999); 
    }

    $rutas_imagenes = [];
    $error_subida = false;

    // --- ARQUITECTURA DEL SISTEMA DE ARCHIVOS (FILE SYSTEM) ---
    // Sanitizamos agresivamente los nombres de la obra y el capítulo para usarlos como nombres de directorio.
    // Esto previene errores de codificación en servidores Linux/Unix provocados por espacios o caracteres especiales.
    $nombre_obra_carpeta = preg_replace('/[^a-zA-Z0-9]/', '_', trim($datosObra['titulo']));
    $nombre_obra_carpeta = preg_replace('/_+/', '_', $nombre_obra_carpeta);

    $nombre_cap_carpeta = preg_replace('/[^a-zA-Z0-9]/', '_', trim($titulo_cap));
    $nombre_cap_carpeta = preg_replace('/_+/', '_', $nombre_cap_carpeta);

    $carpeta_destino = "assets/img/capitulos/" . $nombre_obra_carpeta . "/" . $nombre_cap_carpeta . "/";

    // Instanciamos el árbol de directorios de forma recursiva si no existe
    if (!file_exists($carpeta_destino)) {
        mkdir($carpeta_destino, 0777, true);
    }

    // --- PROCESAMIENTO Y ORDENAMIENTO NATURAL (NATURAL SORT) ---
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

        // CRÍTICO: Aplicamos un Algoritmo de Ordenamiento Natural.
        // Esto asegura que "pagina_2.jpg" se procese ANTES que "pagina_10.jpg", 
        // corrigiendo el comportamiento por defecto de los sistemas operativos que ordenan lexicográficamente.
        usort($archivos_subidos, function ($a, $b) {
            return strnatcmp($a['name'], $b['name']);
        });

        // --- I/O DE ARCHIVOS Y ZERO-PADDING ---
        foreach ($archivos_subidos as $index => $archivo) {
            $nombre_limpio = preg_replace("/[^a-zA-Z0-9.]/", "", basename($archivo['name']));
            // Aplicamos Zero-Padding (ej: 001_img.jpg, 002_img.jpg) para forzar un orden inmutable en el disco
            $nombre_final = str_pad($index, 3, "0", STR_PAD_LEFT) . "_" . $nombre_limpio;
            $ruta_final = $carpeta_destino . $nombre_final;

            if (move_uploaded_file($archivo['tmp_name'], $ruta_final)) {
                $rutas_imagenes[] = $ruta_final; // Registramos la ruta exitosa
            } else {
                $error_subida = true;
            }
        }
    }


    // =========================================================================================
    // 3. PERSISTENCIA DE DATOS E INTEGRACIÓN CON API DE DISCORD
    // =========================================================================================
    if (!$error_subida && count($rutas_imagenes) > 0) {
        // Serializamos el array de rutas como un objeto JSON.
        // Esto reduce la complejidad relacional de la base de datos, evitando crear una tabla auxiliar solo para imágenes.
        $contenido_json = json_encode($rutas_imagenes);

        $sql = "INSERT INTO capitulos (obra_id, titulo, slug, contenido) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $idObra, $titulo_cap, $slug_cap, $contenido_json);

        if ($stmt->execute()) {
            $url_ver = "/obra/" . $datosObra['slug'] . "/" . $slug_cap;

            // --- INTEGRACIÓN DE WEBHOOKS (EVENT-DRIVEN NOTIFICATIONS) ---
            // Construimos un payload (Embed Rich-Text) para notificar automáticamente a la comunidad de Discord
            // sobre la nueva publicación. Esto fomenta el engagement y el retorno de usuarios (Retention).
            $webhookurl = "https://discord.com/api/webhooks/1491397538534916287/AVIGHBvzEJ3nuqayD3Da0gZFU81bEW0KPIAz3ibJQjRi0qYBV0jbNtKQODiAJhDqWZaG";
            $dominio = "http://ioritz-tfg.gamer.gd";

            $url_portada = $dominio . "/" . ltrim($datosObra['portada'], '/');
            $url_obra_general = $dominio . "/obra/" . $datosObra['slug'];
            $url_capitulo_directo = $dominio . $url_ver;

            $mencion_rol = "<@&1491387526609502268>";
            $msg = [
                "content" => "🏮 **¡Nueva actualización en IoriScans!** " . $mencion_rol,
                "embeds" => [
                    [
                        "title" => "📚 ¡Ya disponible: " . htmlspecialchars($datosObra['titulo']) . "!",
                        "url" => $url_obra_general,
                        "description" => "**Detalles del Capítulo:**\n" .
                            "📖 `" . htmlspecialchars($titulo_cap) . "`\n\n" .
                            "👇 ¡No pierdas tiempo!\n" .
                            "[Leer el capítulo ahora](" . $url_capitulo_directo . ")",
                        "color" => hexdec("E50000"),
                        "thumbnail" => [
                            "url" => $url_portada
                        ],
                        "footer" => [
                            "text" => "IoriScans - Refugio de Lectores",
                            "icon_url" => $dominio . "/assets/img/logo.png"
                        ]
                    ]
                ]
            ];

            // Codificamos el payload previniendo que los slashes en las URLs se escapen (JSON_UNESCAPED_SLASHES)
            $json_data = json_encode($msg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Inyectamos un bloque de código JS para realizar el POST al webhook de Discord desde el cliente.
            // Esto libera al servidor PHP de la carga de red por peticiones externas (Non-blocking I/O approach).
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

            $mensaje = "<div class='alert alert-success shadow-sm border-success'>
                            <i class='fas fa-check-circle me-2'></i> ¡Capítulo subido y publicado correctamente!
                            <a href='$url_ver' class='alert-link ms-2 fw-bold'>Ver en el lector</a>
                        </div>" . $script_discord;

        } else {
            $mensaje = "<div class='alert alert-danger shadow-sm'><i class='fas fa-exclamation-triangle me-2'></i> Error al persistir el registro en la base de datos.</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-warning shadow-sm'><i class='fas fa-info-circle me-2'></i> Debes seleccionar al menos un archivo válido o revisar los permisos CHMOD de escritura del directorio destino.</div>";
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="container py-4 admin-main-container">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="/ver_capitulos?id=<?php echo $idObra; ?>"
                        class="text-decoration-none text-muted mb-1 d-inline-block transition-colors hover-iori">
                        <i class="fas fa-arrow-left"></i> Volver a la Obra
                    </a>
                    <h2 class="fw-bold text-dark m-0"><i class="fas fa-upload text-success me-2"></i> Subir Capítulo
                    </h2>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <div class="card shadow-sm border-0 border-top border-success border-3 bg-white rounded-4">
                <div class="card-body p-4">

                    <div
                        class="d-flex align-items-center p-3 mb-4 bg-light rounded border border-light border-start border-start-5 border-success shadow-sm">
                        <?php $imgActual = (strpos($datosObra['portada'], 'http') === 0) ? $datosObra['portada'] : '/' . ltrim($datosObra['portada'], '/'); ?>
                        <img src="<?php echo htmlspecialchars($imgActual); ?>" class="rounded me-3 thumb-obra-subida">
                        <div>
                            <p class="text-muted mb-0 small text-uppercase fw-bold">Añadiendo contenido a:</p>
                            <h5 class="fw-bold text-primary mb-0"><?php echo htmlspecialchars($datosObra['titulo']); ?>
                            </h5>
                        </div>
                    </div>

                    <form id="formSubida" method="POST" action="" enctype="multipart/form-data">

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Título del Capítulo
                                *</label>
                            <input type="text" name="titulo" class="form-control bg-light border-light shadow-sm fs-5"
                                placeholder="Ej: Capítulo 1: El despertar" required>
                        </div>

                        <div class="mb-4 p-4 bg-light rounded border border-light shadow-sm position-relative" id="dropZone">
                            <label class="form-label fw-bold text-primary small text-uppercase d-block mb-3">
                                <i class="fas fa-images me-1"></i> Páginas del Capítulo (Ordenadas) *
                            </label>

                            <input type="file" name="paginas[]" id="fileInput" class="form-control bg-white border-secondary shadow-sm"
                                multiple accept="image/*" required>

                            <div class="mt-3 text-muted small">
                                <ul class="mb-0 ps-3">
                                    <li class="mb-1">Selecciona varias imágenes a la vez manteniendo pulsado
                                        <strong>CTRL</strong>.</li>
                                    <li class="mb-1">El sistema aplicará un algoritmo de ordenamiento natural según su nombre (ej:
                                        <code>01.jpg</code>, <code>02.jpg</code>).</li>
                                </ul>
                            </div>

                            <div id="previewContainer" class="mt-4 d-none">
                                <h6 class="fw-bold text-dark border-bottom border-secondary pb-2 mb-3">Previsualización del Batch (<span
                                        id="fileCount">0</span> archivos detectados)</h6>
                                <div id="imageGrid" class="d-flex flex-wrap gap-2 preview-grid-container border-secondary bg-white">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 border-top pt-4 mt-2">
                            <a href="/ver_capitulos?id=<?php echo $idObra; ?>"
                                class="btn btn-soft-secondary fw-bold px-4 rounded-pill transition-colors">Cancelar</a>
                            
                            <button type="submit" id="btnSubmit"
                                class="btn btn-success fw-bold px-4 shadow-sm rounded-pill position-relative">
                                <span id="btnText"><i class="fas fa-upload me-2"></i> Publicar Capítulo</span>
                                <span id="btnLoader" class="d-none"><i class="fas fa-spinner fa-spin me-2"></i>
                                    Subiendo y comprimiendo...</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    // =========================================================================================
    // LÓGICA DE UI/UX: PREVISUALIZACIÓN CLIENT-SIDE (API FileReader)
    // =========================================================================================
    document.addEventListener('DOMContentLoaded', function () {
        const fileInput = document.getElementById('fileInput');
        const previewContainer = document.getElementById('previewContainer');
        const imageGrid = document.getElementById('imageGrid');
        const fileCountSpan = document.getElementById('fileCount');
        const form = document.getElementById('formSubida');
        const btnSubmit = document.getElementById('btnSubmit');
        const btnText = document.getElementById('btnText');
        const btnLoader = document.getElementById('btnLoader');

        fileInput.addEventListener('change', function (e) {
            imageGrid.innerHTML = ''; // Purgamos la cuadrícula (DOM Refresh)
            const files = Array.from(e.target.files);

            if (files.length > 0) {
                previewContainer.classList.remove('d-none');
                fileCountSpan.textContent = files.length;

                // Replicamos el algoritmo de ordenamiento natural (strnatcmp de PHP) en Javascript (Intl.Collator)
                // para que el administrador vea en pantalla exactamente el mismo orden que se guardará en disco.
                const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
                files.sort((a, b) => collator.compare(a.name, b.name));

                // OPTIMIZACIÓN: Renderizamos en base64 solo las primeras 30 imágenes. 
                // Esto previene que el navegador (especialmente móviles) crashee por desbordamiento de memoria (Memory Leak)
                // al intentar leer cientos de imágenes de alta resolución de golpe.
                const maxPreview = Math.min(files.length, 30);

                for (let i = 0; i < maxPreview; i++) {
                    const file = files[i];
                    if (!file.type.match('image.*')) continue;

                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'preview-img-wrapper border-secondary';

                        wrapper.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <div class="preview-img-badge">Pág. ${i + 1}</div>
                        `;
                        imageGrid.appendChild(wrapper);
                    }
                    reader.readAsDataURL(file);
                }

                // Generación visual del "resto" de imágenes truncadas
                if (files.length > 30) {
                    const extras = document.createElement('div');
                    extras.className = 'd-flex align-items-center justify-content-center fw-bold text-muted bg-light border border-secondary preview-extras';
                    extras.innerHTML = `+${files.length - 30}`;
                    imageGrid.appendChild(extras);
                }
            } else {
                previewContainer.classList.add('d-none');
            }
        });

        // Prevención estructural de dobles submits.
        // Mutamos el estado visual del botón al realizar el POST para informar al usuario de que hay actividad en segundo plano.
        form.addEventListener('submit', function () {
            btnText.classList.add('d-none');
            btnLoader.classList.remove('d-none');
            btnSubmit.classList.add('disabled');
            // Nota arquitectónica: No aplicamos event.preventDefault(). Dejamos que el browser ejecute el multipart request nativo.
        });
    });
</script>

<?php include 'includes/footer.php'; ?>