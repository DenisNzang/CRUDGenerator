<?php
// api/generation/app_generator.php

require_once __DIR__ . '/../../src/core/config/config.php';
require_once __DIR__ . '/../../src/core/utils/Common.php';
require_once __DIR__ . '/../../src/core/generators/AppGenerator.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'generate_app':
            // Decodificar datos de la aplicación
            $appData = json_decode($_POST['app_data'] ?? '', true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
            }

            if (!is_array($appData)) {
                throw new Exception('Datos de aplicación inválidos');
            }

            // Manejar archivo de logo si existe
            if (isset($_FILES['app_logo']) && $_FILES['app_logo']['error'] === UPLOAD_ERR_OK) {
                $appData['appCustomization']['logo'] = $_FILES['app_logo'];
            }

            // Instanciar y ejecutar el generador
            $generator = new AppGenerator($appData);
            $result = $generator->generate();

            echo json_encode($result);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    Utils::log("Error en generación de app: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

exit();