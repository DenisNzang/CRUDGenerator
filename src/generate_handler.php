<?php
// Establecer encabezados para respuesta JSON
header('Content-Type: application/json; charset=utf-8');

// Capturar errores para evitar que se muestren en la salida JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Limpiar posibles salidas previas
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    require_once __DIR__ . '/../php/config.php';
    require_once __DIR__ . '/Utils/Logger.php';
    
    // Cargar el generador
    require_once __DIR__ . '/Generator/CRUDGenerator.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'generate_app':
                $appData = json_decode($_POST['app_data'], true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
                }

                // Manejar archivo de logo si existe
                if (isset($_FILES['app_logo']) && $_FILES['app_logo']['error'] === UPLOAD_ERR_OK) {
                    $appData['appCustomization']['logo'] = $_FILES['app_logo'];
                }

                $generator = new CRUDGenerator($appData);
                $result = $generator->generate();

                echo json_encode($result);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    }
} catch (Exception $e) {
    error_log("Error en CRUDGenerator: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

exit();
?>