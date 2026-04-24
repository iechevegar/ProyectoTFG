<?php
// Iniciamos sesión por si el header necesita comprobar si hay un usuario logueado
session_start();

// Aunque sea una página de error, requerimos la BD porque el header/footer pueden necesitarla (ej. para menús dinámicos)
require 'includes/db.php';
include 'includes/header.php'; 
?>

<main class="container py-5 text-center d-flex flex-column justify-content-center align-items-center error-container">
    
    <div class="mb-0">
        <img src="/assets/img/logo/logo-ioriscans-vertical.svg" alt="IoriScans Logo" class="error-logo mb-3" onerror="this.style.display='none';">
    </div>
    
    <h1 class="display-1 fw-bold text-dark mb-2">404</h1>
    
    <h3 class="fw-bold text-secondary mb-4">¡Te has perdido en una mazmorra!</h3>
    
    <p class="text-muted fs-5 mb-5 error-text">
        La página que estás buscando no existe, ha sido borrada o el enlace es incorrecto. 
        No te preocupes, puedes volver a la zona segura pulsando el botón de abajo.
    </p>
    
    <a href="/" class="btn btn-iori btn-lg fw-bold px-5 shadow-sm rounded-pill hover-scale">
        <i class="fas fa-home me-2"></i> Volver al Catálogo
    </a>

</main>

<?php 
// Cargamos el pie de página para mantener la coherencia visual en toda la plataforma
include 'includes/footer.php'; 
?>