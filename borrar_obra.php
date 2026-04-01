<?php
session_start();
require 'includes/db.php';

// 1. SEGURIDAD: Solo admin puede borrar
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 2. VALIDAR ID
if (!isset($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$id = intval($_GET['id']);

// 3. RECUPERAR DATOS (Para borrar la imagen de la carpeta)
$sql = "SELECT portada FROM obras WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($obra = $resultado->fetch_assoc()) {
    $ruta_imagen = $obra['portada'];
    
    // Si el archivo existe y no es una imagen de internet (http...), lo borramos
    if (file_exists($ruta_imagen) && strpos($ruta_imagen, 'http') === false) {
        unlink($ruta_imagen);
    }
}

// 4. BORRAR DE LA BASE DE DATOS
// al borrar la obra se borran AUTOMÁTICAMENTE todos sus capítulos.
$sql_delete = "DELETE FROM obras WHERE id = ?";
$stmt_delete = $conn->prepare($sql_delete);
$stmt_delete->bind_param("i", $id);

if ($stmt_delete->execute()) {
    // Éxito
    header("Location: admin.php?msg=Obra eliminada correctamente");
    exit();
} else {
    // Error
    header("Location: admin.php?msg=Error al eliminar la obra");
    exit();
}
?>