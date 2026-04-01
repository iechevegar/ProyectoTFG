<?php
session_start();
require 'includes/db.php';

// Si falta algo en la URL o no hay sesión, volvemos
if (!isset($_SESSION['usuario']) || !isset($_GET['capId']) || !isset($_GET['obraId'])) {
    header("Location: index.php");
    exit();
}

$capId = intval($_GET['capId']);
$obraId = intval($_GET['obraId']);
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';

// --- PROTECCIÓN BACKEND: Si es admin, no hacemos nada en la base de datos ---
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    header("Location: detalle.php?id=$obraId");
    exit();
}
// ----------------------------------------------------------------------------

// Obtener ID del usuario de forma segura
$nombreUser = $_SESSION['usuario'];
$stmtUser = $conn->prepare("SELECT id FROM usuarios WHERE nombre = ?");
$stmtUser->bind_param("s", $nombreUser);
$stmtUser->execute();
$resUser = $stmtUser->get_result();

if ($resUser->num_rows > 0) {
    $userId = $resUser->fetch_assoc()['id'];

    // Ejecutar acción
    if ($accion === 'marcar') {
        $stmt = $conn->prepare("INSERT IGNORE INTO capitulos_leidos (usuario_id, capitulo_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $capId);
        $stmt->execute();
    } elseif ($accion === 'desmarcar') {
        $stmt = $conn->prepare("DELETE FROM capitulos_leidos WHERE usuario_id = ? AND capitulo_id = ?");
        $stmt->bind_param("ii", $userId, $capId);
        $stmt->execute();
    }
}

// Volver a la página de la obra
header("Location: detalle.php?id=$obraId");
exit();
?>