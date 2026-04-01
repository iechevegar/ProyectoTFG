<?php
// 1. Iniciamos la sesión para poder acceder a ella
session_start();

// 2. Destruimos toda la información de la sesión (borra usuario, rol, etc.)
session_destroy();

// 3. Redirigimos al usuario a la página principal (o al login si prefieres)
header("Location: index.php");
exit();
?>