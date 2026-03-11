<?php
// Este archivo ya no se usa directamente, pero se mantiene por compatibilidad
// La funcionalidad se ha movido a api/index.php con la acción 'load_project'

// Este archivo se puede usar para cargar proyectos desde archivos subidos o rutas específicas
// pero la funcionalidad principal se manejará en api/index.php

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Error al decodificar JSON: ' . json_last_error_msg()
    ]);
    exit;
}

// Cargar proyecto desde una ruta específica
$projectFilePath = $input['projectFilePath'] ?? '';

if (empty($projectFilePath)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No se proporcionó archivo de proyecto'
    ]);
    exit;
}

// Validar que la ruta sea segura (no contenga .. o rutas absolutas peligrosas)
if (strpos($projectFilePath, '..') !== false || strpos($projectFilePath, './') === 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Ruta de archivo no válida'
    ]);
    exit;
}

// Asegurar que sea un archivo .sti
if (!str_ends_with(strtolower($projectFilePath), '.sti')) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'El archivo debe tener extensión .sti'
    ]);
    exit;
}

$fullPath = __DIR__ . '/../../../' . ltrim($projectFilePath, '/');

if (!file_exists($fullPath)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => "El archivo de proyecto no existe: $fullPath"
    ]);
    exit;
}

$content = file_get_contents($fullPath);
if ($content === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo leer el archivo de proyecto'
    ]);
    exit;
}

$projectData = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al decodificar el archivo de proyecto: ' . json_last_error_msg()
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'projectData' => $projectData,
    'message' => 'Proyecto cargado exitosamente'
]);