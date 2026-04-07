<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ANTI-CSRF: Bloquear accesos por URL (GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header("Location: admin.php");
    exit();
}

$capId = intval($_POST['id']);
$obraId = isset($_POST['obra_id']) ? intval($_POST['obra_id']) : 0;

$sql = "SELECT contenido FROM capitulos WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $capId);
$stmt->execute();
$res = $stmt->get_result();

if ($cap = $res->fetch_assoc()) {
    $imagenes = json_decode($cap['contenido'], true);
    if (is_array($imagenes)) {
        foreach ($imagenes as $ruta) {
            if (file_exists($ruta)) {
                unlink($ruta); 
            }
        }
    }
}

$sqlDelete = "DELETE FROM capitulos WHERE id = ?";
$stmtDelete = $conn->prepare($sqlDelete);
$stmtDelete->bind_param("i", $capId);

if ($stmtDelete->execute()) {
    if ($obraId > 0) {
        header("Location: ver_capitulos.php?id=$obraId&msg=Capítulo eliminado y archivos limpiados");
    } else {
        header("Location: admin.php");
    }
} else {
    echo "Error al eliminar: " . $conn->error;
}
exit();
?>