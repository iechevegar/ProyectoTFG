<?php
// =========================================================================================
// CAPA DE ACCESO A DATOS — db.php
// =========================================================================================
// Las credenciales están centralizadas aquí. En producción real deberían
// cargarse desde variables de entorno o un .env fuera del webroot.

require_once __DIR__ . '/funciones.php';

// --- Credenciales ---
$servername = "sql100.infinityfree.com";
$username   = "if0_41551522";
$password   = "QYCyVphFpO5F";
$dbname     = "if0_41551522_ioriscans";

// --- Conexión (Fail-Fast) ---
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log('DB connect error: ' . $conn->connect_error);
    die('Error de infraestructura: no se pudo conectar a la base de datos.');
}

$conn->set_charset("utf8");
date_default_timezone_set('Europe/Madrid');
$conn->query("SET time_zone = '+02:00'");
?>