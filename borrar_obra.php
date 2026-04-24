<?php
session_start();
require 'includes/db.php';

// =========================================================================================
// 1. MIDDLEWARE DE SEGURIDAD Y AUTORIZACIÓN (RBAC)
// =========================================================================================
// Restringimos el acceso al script destructivo. Solo las cuentas con privilegios 
// administrativos pueden ejecutar la purga de obras del catálogo.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /login");
    exit();
}

// =========================================================================================
// 2. PROTECCIÓN CSRF Y RESTRICCIÓN DE VERBO HTTP
// =========================================================================================
// Aplicamos una política estricta de métodos HTTP. Obligamos a que la petición de borrado
// sea un POST originado de forma deliberada desde nuestros formularios. Si interceptamos 
// un GET (ej. un administrador haciendo clic en un enlace malicioso incrustado en un email), 
// bloqueamos la transacción para prevenir vulnerabilidades de Cross-Site Request Forgery.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header("Location: /admin?msg=" . urlencode("Acción no permitida por políticas de seguridad."));
    exit();
}

// Sanitización de tipo (Type Casting) para evitar ataques de Inyección SQL
$id = intval($_POST['id']);


// =========================================================================================
// 3. MANEJO DE I/O: LIMPIEZA DEL SISTEMA DE ARCHIVOS (GARBAGE COLLECTION)
// =========================================================================================
// Antes de destruir la tupla en la base de datos, recuperamos la ruta del asset visual (portada).
// Si borramos el registro SQL primero, perderíamos la referencia y el archivo físico (.jpg/.png)
// quedaría aislado en el servidor consumiendo almacenamiento de forma perpetua (Storage Leak).
$sql = "SELECT portada FROM obras WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($obra = $resultado->fetch_assoc()) {
    $ruta_imagen = $obra['portada'];
    // Verificamos que la imagen exista físicamente y que sea un archivo local 
    // (ignoramos URLs absolutas externas, como placeholders de APIs, para evitar excepciones I/O).
    if (file_exists($ruta_imagen) && strpos($ruta_imagen, 'http') === false) {
        unlink($ruta_imagen);
    }
}


// =========================================================================================
// 4. PURGA ESTRUCTURAL (INTEGRIDAD REFERENCIAL Y CASCADA)
// =========================================================================================
// Ejecutamos la eliminación de la entidad padre ('obras').
// NOTA ARQUITECTÓNICA: Delegamos el mantenimiento de la integridad relacional al motor MySQL. 
// Las tablas hijas (capítulos, reseñas, favoritos, obra_genero) poseen claves foráneas (Foreign Keys) 
// con la restricción ON DELETE CASCADE, lo que garantiza que todo el árbol de dependencias 
// asíncronas de la obra se destruya de forma atómica y consistente con un solo query.
$sql_delete = "DELETE FROM obras WHERE id = ?";
$stmt_delete = $conn->prepare($sql_delete);
$stmt_delete->bind_param("i", $id);

if ($stmt_delete->execute()) {
    // Patrón PRG (Post/Redirect/Get) con inyección de feedback mediante Query Params
    header("Location: /admin?msg=" . urlencode("Obra y dependencias eliminadas correctamente."));
} else {
    // Fallback de gestión de errores
    header("Location: /admin?msg=" . urlencode("Error crítico en la transacción SQL."));
}

// Finalizamos explícitamente el hilo de ejecución del servidor
exit();
?>