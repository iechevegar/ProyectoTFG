<?php
// =========================================================================================
// GESTIÓN DEL CICLO DE VIDA DE LA SESIÓN (LOGOUT)
// =========================================================================================

// 1. Recuperación del Contexto: Inicializamos el motor para enlazar con la sesión activa del cliente.
session_start();

// 2. Invalidación de Token y Purga de Datos: 
// Destruimos físicamente la sesión en el servidor. Esto elimina todas las variables en memoria 
// ($_SESSION['usuario'], $_SESSION['rol'], etc.), garantizando que las credenciales 
// no queden expuestas ni puedan ser reutilizadas mediante ataques de Session Hijacking.
session_destroy();

// 3. Enrutamiento Semántico: Expulsamos al usuario del entorno privado y lo devolvemos 
// a la raíz pública de la plataforma (Catálogo), rompiendo el flujo de navegación previo.
header("Location: /");
exit();
?>