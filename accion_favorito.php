<?php
// Iniciamos la sesión para poder identificar al usuario actual
session_start();
require 'includes/db.php';

// Control de acceso: Si un visitante anónimo intenta ejecutar este script 
// directamente escribiendo la URL, lo expulsamos a la pantalla de login.
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Verificamos que vengan todos los parámetros necesarios por GET.
// Pedimos el 'slug' específicamente para poder hacer la redirección final a la URL amigable
// sin tener que hacer una consulta extra a la base de datos para buscarlo.
if (isset($_GET['id']) && isset($_GET['accion']) && isset($_GET['slug'])) {
    
    $obra_id = intval($_GET['id']);
    $accion = $_GET['accion'];
    $slug = $_GET['slug'];
    
    // Regla de negocio: En nuestra plataforma, las cuentas de Administrador no hacen uso 
    // de la biblioteca personal. Para evitar errores en la BD, si un admin llega a este script, 
    // abortamos la operación y lo devolvemos a la obra.
    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        header("Location: /obra/" . $slug);
        exit();
    }
    
    // Obtenemos el ID numérico del usuario logueado a partir de su nombre de sesión.
    // Usamos sentencias preparadas (Prepared Statements) para evitar cualquier inyección SQL.
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
            // Usamos INSERT IGNORE por seguridad: si el usuario hace doble clic muy rápido
            // o recarga la página, evitamos que MySQL lance un error de clave primaria duplicada.
            $sql = "INSERT IGNORE INTO favoritos (usuario_id, obra_id) VALUES (?, ?)";
            $stmtIns = $conn->prepare($sql);
            $stmtIns->bind_param("ii", $usuario_id, $obra_id);
            $stmtIns->execute();
            
        } elseif ($accion === 'quitar') {
            // Eliminamos la relación específica entre este usuario y la obra
            $sql = "DELETE FROM favoritos WHERE usuario_id = ? AND obra_id = ?";
            $stmtDel = $conn->prepare($sql);
            $stmtDel->bind_param("ii", $usuario_id, $obra_id);
            $stmtDel->execute();
        }
    }

    // Una vez finalizada la lógica, redirigimos al usuario de vuelta a la vista de la obra
    // utilizando nuestro sistema de URLs amigables para no romper la navegación.
    header("Location: /obra/" . $slug);
    exit();
}

// Fallback de seguridad: Si alguien accede al archivo a pelo, sin los parámetros GET, 
// lo redirigimos a la página principal.
header("Location: /");
exit();
?>