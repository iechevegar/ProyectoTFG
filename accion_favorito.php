<?php
session_start();
require 'includes/db.php';

// Si no está logueado, fuera
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Ahora también pedimos el SLUG por seguridad y para la redirección
if (isset($_GET['id']) && isset($_GET['accion']) && isset($_GET['slug'])) {
    $obra_id = intval($_GET['id']);
    $accion = $_GET['accion'];
    $slug = $_GET['slug'];
    
    // --- PROTECCIÓN BACKEND: Si es admin, lo devolvemos sin hacer nada ---
    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        header("Location: /obra/" . $slug);
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
    
    if ($res->num_rows > 0) {
        $userRow = $res->fetch_assoc();
        $usuario_id = $userRow['id'];

        if ($accion === 'poner') {
            $sql = "INSERT IGNORE INTO favoritos (usuario_id, obra_id) VALUES (?, ?)";
            $stmtIns = $conn->prepare($sql);
            $stmtIns->bind_param("ii", $usuario_id, $obra_id);
            $stmtIns->execute();
            
        } elseif ($accion === 'quitar') {
            $sql = "DELETE FROM favoritos WHERE usuario_id = ? AND obra_id = ?";
            $stmtDel = $conn->prepare($sql);
            $stmtDel->bind_param("ii", $usuario_id, $obra_id);
            $stmtDel->execute();
        }
    }

    // Volver a la página de la obra usando la URL amigable
    header("Location: /obra/" . $slug);
    exit();
}

// Si falta algún dato, lo mandamos al inicio por seguridad
header("Location: /");
exit();
?>