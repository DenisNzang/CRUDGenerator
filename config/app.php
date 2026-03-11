<?php
// config/app.php

// Definiciones globales de configuración
define('APP_NAME', 'CRUD Generator');
define('APP_VERSION', '1.0.0');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('GENERATED_APPS_DIR', __DIR__ . '/../generated-app/');
define('TEMP_DIR', __DIR__ . '/../uploads/');

// Tipos de base de datos soportados
define('SUPPORTED_DATABASES', serialize(['sqlite', 'mysql', 'postgresql']));

// Extensiones permitidas
define('ALLOWED_SQLITE_EXTENSIONS', serialize(['db', 'sqlite', 'sqlite3']));
define('ALLOWED_IMAGE_EXTENSIONS', serialize(['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp']));

// Validación de entrada
define('MAX_TABLE_NAME_LENGTH', 64);
define('MAX_COLUMN_NAME_LENGTH', 64);
define('MAX_FIELD_LABEL_LENGTH', 128);

// Crear directorios necesarios si no existen
if (!file_exists(GENERATED_APPS_DIR)) {
    mkdir(GENERATED_APPS_DIR, 0755, true);
}

if (!file_exists(TEMP_DIR)) {
    mkdir(TEMP_DIR, 0755, true);
}

// Directorio de logs
$logDir = __DIR__ . '/../logs/';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
define('LOG_FILE', $logDir . 'app.log');