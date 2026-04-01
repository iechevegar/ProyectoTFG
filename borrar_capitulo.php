<?php
session_start();
require 'includes/db.php';

// SEGURIDAD
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$capId = intval($_GET['id']);
$obraId = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : 0; // Para volver a la lista correcta

// 1. OBTENER LAS RUTAS DE LAS IMÁGENES ANTES DE BORRAR
$sql = "SELECT contenido FROM capitulos WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $capId);
$stmt->execute();
$res = $stmt->get_result();

if ($cap = $res->fetch_assoc()) {
    // Decodificar JSON
    $imagenes = json_decode($cap['contenido'], true);
    
    // Si hay imágenes, las borramos del disco una a una
    if (is_array($imagenes)) {
        foreach ($imagenes as $ruta) {
            // $ruta es algo como "assets/img/capitulos/171500_nombre.jpg"
            if (file_exists($ruta)) {
                unlink($ruta); // Borra el archivo físico
            }
        }
    }
}

// 2. BORRAR DE LA BASE DE DATOS
$sqlDelete = "DELETE FROM capitulos WHERE id = ?";
$stmtDelete = $conn->prepare($sqlDelete);
$stmtDelete->bind_param("i", $capId);

if ($stmtDelete->execute()) {
    // Redirigir de vuelta a la lista de esa obra
    if ($obraId > 0) {
        header("Location: ver_capitulos.php?id=$obraId&msg=Capítulo eliminado y archivos limpiados");
    } else {
        header("Location: admin.php");
    }
} else {
    echo "Error al eliminar: " . $conn->error;
}
?>