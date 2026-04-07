<?php
session_start();
require 'includes/db.php';

// Si no está logueado, fuera
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// --- SEGURIDAD CRÍTICA (ANTI-CSRF) ---
// Comprobamos que la orden viene obligatoriamente de hacer clic en el botón POST del formulario.
// Si alguien intenta entrar por URL (GET), la petición se bloquea.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: perfil.php?error=Acción no permitida por seguridad.");
    exit();
}
// -------------------------------------

// Obtener datos del usuario para borrar su foto primero
$nombreUser = $_SESSION['usuario'];
$sql = "SELECT id, foto FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nombreUser);
$stmt->execute();
$res = $stmt->get_result();

if ($user = $res->fetch_assoc()) {
    $idUsuario = $user['id'];
    $rutaFoto = $user['foto'];

    // 1. BORRAR FOTO DEL SERVIDOR (Si tiene una y existe)
    if (!empty($rutaFoto) && file_exists($rutaFoto)) {
        // Verificamos que no sea una imagen de internet
        if (strpos($rutaFoto, 'http') === false) {
            unlink($rutaFoto);
        }
    }

    // 2. BORRAR USUARIO DE LA BASE DE DATOS
    // Al borrar el usuario, MySQL borrará automáticamente sus favoritos (ON DELETE CASCADE)
    $sqlDelete = "DELETE FROM usuarios WHERE id = ?";
    $stmtDelete = $conn->prepare($sqlDelete);
    $stmtDelete->bind_param("i", $idUsuario);

    if ($stmtDelete->execute()) {
        // 3. DESTRUIR SESIÓN
        session_destroy();
        
        // Iniciamos una sesión temporal solo para pasar el mensaje de éxito al login
        session_start();
        $_SESSION['msg_exito'] = "Cuenta eliminada correctamente. ¡Te echaremos de menos!";
        
        header("Location: login.php");
        exit();
    } else {
        // Si falla algo
        header("Location: perfil.php?error=No se pudo eliminar la cuenta.");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>