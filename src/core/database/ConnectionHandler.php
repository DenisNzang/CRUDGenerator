<?php
// core/handlers/connection_handler.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Common.php';

/**
 * Clase para manejar conexiones a bases de datos
 */
class ConnectionHandler {
    
    private $pdo;
    private $databaseType;

    public function __construct($databaseType, $connectionData) {
        $this->databaseType = $databaseType;
        $this->connect($connectionData);
    }

    private function connect($connectionData) {
        try {
            switch ($this->databaseType) {
                case 'sqlite':
                    $this->connectSQLite($connectionData);
                    break;
                case 'mysql':
                    $this->connectMySQL($connectionData);
                    break;
                case 'postgresql':
                    $this->connectPostgreSQL($connectionData);
                    break;
                default:
                    throw new Exception("Tipo de base de datos no soportado: " . $this->databaseType);
            }

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (PDOException $e) {
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }

    private function connectSQLite($connectionData) {
        if (isset($connectionData['file']) && is_string($connectionData['file'])) {
            $filePath = $connectionData['file'];
        } elseif (isset($connectionData['sqlite_file_upload'])) {
            $filePath = $this->handleSQLiteUpload($connectionData['sqlite_file_upload']);
        } else {
            throw new Exception("No se proporcionó archivo SQLite");
        }

        if (!file_exists($filePath)) {
            throw new Exception("El archivo SQLite no existe: " . $filePath);
        }

        $this->pdo = new PDO("sqlite:$filePath");
    }

    private function connectMySQL($connectionData) {
        $host = filter_var($connectionData['host'] ?? 'localhost', FILTER_VALIDATE_IP) ?
                $connectionData['host'] :
                filter_var($connectionData['host'] ?? 'localhost', FILTER_SANITIZE_URL);
        $port = filter_var($connectionData['port'] ?? '3306', FILTER_VALIDATE_INT,
                          ["options" => ["min_range" => 1, "max_range" => 65535]]) ?: '3306';
        $database = filter_var($connectionData['database'] ?? '', FILTER_SANITIZE_STRING);
        $username = filter_var($connectionData['username'] ?? '', FILTER_SANITIZE_STRING);
        $password = $connectionData['password'] ?? '';

        // Validar formato de host
        if (!filter_var($host, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\.-]*[a-zA-Z0-9]$/', $host)) {
            throw new Exception("Host no válido");
        }

        $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
        $this->pdo = new PDO($dsn, $username, $password);
    }

    private function connectPostgreSQL($connectionData) {
        $host = filter_var($connectionData['host'] ?? 'localhost', FILTER_VALIDATE_IP) ?
                $connectionData['host'] :
                filter_var($connectionData['host'] ?? 'localhost', FILTER_SANITIZE_URL);
        $port = filter_var($connectionData['port'] ?? '5432', FILTER_VALIDATE_INT,
                          ["options" => ["min_range" => 1, "max_range" => 65535]]) ?: '5432';
        $database = filter_var($connectionData['database'] ?? '', FILTER_SANITIZE_STRING);
        $schema = filter_var($connectionData['schema'] ?? 'public', FILTER_SANITIZE_STRING);
        $username = filter_var($connectionData['username'] ?? '', FILTER_SANITIZE_STRING);
        $password = $connectionData['password'] ?? '';

        if (!filter_var($host, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\.-]*[a-zA-Z0-9]$/', $host)) {
            throw new Exception("Host no válido");
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;options='--client_encoding=UTF8'";
        $this->pdo = new PDO($dsn, $username, $password);
    }

    private function handleSQLiteUpload($fileData) {
        $uploadDir = TEMP_DIR;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedExtensions = unserialize(ALLOWED_SQLITE_EXTENSIONS);
        $fileExtension = pathinfo($fileData['name'], PATHINFO_EXTENSION);

        if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
            throw new Exception("Tipo de archivo no permitido. Use: " . implode(', ', $allowedExtensions));
        }

        $fileName = uniqid('sqlite_') . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;

        if (!move_uploaded_file($fileData['tmp_name'], $filePath)) {
            throw new Exception("Error al subir el archivo SQLite");
        }

        return $filePath;
    }

    public function testConnection() {
        try {
            $this->pdo->query("SELECT 1");
            return ['success' => true, 'message' => 'Conexión exitosa'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getPDO() {
        return $this->pdo;
    }

    public function close() {
        $this->pdo = null;
    }
}