<?php
// =========================================================================================
// LIBRERÍA DE FUNCIONES GLOBALES (CORE UTILITIES)
// =========================================================================================
// Centralizamos algoritmos de uso transversal en este archivo para cumplir con 
// el principio de ingeniería de software DRY (Don't Repeat Yourself), 
// facilitando la mantenibilidad y escalabilidad del código.

/**
 * Generador de Slugs (Enrutamiento Semántico)
 * * Transforma cadenas de texto natural (ej: "¿Qué opináis del final?") 
 * en identificadores URL-friendly y seguros (ej: "que-opinais-del-final").
 * * Beneficios Arquitectónicos:
 * - SEO Optimization: Permite indexar palabras clave en la URL.
 * - Seguridad (B-IDOR Mitigation): Sustituye el uso de Primary Keys numéricas expuestas al cliente.
 *
 * @param string $string La cadena de texto original (payload).
 * @return string El slug purificado.
 */
function limpiarURL($string) {
    
    // --- FASE 1: NORMALIZACIÓN BASE ---
    // Reducimos la entropía del input. Convertimos todo a minúsculas para evitar
    // duplicidades lógicas (ej: "Manga" vs "manga") y podamos hacer match exacto en la BD.
    // trim() elimina caracteres invisibles (espacios, saltos de línea) en los extremos.
    $string = strtolower(trim($string));
    
    // --- FASE 2: SANITIZACIÓN ESTRICTA (RegEx Whitelisting) ---
    // Utilizamos Expresiones Regulares para aplicar una política de "Lista Blanca".
    // El patrón '/[^a-z0-9-]/' busca CUALQUIER carácter que NO sea una letra inglesa (a-z), 
    // un dígito (0-9) o un guion medio (-), y lo sustituye por un guion.
    // Esto purga instantáneamente signos de interrogación, espacios, comillas y caracteres especiales 
    // que corromperían la estructura de una petición HTTP RESTful.
    $string = preg_replace('/[^a-z0-9-]/', '-', $string); 
    
    // --- FASE 3: COMPRESIÓN DE REDUNDANCIAS ---
    // El paso anterior suele dejar "basura estructural" (ej: "solo !leveling!" -> "solo--leveling-").
    // Este RegEx '/-+/' localiza secuencias de uno o más guiones consecutivos y 
    // los colapsa en un único guion, optimizando la longitud del string.
    $string = preg_replace('/-+/', '-', $string); 
    
    // --- FASE 4: LIMPIEZA DE BORDES (EDGE TRIMMING) ---
    // Un slug que empieza o termina por guion se considera un antipatrón de diseño de URLs.
    // Le indicamos a trim() que, en lugar de espacios, mutile los guiones de los extremos.
    return trim($string, '-'); 
}
?>