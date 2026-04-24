<?php
// Iniciamos sesión para identificar qué lector está interactuando con el contenido
session_start();
require 'includes/db.php';

// --- VALIDACIÓN DE ENTRADA (Seguridad pasiva) ---
// Comprobamos que el usuario esté logueado y que recibamos el ID del capítulo y el SLUG de la obra.
// El slug es vital para poder devolver al usuario a la URL amigable correcta al finalizar.
if (!isset($_SESSION['usuario']) || !isset($_GET['capId']) || !isset($_GET['slug'])) {
    header("Location: /"); 
    exit();
}

$capId = intval($_GET['capId']);
$slug = $_GET['slug']; // Recogemos el slug para la redirección semántica
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

// --- LÓGICA DE NEGOCIO: EXCLUSIÓN DE ADMINISTRADORES ---
// Evitamos que las cuentas de admin generen registros de lectura. 
// Esto mantiene las estadísticas de la base de datos limpias de pruebas técnicas.
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    header("Location: /obra/" . $slug);
    exit();
}

// Obtenemos el ID del usuario de forma segura mediante Prepared Statements.
// Nunca confiamos ciegamente en los datos de la sesión para consultas directas.
$nombreUser = $_SESSION['usuario'];
$stmtUser = $conn->prepare("SELECT id FROM usuarios WHERE nombre = ?");
$stmtUser->bind_param("s", $nombreUser);
$stmtUser->execute();
$resUser = $stmtUser->get_result();

if ($resUser->num_rows > 0) {
    $userId = $resUser->fetch_assoc()['id'];

    // --- PROCESAMIENTO DE ESTADO (Marcado/Desmarcado) ---
    if ($accion === 'marcar') {
        // Usamos INSERT IGNORE para prevenir errores de clave duplicada si el usuario 
        // recarga la página o el cliente manda la petición dos veces por error.
        $stmt = $conn->prepare("INSERT IGNORE INTO capitulos_leidos (usuario_id, capitulo_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $capId);
        $stmt->execute();
        
    } elseif ($accion === 'desmarcar') {
        // Eliminamos el registro de la tabla pivote para reflejar que el capítulo ya no es 'leído'
        $stmt = $conn->prepare("DELETE FROM capitulos_leidos WHERE usuario_id = ? AND capitulo_id = ?");
        $stmt->bind_param("ii", $userId, $capId);
        $stmt->execute();
    }
}

// --- REDIRECCIÓN SEMÁNTICA (SEO Friendly) ---
// En lugar de redirigir a un archivo físico .php, devolvemos al usuario a la ruta amigable
// definida en nuestro .htaccess para mantener la coherencia visual de la plataforma.
header("Location: /obra/" . $slug);
exit();
?>