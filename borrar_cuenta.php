<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. CAPA DE SEGURIDAD: VERIFICACIÓN DE SESIÓN ACTIVA
// =========================================================================================
// Evitamos la ejecución en frío. Si un visitante no autenticado intenta acceder
// a este endpoint, cortamos el hilo y lo redirigimos al portal de acceso.
if (!isset($_SESSION['usuario'])) {
    header("Location: /login");
    exit();
}

// =========================================================================================
// 2. PREVENCIÓN CSRF Y RESTRICCIÓN DE VERBO HTTP
// =========================================================================================
// Aplicamos una política estricta de verbos HTTP. Las acciones destructivas (Eliminar Cuenta)
// DEBEN realizarse mediante POST. Si se intenta forzar vía GET (ej: escribiendo la URL en 
// el navegador o mediante un ataque de Cross-Site Request Forgery con una etiqueta <img>), 
// la petición es interceptada y rechazada.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirigimos al perfil inyectando el error por GET para su renderizado en la vista
    header("Location: /perfil?error=" . urlencode("Acción no permitida por políticas de seguridad."));
    exit();
}


// =========================================================================================
// 3. EXTRACCIÓN DE IDENTIDAD Y METADATOS DE ARCHIVOS
// =========================================================================================
// Antes de destruir el registro en la BD, necesitamos recuperar la ruta del avatar del usuario.
// Usamos Prepared Statements para prevenir Inyección SQL durante la validación de la identidad.
$nombreUser = $_SESSION['usuario'];
$sql = "SELECT id, foto FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nombreUser);
$stmt->execute();
$res = $stmt->get_result();

if ($user = $res->fetch_assoc()) {
    $idUsuario = $user['id'];
    $rutaFoto = $user['foto'];

    // =========================================================================================
    // 4. MANTENIMIENTO DEL FILE SYSTEM (PREVENCIÓN DE STORAGE LEAKS)
    // =========================================================================================
    // Si el usuario subió una imagen personalizada, debemos eliminarla físicamente del disco.
    // Si omitimos este paso, el servidor terminaría acumulando "archivos huérfanos".
    if (!empty($rutaFoto) && file_exists($rutaFoto)) {
        // Validamos que la ruta sea local y no una URL externa (como los avatares generados por API)
        // para evitar que la función unlink() arroje excepciones de I/O.
        if (strpos($rutaFoto, 'http') === false) {
            unlink($rutaFoto);
        }
    }

    // =========================================================================================
    // 5. PURGA EN LA BASE DE DATOS (INTEGRIDAD REFERENCIAL)
    // =========================================================================================
    // Ejecutamos el borrado del usuario. 
    // NOTA ARQUITECTÓNICA: Delegamos la eliminación de las dependencias (favoritos, comentarios, 
    // temas de foro, capítulos leídos) al motor de la base de datos MySQL mediante la restricción 
    // ON DELETE CASCADE definida en las Foreign Keys. Esto asegura transacciones atómicas limpias.
    $sqlDelete = "DELETE FROM usuarios WHERE id = ?";
    $stmtDelete = $conn->prepare($sqlDelete);
    $stmtDelete->bind_param("i", $idUsuario);

    if ($stmtDelete->execute()) {
        
        // =========================================================================================
        // 6. GESTIÓN DEL CICLO DE VIDA DE LA SESIÓN (FLASH MESSAGING)
        // =========================================================================================
        // 1. Destruimos completamente la sesión actual para borrar rastros de autenticación.
        session_destroy();
        
        // 2. Iniciamos una nueva sesión "limpia" de forma temporal.
        // Esto obedece al patrón Flash Message: necesitamos una forma de pasar un mensaje de éxito 
        // a la página de login que sobreviva a la redirección HTTP, pero sin mantener al usuario logueado.
        session_start();
        $_SESSION['msg_exito'] = "Cuenta eliminada correctamente. ¡Te echaremos de menos!";
        
        header("Location: /login");
        exit();
    } else {
        // Fallback en caso de que la restricción relacional o el motor SQL fallen
        header("Location: /perfil?error=" . urlencode("Error interno: No se pudo purgar la cuenta."));
        exit();
    }
} else {
    // Si el usuario de la sesión no se encuentra en la BD (ej. cuenta ya borrada por admin concurrentemente)
    header("Location: /");
    exit();
}
?>