<?php
// api/index.php - Punto de entrada para la API

// Aumentar límites
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
ini_set('display_errors', 0);

// Limpiar buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Establecer encabezados
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// Obtener la acción desde POST o desde el cuerpo JSON
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Si no se encontró en POST/GET y el Content-Type es JSON, intentar obtener del cuerpo
if (empty($action) && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

// Rutas de la API basadas en la acción
if ($method === 'POST') {
    switch ($action) {
        case 'test_connection':
            require_once __DIR__ . '/database/connection.php';
            break;
        case 'analyze_database':
            require_once __DIR__ . '/database/analyzer.php';
            break;
        case 'generate_app':
            require_once __DIR__ . '/generation/app_generator.php';
            break;
        case 'save_project':
            require_once __DIR__ . '/project/save.php';
            break;
        case 'load_project':
            require_once __DIR__ . '/project/load.php';
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint no encontrado']);
            break;
    }
} else {
    http_response_code(405); // Método no permitido
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}