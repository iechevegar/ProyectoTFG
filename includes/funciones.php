<?php
// Función para limpiar textos y usarlos en URLs amigables
function limpiarURL($string) {
    // 1. Convertir todo a minúsculas y quitar espacios a los lados
    $string = strtolower(trim($string));
    
    // 2. Reemplazar cualquier cosa que no sea letra o número por un guion (-)
    $string = preg_replace('/[^a-z0-9-]/', '-', $string); 
    
    // 3. Si hay varios guiones seguidos (ej: "solo---leveling"), dejar solo uno
    $string = preg_replace('/-+/', '-', $string); 
    
    // 4. Quitar guiones que hayan podido quedar al principio o al final
    return trim($string, '-'); 
}
?>