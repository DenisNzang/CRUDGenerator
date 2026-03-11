<?php
// api/database/connection.php

require_once __DIR__ . '/../../src/core/config/config.php';
require_once __DIR__ . '/../../src/core/utils/Common.php';
require_once __DIR__ . '/../../src/core/database/ConnectionHandler.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'test_connection':
            $databaseType = $_POST['database_type'] ?? '';
            $connectionData = [];

            if ($databaseType === 'sqlite') {
                if (isset($_FILES['sqlite_file'])) {
                    $connectionData['sqlite_file_upload'] = $_FILES['sqlite_file'];
                } else {
                    throw new Exception("No se proporcionó archivo SQLite");
                }
            } else {
                $connectionData = [
                    'host' => $_POST['host'] ?? '',
                    'port' => $_POST['port'] ?? '',
                    'database' => $_POST['database'] ?? '',
                    'schema' => $_POST['schema'] ?? 'public',
                    'username' => $_POST['username'] ?? '',
                    'password' => $_POST['password'] ?? ''
                ];
            }

            $db = new ConnectionHandler($databaseType, $connectionData);
            $result = $db->testConnection();
            $db->close();

            echo json_encode($result);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    Utils::log("Error en conexión: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

exit();