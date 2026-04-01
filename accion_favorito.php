<?php
session_start();
require 'includes/db.php';

// Si no está logueado, fuera
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['accion'])) {
    $obra_id = intval($_GET['id']);
    $accion = $_GET['accion'];
    
    // --- PROTECCIÓN BACKEND: Si es admin, lo devolvemos sin hacer nada ---
    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        header("Location: detalle.php?id=" . $obra_id);
        exit();
    }
    // --------------------------------------------------------------------
    
    // Obtener ID del usuario
    $nombreUser = $_SESSION['usuario'];
    $sqlUser = "SELECT id FROM usuarios WHERE nombre = ?";
    $stmt = $conn->prepare($sqlUser);
    $stmt->bind_param("s", $nombreUser);
    $stmt->execute();
    $res = $stmt->get_result();
    $userRow = $res->fetch_assoc();
    $usuario_id = $userRow['id'];

    if ($accion === 'poner') {
        // Insertar (Ignora error si ya existe gracias a UNIQUE KEY)
        // Nota: Le he añadido 'IGNORE' para que no salte error fatal en PHP si se recarga la página
        $sql = "INSERT IGNORE INTO favoritos (usuario_id, obra_id) VALUES (?, ?)";
        $stmtIns = $conn->prepare($sql);
        $stmtIns->bind_param("ii", $usuario_id, $obra_id);
        $stmtIns->execute();
        
    } elseif ($accion === 'quitar') {
        // Borrar
        $sql = "DELETE FROM favoritos WHERE usuario_id = ? AND obra_id = ?";
        $stmtDel = $conn->prepare($sql);
        $stmtDel->bind_param("ii", $usuario_id, $obra_id);
        $stmtDel->execute();
    }
}

// Volver a la página de la obra
header("Location: detalle.php?id=" . $obra_id);
exit();
?>