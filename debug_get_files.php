<?php
// debug_get_files.php
// Ejecuta get_files.php y muestra la salida RAW para depuración

header('Content-Type: text/plain');

ob_start();
include 'get_files.php';
$output = ob_get_clean();

echo "--- SALIDA RAW DE get_files.php ---\n\n";
echo $output;

echo "\n\n--- FIN DE SALIDA ---\n";

if (strpos($output, '<br') !== false || strpos($output, '<b>') !== false) {
    echo "\n\nADVERTENCIA: Se detectó salida HTML. Esto indica un error fatal o warning en PHP antes de enviar JSON.\n";
}
?>
