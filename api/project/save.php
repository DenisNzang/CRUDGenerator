<?php
// Este archivo ya no se usa directamente, pero se mantiene por compatibilidad
// La funcionalidad se ha movido a api/index.php con la acción 'save_project'

// Obtener los datos del proyecto
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Error al decodificar JSON: ' . json_last_error_msg()
    ]);
    exit;
}

if (!isset($input['projectData'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No se proporcionaron datos del proyecto'
    ]);
    exit;
}

$projectData = $input['projectData'];
$projectName = $input['projectName'] ?? 'proyecto_guardado';
$projectLocation = $input['projectLocation'] ?? 'default';
$customPath = $input['customPath'] ?? '';

// Validar nombre del proyecto
$projectName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $projectName);
if (empty($projectName)) {
    $projectName = 'proyecto_guardado';
}

// Determinar directorio de proyectos
if ($projectLocation === 'custom' && !empty($customPath)) {
    $projectsDir = rtrim($customPath, '/') . '/';
} else {
    $projectsDir = __DIR__ . '/../../../projects/'; // Subir un nivel más desde api/project/
    if (!is_dir($projectsDir)) {
        mkdir($projectsDir, 0755, true);
    }
}

// Asegurar que el directorio exista
if (!is_dir($projectsDir)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "No se pudo crear el directorio de proyectos: $projectsDir"
    ]);
    exit;
}

// Nombre del archivo con extensión .sti
$fileName = $projectName . '.sti';
$filePath = $projectsDir . $fileName;

// Codificar y guardar los datos del proyecto
$projectContent = json_encode($projectData, JSON_PRETTY_PRINT);
if ($projectContent === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al codificar los datos del proyecto: ' . json_last_error_msg()
    ]);
    exit;
}

if (file_put_contents($filePath, $projectContent) === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "No se pudo guardar el archivo de proyecto en: $filePath"
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'filePath' => str_replace(__DIR__ . '/../../../', '', $filePath),
    'message' => 'Proyecto guardado exitosamente'
]);