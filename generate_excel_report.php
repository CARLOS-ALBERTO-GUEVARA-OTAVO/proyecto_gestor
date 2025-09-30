<?php

/**
 * Script para generar un reporte en Excel (.xlsx) con la lista completa de archivos
 * y sus rutas desde una cuenta de Google Drive usando una Service Account.
 *
 * USO:
 * 1. Asegúrate de tener las dependencias: `composer require google/apiclient phpoffice/phpspreadsheet`
 * 2. Coloca tu archivo de credenciales de Service Account en la misma carpeta (o ajusta la ruta).
 * 3. Ejecuta este script desde la línea de comandos: `php generate_excel_report.php`
 *    o ábrelo en tu navegador. La ejecución puede tardar si tienes muchos archivos.
 *
 * @version 1.0
 * @author Gemini Code Assist
 */

ini_set('memory_limit', '1024M'); // Aumenta el límite de memoria para procesar muchos archivos
set_time_limit(600); // Aumenta el tiempo máximo de ejecución a 10 minutos

session_start();
require __DIR__ . '/vendor/autoload.php';

use Google\Client as Google_Client;
use Google\Service\Drive as Google_Service_Drive;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

echo "Iniciando proceso de generación de reporte de Google Drive...\n<br>";
flush();

// --- 1. CONFIGURACIÓN Y AUTENTICACIÓN CON GOOGLE DRIVE ---

// Nombre del archivo de credenciales de la cuenta de servicio.
$credentialsFile = __DIR__ . '/flotax-map-3949a96314d9.json';

// ID de la carpeta raíz desde la que se empezará a buscar.
// Puedes cambiarlo por el ID de una carpeta específica si no quieres escanear todo el Drive.
$rootFolderId = 'root'; // 'root' se refiere a "Mi unidad"

try {
    $client = new Google_Client();
    $client->setAuthConfig($credentialsFile);
    // Usamos 'drive.readonly' porque solo necesitamos leer la información.
    $client->addScope(Google_Service_Drive::DRIVE_READONLY);

    $token = $client->fetchAccessTokenWithAssertion();
    if (isset($token['error'])) {
        throw new Exception('Error de autenticación: ' . $token['error_description']);
    }

    $service = new Google_Service_Drive($client);
    echo "Autenticación con Google Drive exitosa.\n<br>";
    flush();

    // --- 2. OBTENER TODOS LOS ARCHIVOS Y CARPETAS (CON PAGINACIÓN) ---

    echo "Obteniendo la lista de todos los archivos y carpetas. Esto puede tardar...\n<br>";
    flush();

    $allFiles = [];
    $pageToken = null;

    do {
        $optParams = [
            'q' => "trashed = false", // Excluimos archivos en la papelera
            'pageSize' => 1000, // Máximo permitido por la API
            'fields' => 'nextPageToken, files(id, name, mimeType, parents)',
            'pageToken' => $pageToken
        ];

        $results = $service->files->listFiles($optParams);
        $files = $results->getFiles();
        $allFiles = array_merge($allFiles, $files);

        $pageToken = $results->getNextPageToken();
    } while ($pageToken !== null);

    echo "Se encontraron " . count($allFiles) . " elementos en total.\n<br>";
    flush();

    // --- 3. CONSTRUIR EL MAPA DE RUTAS ---

    echo "Construyendo las rutas completas de cada archivo...\n<br>";
    flush();

    // Creamos un mapa de carpetas para una búsqueda rápida: [folderId => folderName]
    // y un mapa de relaciones padre-hijo: [fileId => parentId]
    $folderMap = [];
    $parentMap = [];
    foreach ($allFiles as $file) {
        if ($file->getMimeType() === 'application/vnd.google-apps.folder') {
            $folderMap[$file->getId()] = $file->getName();
        }
        if (!empty($file->getParents())) {
            // Un archivo puede tener múltiples padres, lo guardamos como un array.
            $parentMap[$file->getId()] = $file->getParents();
        }
    }

    /**
     * Función recursiva para construir la ruta de un archivo/carpeta.
     * @param string $fileId El ID del elemento.
     * @param array $parentMap El mapa de relaciones padre-hijo.
     * @param array $folderMap El mapa de nombres de carpetas.
     * @return string La ruta completa.
     */
    function getPath(string $fileId, array $parentMap, array $folderMap): string {
        if (!isset($parentMap[$fileId])) {
            return ''; // Es un elemento en la raíz "Mi Unidad"
        }

        $parentId = $parentMap[$fileId][0]; // Tomamos el primer padre para la ruta principal
        if (!isset($folderMap[$parentId])) {
            return 'Ruta Desconocida'; // El padre no es una carpeta accesible o no existe
        }

        // Construimos la ruta recursivamente hacia arriba
        $path = getPath($parentId, $parentMap, $folderMap);
        return $path . $folderMap[$parentId] . '/';
    }

    // --- 4. PREPARAR Y GUARDAR EL ARCHIVO EXCEL ---

    echo "Generando el archivo Excel...\n<br>";
    flush();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Rutas Drive');

    // Encabezados de las columnas
    $sheet->setCellValue('A1', 'Nombre del Archivo');
    $sheet->setCellValue('B1', 'Ruta Completa');
    $sheet->setCellValue('C1', 'ID del Archivo');
    $sheet->setCellValue('D1', 'Tipo MIME');

    // Aplicar estilo a los encabezados
    $headerStyle = ['font' => ['bold' => true]];
    $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

    $rowIndex = 2; // Empezamos a escribir los datos en la fila 2

    foreach ($allFiles as $file) {
        // Ignoramos las carpetas en la lista final, solo queremos los archivos.
        if ($file->getMimeType() === 'application/vnd.google-apps.folder') {
            continue;
        }

        // Si un archivo está en múltiples carpetas, generamos una fila por cada una.
        if (isset($parentMap[$file->getId()])) {
            foreach ($parentMap[$file->getId()] as $parentId) {
                $path = getPath($parentId, $parentMap, $folderMap) . ($folderMap[$parentId] ?? 'Mi Unidad');
                $sheet->setCellValue('A' . $rowIndex, $file->getName());
                $sheet->setCellValue('B' . $rowIndex, $path);
                $sheet->setCellValue('C' . $rowIndex, $file->getId());
                $sheet->setCellValue('D' . $rowIndex, $file->getMimeType());
                $rowIndex++;
            }
        } else { // Archivos en la raíz "Mi Unidad"
            $sheet->setCellValue('A' . $rowIndex, $file->getName());
            $sheet->setCellValue('B' . $rowIndex, 'Mi Unidad');
            $sheet->setCellValue('C' . $rowIndex, $file->getId());
            $sheet->setCellValue('D' . $rowIndex, $file->getMimeType());
            $rowIndex++;
        }
    }

    // Autoajustar el ancho de las columnas
    foreach (range('A', 'D') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    $writer = new Xlsx($spreadsheet);
    $excelFilename = __DIR__ . '/rutas_drive.xlsx';
    $writer->save($excelFilename);

    echo "¡Proceso completado! El archivo ha sido guardado en: <strong>{$excelFilename}</strong>\n<br>";

} catch (Exception $e) {
    // Manejo de errores
    echo "Ocurrió un error: " . $e->getMessage();
}

?>