<?php
// core/generators/app_generator.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Common.php';
require_once __DIR__ . '/TemplateGenerator.php';
require_once __DIR__ . '/AuthGenerator.php';

/**
 * Clase principal para generar aplicaciones CRUD
 */
class AppGenerator {
    private $appData;
    private $outputDir;
    private $templateGenerator;

    public function __construct($appData) {
        if (!is_array($appData)) {
            throw new Exception("appData debe ser un array");
        }

        $this->validateAppData($appData);

        $appName = Utils::cleanAppName($appData['appCustomization']['title'] ?? 'app');
        $this->outputDir = GENERATED_APPS_DIR . $appName . '_' . time();

        $this->appData = $appData;
        $this->templateGenerator = new TemplateGenerator($this->appData);
        $this->authGenerator = new AuthGenerator();
    }

    private function validateAppData($appData) {
        if (!isset($appData['databaseType']) || !in_array($appData['databaseType'], unserialize(SUPPORTED_DATABASES))) {
            throw new Exception("Tipo de base de datos no soportado: " . ($appData['databaseType'] ?? 'no especificado'));
        }

        $title = $appData['appCustomization']['title'] ?? '';
        if (empty($title) || strlen($title) > MAX_FIELD_LABEL_LENGTH) {
            throw new Exception("Título de aplicación inválido o demasiado largo");
        }

        if (!isset($appData['selectedTables']) || !is_array($appData['selectedTables'])) {
            throw new Exception("No se han seleccionado tablas válidas");
        }

        foreach ($appData['selectedTables'] as $table) {
            if (strlen($table) > MAX_TABLE_NAME_LENGTH || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                throw new Exception("Nombre de tabla inválido: $table");
            }
        }
    }

    public function generate() {
        Utils::log("Inicio de generación de aplicación: " . ($this->appData['appCustomization']['title'] ?? 'Sin título'));

        try {
            Utils::createDirectory($this->outputDir);
            Utils::log("Directorio de salida creado: " . $this->outputDir);

            $this->generateAssets();
            $this->generateConfigFile();
            $this->generateIndexFile();
            $this->generateCRUDFiles();
            $this->generateAuthFiles();
            $this->copyAssets();

            $zipPath = $this->createZip();
            Utils::log("Archivo ZIP generado: " . $zipPath);

            // Vaciar carpeta de uploads después de completar la generación
            $this->cleanupUploads();

            $result = [
                'success' => true,
                'downloadUrl' => str_replace(GENERATED_APPS_DIR, 'generated-app/', $zipPath),
                'outputDir' => $this->outputDir
            ];

            Utils::log("Generación completada exitosamente");
            return $result;

        } catch (Exception $e) {
            Utils::log("Error durante la generación: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    private function generateAssets() {
        // Crear directorios necesarios
        Utils::createDirectory($this->outputDir . '/css');
        Utils::createDirectory($this->outputDir . '/js');
        Utils::createDirectory($this->outputDir . '/php');
        Utils::createDirectory($this->outputDir . '/templates');
        Utils::createDirectory($this->outputDir . '/config');
        Utils::createDirectory($this->outputDir . '/assets');
        Utils::createDirectory($this->outputDir . '/assets/db'); // Directorio para base de datos SQLite

        // Generar archivo de base de datos
        $this->generateDatabaseFile();
    }

    private function generateDatabaseFile() {
        $dbContent = <<<PHP
<?php
// Archivo de base de datos para la aplicación CRUD generada
class Database {
    private \$pdo;
    private \$config;

    public function __construct() {
        \$this->config = require __DIR__ . '/../config/config.php';  // Ruta corregida: subir un nivel para salir de /config y acceder a config.php
        \$this->connect();
    }

    private function connect() {
        \$dbConfig = \$this->config['database'];

        try {
            switch (\$dbConfig['type']) {
                case 'sqlite':
                    \$this->connectSQLite(\$dbConfig['connection']);
                    break;
                case 'mysql':
                    \$this->connectMySQL(\$dbConfig['connection']);
                    break;
                case 'postgresql':
                    \$this->connectPostgreSQL(\$dbConfig['connection']);
                    break;
                default:
                    throw new Exception("Tipo de base de datos no soportado: " . \$dbConfig['type']);
            }

            \$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (PDOException \$e) {
            throw new Exception("Error de conexión: " . \$e->getMessage());
        }
    }

    private function connectSQLite(\$connection) {
        // Construir la ruta relativa desde la ubicación de este archivo (en config/)
        // Para llegar a assets/db/, necesitamos subir un nivel con ../
        \$filePath = __DIR__ . '/../assets/db/' . basename(\$connection['file']);

        // Verificar si el archivo existe
        if (!file_exists(\$filePath)) {
            throw new Exception("Archivo de base de datos SQLite no encontrado: " . \$filePath);
        }

        \$this->pdo = new PDO("sqlite:" . \$filePath);
    }

    private function connectMySQL(\$connection) {
        \$dsn = "mysql:host={\$connection['host']};port={\$connection['port']};dbname={\$connection['database']};charset=utf8mb4";
        \$this->pdo = new PDO(\$dsn, \$connection['username'], \$connection['password']);
    }

    private function connectPostgreSQL(\$connection) {
        \$dsn = "pgsql:host={\$connection['host']};port={\$connection['port']};dbname={\$connection['database']}";
        \$this->pdo = new PDO(\$dsn, \$connection['username'], \$connection['password']);
    }

    public function query(\$sql, \$params = []) {
        try {
            \$stmt = \$this->pdo->prepare(\$sql);
            \$stmt->execute(\$params);
            return \$stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException \$e) {
            throw new Exception("Error en consulta: " . \$e->getMessage());
        }
    }

    public function execute(\$sql, \$params = []) {
        try {
            \$stmt = \$this->pdo->prepare(\$sql);
            return \$stmt->execute(\$params);
        } catch (PDOException \$e) {
            throw new Exception("Error en ejecución: " . \$e->getMessage());
        }
    }

    public function getLastInsertId() {
        return \$this->pdo->lastInsertId();
    }
}
?>
PHP;
        file_put_contents($this->outputDir . '/config/Database.php', $dbContent);
    }

    private function generateConfigFile() {
        $config = [
            'database' => [
                'type' => $this->appData['databaseType'],
                'connection' => $this->appData['connectionData']
            ],
            'tables' => $this->appData['selectedTables'],
            'queries' => $this->appData['customQueries'],
            'field_configurations' => $this->appData['fieldConfigurations'],
            'database_structure' => $this->appData['databaseStructure'],
            'app' => $this->appData['appCustomization']
        ];

        file_put_contents($this->outputDir . '/config/config.php', '<?php return ' . var_export($config, true) . '; ?>');
        file_put_contents($this->outputDir . '/config/config.json', json_encode($config, JSON_PRETTY_PRINT));
    }

    private function generateIndexFile() {
        $content = $this->templateGenerator->generateIndexTemplate();
        file_put_contents($this->outputDir . '/index.html', $content);
    }

    private function generateCRUDFiles() {
        // Generar archivos para tablas seleccionadas
        foreach ($this->appData['selectedTables'] as $table) {
            $this->generateTableFiles($table);
        }

        // Generar archivos para consultas personalizadas
        foreach ($this->appData['customQueries'] as $query) {
            $this->generateQueryFiles($query);
        }
    }

    private function generateTableFiles($tableName) {
        // Generar PHP para manejar CRUD de la tabla
        $phpContent = $this->templateGenerator->generateTablePHPTemplate($tableName);
        file_put_contents($this->outputDir . "/php/{$tableName}.php", $phpContent);

        // Generar template HTML para la tabla
        $htmlContent = $this->templateGenerator->generateTableHTMLTemplate($tableName);
        file_put_contents($this->outputDir . "/templates/{$tableName}.html", $htmlContent);
    }

    private function generateQueryFiles($query) {
        $phpContent = $this->templateGenerator->generateQueryPHPTemplate($query);
        $queryId = $query['id'] ?? uniqid('query_');
        file_put_contents($this->outputDir . "/php/query_{$queryId}.php", $phpContent);

        $htmlContent = $this->templateGenerator->generateQueryHTMLTemplate($query);
        file_put_contents($this->outputDir . "/templates/query_{$queryId}.html", $htmlContent);
    }

    private function copyAssets() {
        // Crear directorios
        Utils::createDirectory($this->outputDir . '/css');
        Utils::createDirectory($this->outputDir . '/css/fonts'); // Para fuentes de Bootstrap Icons
        Utils::createDirectory($this->outputDir . '/js');

        // Copiar archivos CSS desde el generador
        $cssFiles = [
            'bootstrap.min.css',
            'bootstrap-icons.css',
            'dataTables.bootstrap5.min.css'
        ];

        foreach ($cssFiles as $cssFile) {
            // Buscar en la nueva estructura de directorios
            $source = __DIR__ . '/../../../src/shared/assets/css/bootstrap/' . $cssFile;
            if (!file_exists($source)) {
                $source = __DIR__ . '/../../../src/shared/assets/css/icons/' . $cssFile;
                if (!file_exists($source)) {
                    $source = __DIR__ . '/../../../src/shared/assets/css/datatables/' . $cssFile;
                    if (!file_exists($source)) {
                        $source = __DIR__ . '/../../../css/' . $cssFile; // Ruta anterior por compatibilidad
                    }
                }
            }
            $dest = $this->outputDir . '/css/' . $cssFile;
            if (file_exists($source)) {
                copy($source, $dest);
            }
        }

        // Copiar fuentes de Bootstrap Icons
        $this->copyBootstrapIconsFonts();

        // Copiar archivos JS desde el generador
        $jsFiles = [
            'jquery-3.6.0.min.js',
            'bootstrap.bundle.min.js',
            'jquery.dataTables.min.js',
            'dataTables.bootstrap5.min.js',
            'jspdf.min.js',
            'jspdf-autotable.min.js',
            'xlsx.full.min.js'
        ];

        foreach ($jsFiles as $jsFile) {
            // Buscar en la nueva estructura de directorios
            $source = __DIR__ . '/../../../src/shared/assets/js/libs/' . $jsFile;
            if (!file_exists($source)) {
                $source = __DIR__ . '/../../../js/' . $jsFile; // Ruta anterior por compatibilidad
            }
            $dest = $this->outputDir . '/js/' . $jsFile;
            if (file_exists($source)) {
                copy($source, $dest);
            }
        }

        // Copiar archivos de idioma
        $langFiles = [
            'dataTables.spanish.json',
            'dataTables.english.json',
            'dataTables.french.json'
        ];

        foreach ($langFiles as $langFile) {
            // Buscar en la nueva estructura de directorios
            $source = __DIR__ . '/../../../src/shared/assets/js/libs/' . $langFile;
            if (!file_exists($source)) {
                $source = __DIR__ . '/../../../js/' . $langFile; // Ruta anterior por compatibilidad
            }
            $dest = $this->outputDir . '/js/' . $langFile;
            if (file_exists($source)) {
                copy($source, $dest);
            }
        }

        // Crear y copiar archivo app.js para la aplicación generada
        $this->createAppJSFile();

        // Si la base de datos es SQLite, copiar el archivo original
        if ($this->appData['databaseType'] === 'sqlite') {
            $this->copySqliteFile();
        }

        // Manejar archivo de logo si existe
        $this->handleLogo();

        // Actualizar información de empresa en todos los archivos HTML generados
        $this->updateCompanyInfoInGeneratedFiles();
    }

    private function copyBootstrapIconsFonts() {
        // Buscar en la nueva estructura de directorios
        $sourceFontsDir = __DIR__ . '/../../../src/shared/assets/css/icons/fonts/';
        if (!is_dir($sourceFontsDir)) {
            $sourceFontsDir = __DIR__ . '/../../css/fonts/'; // Ruta anterior por compatibilidad
        }

        if (is_dir($sourceFontsDir)) {
            $fontFiles = scandir($sourceFontsDir);

            foreach ($fontFiles as $fontFile) {
                if ($fontFile !== '.' && $fontFile !== '..') {
                    $sourcePath = $sourceFontsDir . $fontFile;
                    $destPath = $this->outputDir . '/css/fonts/' . $fontFile;

                    if (is_file($sourcePath)) {
                        copy($sourcePath, $destPath);
                    }
                }
            }
        }
    }

    private function createAppJSFile() {
        $appJSContent = file_get_contents(__DIR__ . '/AppJsTemplate.php');
        file_put_contents($this->outputDir . '/js/app.js', $appJSContent);
    }

    private function copySqliteFile() {
        $connectionData = $this->appData['connectionData'];

        // Buscar el archivo SQLite más reciente en el directorio de subidas
        // La ruta correcta es 3 niveles hacia atrás desde generators: generators -> core -> src -> (raíz del proyecto)
        $uploadDir = __DIR__ . '/../../../uploads/';
        $files = scandir($uploadDir);
        $latestFile = null;
        $latestTime = 0;

        foreach ($files as $file) {
            if (strpos($file, 'sqlite_') === 0) {
                $filePath = $uploadDir . $file;
                $fileTime = filemtime($filePath);
                if ($fileTime > $latestTime) {
                    $latestTime = $fileTime;
                    $latestFile = $filePath;
                }
            }
        }

        if ($latestFile && file_exists($latestFile)) {
            // Crear directorio de base de datos si no existe
            $dbDir = $this->outputDir . '/assets/db/';
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }

            // Copiar con el nombre original al directorio de la base de datos
            $destPath = $dbDir . basename($latestFile);

            if (copy($latestFile, $destPath)) {
                Utils::log("Base de datos SQLite copiada: " . basename($latestFile));

                // Actualizar la configuración para que apunte al archivo copiado
                $this->updateSqliteConfigPath(basename($latestFile));
            } else {
                Utils::log("Error al copiar la base de datos SQLite", 'ERROR');
                throw new Exception("No se pudo copiar la base de datos SQLite");
            }
        } else {
            Utils::log("Archivo SQLite original no encontrado", 'ERROR');
            throw new Exception("Archivo SQLite no encontrado para copiar");
        }
    }

    private function updateSqliteConfigPath($dbName) {
        // Actualizar la configuración para que apunte al archivo en assets/db/
        $configPath = $this->outputDir . '/config/config.php';
        if (file_exists($configPath)) {
            $config = require $configPath;

            if (isset($config['database']['connection'])) {
                // Actualizar la ruta del archivo SQLite
                $config['database']['connection']['file'] = "assets/db/$dbName";

                // Guardar la configuración actualizada
                $configContent = "<?php return " . var_export($config, true) . "; ?>";
                file_put_contents($configPath, $configContent);
            }
        }
    }

    private function handleLogo() {
        if (isset($this->appData['appCustomization']['logo'])) {
            $logoData = $this->appData['appCustomization']['logo'];

            if (is_array($logoData) && isset($logoData['tmp_name']) && file_exists($logoData['tmp_name'])) {
                $uploadDir = $this->outputDir . '/assets/';
                Utils::createDirectory($uploadDir);

                $fileExtension = pathinfo($logoData['name'], PATHINFO_EXTENSION);
                $fileName = 'logo.' . $fileExtension;
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($logoData['tmp_name'], $filePath)) {
                    // Actualizar el archivo index.html para que use el logo correcto
                    $indexPath = $this->outputDir . '/index.html';
                    if (file_exists($indexPath)) {
                        $content = file_get_contents($indexPath);
                        // Reemplazar el icono por defecto con la imagen del logo
                        $content = str_replace('<i class="bi bi-database me-2"></i>', '<img src="assets/' . $fileName . '" alt="Logo" class="logo-img" style="height: 30px; width: auto;">', $content);
                        file_put_contents($indexPath, $content);
                    }
                    return 'assets/' . $fileName;
                }
            } elseif (is_string($logoData) && file_exists($logoData)) {
                $uploadDir = $this->outputDir . '/assets/';
                Utils::createDirectory($uploadDir);

                $fileExtension = pathinfo($logoData, PATHINFO_EXTENSION);
                $fileName = 'logo.' . $fileExtension;
                $destPath = $uploadDir . $fileName;

                if (copy($logoData, $destPath)) {
                    // Actualizar el archivo index.html para que use el logo correcto
                    $indexPath = $this->outputDir . '/index.html';
                    if (file_exists($indexPath)) {
                        $content = file_get_contents($indexPath);
                        // Reemplazar el icono por defecto con la imagen del logo
                        $content = str_replace('<i class="bi bi-database me-2"></i>', '<img src="assets/' . $fileName . '" alt="Logo" class="logo-img" style="height: 30px; width: auto;">', $content);
                        file_put_contents($indexPath, $content);
                    }
                    return 'assets/' . $fileName;
                }
            } elseif (is_string($logoData) && strpos($logoData, 'data:image') === 0) {
                $uploadDir = $this->outputDir . '/assets/';
                Utils::createDirectory($uploadDir);

                $parts = explode(',', $logoData);
                if (count($parts) === 2) {
                    $imageData = base64_decode($parts[1]);
                    $finfo = finfo_open();
                    $mimeType = finfo_buffer($finfo, $imageData, FILEINFO_MIME_TYPE);
                    finfo_close($finfo);

                    $extensions = [
                        'image/jpeg' => 'jpg',
                        'image/jpg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/svg+xml' => 'svg',
                        'image/webp' => 'webp'
                    ];

                    $fileExtension = $extensions[$mimeType] ?? 'png';
                    $fileName = 'logo.' . $fileExtension;
                    $filePath = $uploadDir . $fileName;

                    if (file_put_contents($filePath, $imageData)) {
                        // Actualizar el archivo index.html para que use el logo correcto
                        $indexPath = $this->outputDir . '/index.html';
                        if (file_exists($indexPath)) {
                            $content = file_get_contents($indexPath);
                            // Reemplazar el icono por defecto con la imagen del logo
                            $content = str_replace('<i class="bi bi-database me-2"></i>', '<img src="assets/' . $fileName . '" alt="Logo" class="logo-img" style="height: 30px; width: auto;">', $content);
                            file_put_contents($indexPath, $content);
                        }
                        return 'assets/' . $fileName;
                    }
                }
            }
        }

        // Crear logo por defecto si no hay logo personalizado
        $this->createDefaultLogo();

        // Asegurarse de que el archivo index.html también se actualice para el logo por defecto
        $indexPath = $this->outputDir . '/index.html';
        if (file_exists($indexPath)) {
            $content = file_get_contents($indexPath);
            // El logo por defecto ya está como icono bootstrap, no necesitamos cambiar nada
        }

        return 'assets/default-logo.png';
    }

    private function createDefaultLogo() {
        $assetsDir = $this->outputDir . '/assets/';
        Utils::createDirectory($assetsDir);

        $logoPath = $assetsDir . 'default-logo.png';

        $svgContent = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="30" height="30" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg">
    <rect width="30" height="30" fill="#fd7e14" rx="4"/>
    <text x="15" y="20" font-family="Arial, sans-serif" font-size="12" font-weight="bold" fill="white" text-anchor="middle">CRUD</text>
</svg>';

        file_put_contents($logoPath, $svgContent);
    }

    private function updateCompanyInfoInGeneratedFiles() {
        // Obtener información de empresa
        $companyInfo = $this->appData['appCustomization']['companyInfo'] ?? [];

        $companyFooter = '';
        if (!empty($companyInfo['name'])) {
            $companyFooter = '<div class="company-info">';
            $companyFooter .= '<strong>' . htmlspecialchars($companyInfo['name']) . '</strong>';

            $addressParts = [];
            if (!empty($companyInfo['address'])) $addressParts[] = htmlspecialchars($companyInfo['address']);
            if (!empty($companyInfo['city'])) $addressParts[] = htmlspecialchars($companyInfo['city']);
            if (!empty($companyInfo['province'])) $addressParts[] = htmlspecialchars($companyInfo['province']);
            if (!empty($companyInfo['country'])) $addressParts[] = htmlspecialchars($companyInfo['country']);

            if (!empty($addressParts)) {
                $companyFooter .= '<br><small>' . implode(', ', $addressParts) . '</small>';
            }

            if (!empty($companyInfo['phone'])) {
                $companyFooter .= '<br><small><i class="bi bi-telephone"></i> ' . htmlspecialchars($companyInfo['phone']) . '</small>';
            }
            if (!empty($companyInfo['email'])) {
                $companyFooter .= '<br><small><i class="bi bi-envelope"></i> ' . htmlspecialchars($companyInfo['email']) . '</small>';
            }
            if (!empty($companyInfo['website'])) {
                $companyFooter .= '<br><small><i class="bi bi-globe"></i> <a href="' . htmlspecialchars($companyInfo['website']) . '" target="_blank">' . htmlspecialchars($companyInfo['website']) . '</a></small>';
            }

            $companyFooter .= '</div>';
        } else {
            // Si no hay información de empresa, mostrar solo el nombre de la aplicación
            $appTitle = $this->appData['appCustomization']['title'] ?? 'Mi App CRUD';
            $companyFooter = '© ' . date('Y') . ' ' . htmlspecialchars($appTitle) . ' - Todos los derechos reservados';
        }

        // Buscar y actualizar todos los archivos HTML en la carpeta de salida
        $this->replaceInAllHtmlFiles('{{COMPANY_INFO_FOOTER}}', $companyFooter);
    }

    private function replaceInAllHtmlFiles($search, $replace) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->outputDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'html') {
                $content = file_get_contents($file->getPathname());
                if (strpos($content, $search) !== false) {
                    $updatedContent = str_replace($search, $replace, $content);
                    file_put_contents($file->getPathname(), $updatedContent);
                    Utils::log("Actualizado marcador en: " . $file->getFilename());
                }
            }
        }
    }

    private function cleanupUploads() {
        $uploadDir = __DIR__ . '/../../uploads/';

        if (!is_dir($uploadDir)) {
            return; // No hay directorio que limpiar
        }

        $files = scandir($uploadDir);

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = $uploadDir . $file;

                // Eliminar todos los archivos temporales (no los directorios)
                if (is_file($filePath)) {
                    if (unlink($filePath)) {
                        Utils::log("Archivo temporal eliminado: " . $file);
                    } else {
                        Utils::log("No se pudo eliminar archivo temporal: " . $file, 'ERROR');
                    }
                }
            }
        }
    }

    private function createZip() {
        $zip = new ZipArchive();
        $zipPath = $this->outputDir . '/application.zip';

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $this->addFolderToZip($this->outputDir, $zip);
            $zip->close();

            return $zipPath;
        }

        throw new Exception("No se pudo crear el archivo ZIP");
    }

    private function addFolderToZip($folder, &$zip, $parent = '') {
        $files = scandir($folder);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $filePath = $folder . '/' . $file;
            $localPath = $parent . $file;

            if (is_dir($filePath)) {
                $zip->addEmptyDir($localPath);
                $this->addFolderToZip($filePath, $zip, $localPath . '/');
            } else {
                $zip->addFile($filePath, $localPath);
            }
        }
    }

    private function generateAuthFiles() {
        $authEnabled = $this->appData['authEnabled'] ?? false;

        if (!$authEnabled) {
            // Si la autenticación no está habilitada, no generar archivos de autenticación
            return;
        }

        // Generar archivos de autenticación
        $authFiles = $this->authGenerator->generateAuthFiles($this->appData);

        foreach ($authFiles as $filePath => $content) {
            $fullPath = $this->outputDir . '/' . $filePath;
            $dir = dirname($fullPath);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($fullPath, $content);
        }
    }
}