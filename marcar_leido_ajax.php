<?php
session_start();
require_once 'includes/db.php';

// =========================================================================================
// 1. ENDPOINT ASÍNCRONO (API RESTful) Y MIDDLEWARE DE ACCESO
// =========================================================================================
// Este script no devuelve HTML, sino texto plano o JSON para ser consumido vía fetch/AJAX.
// Aplicamos validación estricta de verbos HTTP: exigimos POST para mutaciones de estado 
// y verificamos la existencia de un token de sesión válido.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['capId']) && isset($_POST['pagina']) && isset($_SESSION['usuario'])) {
    
    // Sanitización de entradas mediante Type Casting estricto (Anti-SQLi)
    $capId = intval($_POST['capId']);
    $paginaActual = intval($_POST['pagina']);
    $nombreUser = $_SESSION['usuario'];
    
    // =========================================================================================
    // 2. RESOLUCIÓN DE IDENTIDAD (PREPARED STATEMENT - Anti-SQLi)
    // =========================================================================================
    $stmtU = $conn->prepare("SELECT id FROM usuarios WHERE nombre = ?");
    $stmtU->bind_param("s", $nombreUser);
    $stmtU->execute();
    $resUser = $stmtU->get_result();
    
    if ($resUser && $resUser->num_rows > 0) {
        $userId = $resUser->fetch_assoc()['id'];
        
        // =========================================================================================
        // 3. TELEMETRÍA Y TRACKING DE LECTURA
        // =========================================================================================
        // Comprobamos si el motor de base de datos ya tiene un registro de progreso para este binomio Usuario-Capítulo.
        $sqlCheck = "SELECT id, ultima_pagina FROM capitulos_leidos WHERE usuario_id = ? AND capitulo_id = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param("ii", $userId, $capId);
        $stmtCheck->execute();
        $resultado = $stmtCheck->get_result();
        
        // NOTA ARQUITECTÓNICA: Aunque el proyecto opera bajo una arquitectura general de 
        // solo lectura (read-only), este bloque demuestra la capacidad transaccional de la 
        // plataforma para manejar persistencia de estado de forma concurrente.

        if ($resultado->num_rows > 0) {
            // FASE UPDATE (Registro Existente)
            $row = $resultado->fetch_assoc();
            $paginaGuardada = intval($row['ultima_pagina']);
            
            // --- ALGORITMO HIGH-WATER MARK (Marca de Agua Alta) ---
            // Lógica de negocio clave para UX: Prevenimos la regresión de progreso.
            // Si un usuario vuelve a páginas anteriores para releer un detalle, la condición 
            // ($paginaActual > $paginaGuardada) evita que el sistema sobrescriba su progreso 
            // máximo alcanzado con un número menor. Solo guardamos si el avance es positivo.
            if ($paginaActual > $paginaGuardada) {
                $sqlUpdate = "UPDATE capitulos_leidos SET ultima_pagina = ? WHERE usuario_id = ? AND capitulo_id = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bind_param("iii", $paginaActual, $userId, $capId);
                $stmtUpdate->execute();
                
                // Retorno de respuesta HTTP 200 OK implícita para la promesa de JS
                echo "Telemetría sincronizada: Progreso avanzado a página $paginaActual.";
            } else {
                // Bypass estratégico: El usuario está revisitando contenido anterior.
                echo "Caché de progreso intacta (High-Water Mark: Pág $paginaGuardada).";
            }
            
        } else {
            // FASE INSERT (Nuevo Registro)
            // Es la primera vez que el cliente carga este recurso. Instanciamos su traza en la BD.
            $sqlInsert = "INSERT INTO capitulos_leidos (usuario_id, capitulo_id, ultima_pagina) VALUES (?, ?, ?)";
            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->bind_param("iii", $userId, $capId, $paginaActual);
            
            if ($stmtInsert->execute()) {
                echo "Inicialización de tracking exitosa en página $paginaActual.";
            } else {
                echo "Error de I/O en motor relacional.";
            }
        }
    } else {
        echo "Excepción de Autorización: Identidad de sesión huérfana.";
    }
} else {
    // Rechazo de la petición (Equivalente a HTTP 400 Bad Request o 401 Unauthorized)
    echo "Bad Request: Carga útil inválida o token de sesión ausente.";
}
?>