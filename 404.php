<?php
session_start();
require 'includes/db.php';
include 'includes/header.php'; 
?>

<main class="container py-5 text-center d-flex flex-column justify-content-center align-items-center" style="min-height: 70vh;">
    
    <div class="mb-4">
        <i class="fas fa-map-signs text-primary" style="font-size: 6rem; opacity: 0.8;"></i>
    </div>
    
    <h1 class="display-1 fw-bold text-dark mb-2">404</h1>
    <h3 class="fw-bold text-secondary mb-4">¡Te has perdido en una mazmorra!</h3>
    
    <p class="text-muted fs-5 mb-5" style="max-width: 600px;">
        La página que estás buscando no existe, ha sido borrada o el enlace es incorrecto. 
        No te preocupes, puedes volver a la zona segura pulsando el botón de abajo.
    </p>
    
    <a href="index.php" class="btn btn-primary btn-lg fw-bold px-5 shadow-sm rounded-pill hover-scale">
        <i class="fas fa-home me-2"></i> Volver al Catálogo
    </a>

</main>

<style>
    /* Pequeña animación para el botón */
    .hover-scale { transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .hover-scale:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1)!important; }
</style>

<?php include 'includes/footer.php'; ?>