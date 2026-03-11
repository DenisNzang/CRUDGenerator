<?php
require_once __DIR__ . '/../../php/config.php';

/**
 * Clase base para manejar conexiones a diferentes tipos de bases de datos
 */
class DatabaseConnection {
    protected $pdo;
    protected $databaseType;

    /**
     * Constructor de la clase DatabaseConnection
     *
     * @param string $databaseType Tipo de base de datos ('sqlite', 'mysql', 'postgresql')
     * @param array $connectionData Datos de conexión específicos para cada tipo de base de datos
     * @throws Exception Si no se puede conectar a la base de datos
     */
    public function __construct($databaseType, $connectionData) {
        $this->databaseType = $databaseType;
        $this->connect($connectionData);
    }

    protected function connect($connectionData) {
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
                    throw new Exception("Tipo de base de datos no soportado");
            }

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (PDOException $e) {
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }

    protected function connectSQLite($connectionData) {
        // Manejar tanto archivo subido como ruta directa
        if (isset($connectionData['file']) && is_string($connectionData['file'])) {
            // Es una ruta de archivo
            $filePath = $connectionData['file'];
        } elseif (isset($connectionData['sqlite_file_upload'])) {
            // Es un archivo subido via $_FILES
            $filePath = $this->handleSQLiteUpload($connectionData['sqlite_file_upload']);
        } else {
            throw new Exception("No se proporcionó archivo SQLite");
        }

        // Verificar que el archivo existe
        if (!file_exists($filePath)) {
            throw new Exception("El archivo SQLite no existe: " . $filePath);
        }

        $this->pdo = new PDO("sqlite:$filePath");
    }

    protected function connectMySQL($connectionData) {
        // Validar y sanitizar entradas
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

    protected function connectPostgreSQL($connectionData) {
        // Validar y sanitizar entradas
        $host = filter_var($connectionData['host'] ?? 'localhost', FILTER_VALIDATE_IP) ?
                $connectionData['host'] :
                filter_var($connectionData['host'] ?? 'localhost', FILTER_SANITIZE_URL);
        $port = filter_var($connectionData['port'] ?? '5432', FILTER_VALIDATE_INT,
                          ["options" => ["min_range" => 1, "max_range" => 65535]]) ?: '5432';
        $database = filter_var($connectionData['database'] ?? '', FILTER_SANITIZE_STRING);
        $schema = filter_var($connectionData['schema'] ?? 'public', FILTER_SANITIZE_STRING);
        $username = filter_var($connectionData['username'] ?? '', FILTER_SANITIZE_STRING);
        $password = $connectionData['password'] ?? '';

        // Validar formato de host
        if (!filter_var($host, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\.-]*[a-zA-Z0-9]$/', $host)) {
            throw new Exception("Host no válido");
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;options='--client_encoding=UTF8'";
        $this->pdo = new PDO($dsn, $username, $password);
    }

    protected function handleSQLiteUpload($fileData) {
        // Directorio para archivos subidos
        $uploadDir = __DIR__ . '/../../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Validar que es un archivo SQLite
        $allowedExtensions = ALLOWED_SQLITE_EXTENSIONS;
        $fileExtension = pathinfo($fileData['name'], PATHINFO_EXTENSION);

        if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
            throw new Exception("Tipo de archivo no permitido. Use: " . implode(', ', $allowedExtensions));
        }

        // Generar nombre único
        $fileName = uniqid('sqlite_') . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;

        // Mover archivo subido
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