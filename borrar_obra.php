<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ANTI-CSRF: Bloquear accesos por URL (GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header("Location: admin.php?msg=Acción no permitida por seguridad.");
    exit();
}

$id = intval($_POST['id']);

$sql = "SELECT portada FROM obras WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($obra = $resultado->fetch_assoc()) {
    $ruta_imagen = $obra['portada'];
    if (file_exists($ruta_imagen) && strpos($ruta_imagen, 'http') === false) {
        unlink($ruta_imagen);
    }
}

$sql_delete = "DELETE FROM obras WHERE id = ?";
$stmt_delete = $conn->prepare($sql_delete);
$stmt_delete->bind_param("i", $id);

if ($stmt_delete->execute()) {
    header("Location: admin.php?msg=Obra eliminada correctamente");
} else {
    header("Location: admin.php?msg=Error al eliminar la obra");
}
exit();
?>