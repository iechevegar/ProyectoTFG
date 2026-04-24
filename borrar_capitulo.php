<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. CAPA DE SEGURIDAD Y AUTORIZACIÓN (RBAC)
// =========================================================================================
// Verificamos de forma estricta que la petición provenga de un administrador autenticado.
// Cualquier intento de acceso no autorizado aborta la ejecución y redirige al login.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /login");
    exit();
}

// =========================================================================================
// 2. PREVENCIÓN DE VULNERABILIDADES CSRF (Cross-Site Request Forgery)
// =========================================================================================
// Las operaciones destructivas (DELETE) jamás deben permitirse a través de peticiones GET.
// Forzamos el uso del método POST para evitar que un bot, un crawler o un enlace malicioso 
// camuflado en un correo borre contenido de la base de datos de forma silenciosa.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header("Location: /admin");
    exit();
}

// Casteo estricto a entero (Type Casting) para anular cualquier intento de Inyección SQL.
$capId = intval($_POST['id']);
$obraId = isset($_POST['obra_id']) ? intval($_POST['obra_id']) : 0;


// =========================================================================================
// 3. MANEJO DE I/O: SANEAMIENTO DEL SISTEMA DE ARCHIVOS (FILE SYSTEM)
// =========================================================================================
// Antes de borrar el registro de la base de datos, necesitamos recuperar las rutas de las
// imágenes asociadas. Si solo borramos el registro SQL, los archivos físicos (JPG/PNG) 
// quedarían "huérfanos" en el servidor, consumiendo espacio en disco indefinidamente (Storage Leak).
$sql = "SELECT contenido FROM capitulos WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $capId);
$stmt->execute();
$res = $stmt->get_result();

if ($cap = $res->fetch_assoc()) {
    // Decodificamos el payload JSON que contiene el array de rutas relativas
    $imagenes = json_decode($cap['contenido'], true);
    
    if (is_array($imagenes)) {
        // Iteramos sobre el array de rutas y solicitamos al sistema operativo su eliminación (unlink)
        foreach ($imagenes as $ruta) {
            // Comprobación previa de existencia para evitar warnings/errores I/O de PHP 
            // en caso de que el archivo ya hubiera sido borrado manualmente o no exista.
            if (file_exists($ruta)) {
                unlink($ruta); 
            }
        }
    }
}


// =========================================================================================
// 4. PERSISTENCIA: ELIMINACIÓN EN BD Y REDIRECCIÓN (PATRÓN PRG)
// =========================================================================================
// Una vez el almacenamiento físico está limpio, procedemos a purgar la base de datos.
// Usamos Prepared Statements nuevamente como estándar arquitectónico.
$sqlDelete = "DELETE FROM capitulos WHERE id = ?";
$stmtDelete = $conn->prepare($sqlDelete);
$stmtDelete->bind_param("i", $capId);

if ($stmtDelete->execute()) {
    // Redirección contextual: Si sabemos de qué obra venía el usuario, lo devolvemos a esa
    // lista de capítulos específica. Si no, lo mandamos al panel general.
    if ($obraId > 0) {
        // Enviamos el mensaje de feedback mediante GET codificando los espacios con '+'
        header("Location: /ver_capitulos?id=$obraId&msg=Capítulo+eliminado+y+archivos+limpiados");
    } else {
        header("Location: /admin");
    }
} else {
    // Feedback de bajo nivel en caso de fallo crítico en el motor relacional
    echo "Error crítico en la transacción SQL: " . $conn->error;
}

// Finalizamos explícitamente el hilo de ejecución por buenas prácticas
exit();
?>