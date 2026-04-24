<?php
// =========================================================================================
// 1. CONFIGURACIÓN DE CONEXIÓN (CONNECTION STRING)
// =========================================================================================
// Definición de las credenciales de acceso al servidor de base de datos.
// NOTA ARQUITECTÓNICA PARA LA DEFENSA: En este entorno académico se han definido como variables 
// estáticas. Para un entorno empresarial (Enterprise), estos valores se aislarían en un archivo 
// de entorno seguro (.env) fuera de la raíz pública del servidor web para prevenir fugas de datos.
$servername = "sql100.infinityfree.com"; // Host DSN
$username = "if0_41551522";      // Usuario con privilegios DML/DDL
$password = "QYCyVphFpO5F";      // Credencial de acceso
$dbname = "if0_41551522_ioriscans"; // Esquema relacional

// =========================================================================================
// 2. INSTANCIACIÓN Y GESTIÓN DE EXCEPCIONES (FAIL-FAST)
// =========================================================================================
// Inicializamos el driver MySQLi (MySQL Improved) empleando el paradigma Orientado a Objetos.
$conn = new mysqli($servername, $username, $password, $dbname);

// Patrón Fail-Fast: Si el driver no puede establecer el handshake con el servidor SQL 
// (por caída de red, credenciales inválidas o servidor saturado), abortamos toda la ejecución 
// de la aplicación (die). Es preferible una pantalla blanca con un error controlado que 
// permitir que el resto del código PHP falle en cascada lanzando warnings y exponiendo rutas.
if ($conn->connect_error) {
    die("Error crítico de infraestructura: No se pudo establecer el enlace PDO/MySQLi (" . $conn->connect_error . ")");
}

// =========================================================================================
// 3. INTEGRIDAD DE DATOS (ENCODING Y COLLATION)
// =========================================================================================
// Forzamos explícitamente el set de caracteres a UTF-8 en la conexión.
// Esto es VITAL para el soporte multilenguaje (caracteres japoneses en títulos manga, 
// tildes, emojis en el foro, etc.) y previene vulnerabilidades de truncamiento SQL 
// derivadas de discrepancias de codificación entre el cliente y el servidor.
$conn->set_charset("utf8");

// =========================================================================================
// 4. SINCRONIZACIÓN TEMPORAL ESTRICTA (TIMEZONE SYNC)
// =========================================================================================
// A. Capa de Aplicación: Forzamos a PHP a operar en la zona horaria local.
date_default_timezone_set('Europe/Madrid');

// B. Capa de Persistencia: Modificamos el offset temporal de la sesión actual de MySQL.
// ¿Por qué es importante? Porque los servidores de bases de datos suelen estar configurados en UTC.
// Si no hacemos esto, la función NOW() de MySQL guardaría las fechas con varias horas de 
// desfase respecto a la función date() de PHP, corrompiendo el Audit Trail y el orden del foro.
$conn->query("SET time_zone = '+02:00'");
?>