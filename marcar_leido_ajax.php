<?php
session_start();
require 'includes/db.php';

// Verificamos que venga por POST, que existan los datos y que haya un usuario logueado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['capId']) && isset($_POST['pagina']) && isset($_SESSION['usuario'])) {
    
    $capId = intval($_POST['capId']);
    $paginaActual = intval($_POST['pagina']);
    $nombreUser = $_SESSION['usuario'];
    
    // 1. Obtenemos el ID del usuario
    $resUser = $conn->query("SELECT id FROM usuarios WHERE nombre = '$nombreUser'");
    
    if ($resUser && $resUser->num_rows > 0) {
        $userId = $resUser->fetch_assoc()['id'];
        
        // 2. Comprobamos si ya existe un registro de lectura para este usuario y capítulo
        $sqlCheck = "SELECT id, ultima_pagina FROM capitulos_leidos WHERE usuario_id = ? AND capitulo_id = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param("ii", $userId, $capId);
        $stmtCheck->execute();
        $resultado = $stmtCheck->get_result();
        
        if ($resultado->num_rows > 0) {
            // EL CAPÍTULO YA ESTABA EMPEZADO: Lo actualizamos
            $row = $resultado->fetch_assoc();
            $paginaGuardada = intval($row['ultima_pagina']);
            
            // TRUCO TFG: Solo actualizamos si la página actual es mayor que la que ya tenía guardada
            // (Para que si vuelve a leer páginas anteriores no pierda su progreso máximo)
            if ($paginaActual > $paginaGuardada) {
                $sqlUpdate = "UPDATE capitulos_leidos SET ultima_pagina = ? WHERE usuario_id = ? AND capitulo_id = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bind_param("iii", $paginaActual, $userId, $capId);
                $stmtUpdate->execute();
                echo "Progreso actualizado a la página $paginaActual.";
            } else {
                echo "El usuario ya había llegado más lejos (Página $paginaGuardada). No se actualiza.";
            }
            
        } else {
            // ES LA PRIMERA VEZ QUE LEE EL CAPÍTULO: Lo insertamos nuevo
            $sqlInsert = "INSERT INTO capitulos_leidos (usuario_id, capitulo_id, ultima_pagina) VALUES (?, ?, ?)";
            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->bind_param("iii", $userId, $capId, $paginaActual);
            
            if ($stmtInsert->execute()) {
                echo "Nuevo capítulo empezado y guardado en la página $paginaActual.";
            } else {
                echo "Error al insertar el progreso.";
            }
        }
    } else {
        echo "Usuario no encontrado en la base de datos.";
    }
} else {
    echo "Petición inválida o usuario no logueado.";
}
?>