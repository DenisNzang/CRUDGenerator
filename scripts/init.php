<?php
// scripts/init.php

/**
 * Script de inicialización del proyecto
 */

// Verificar requisitos del sistema
function checkRequirements() {
    $errors = [];
    
    // Verificar versión de PHP
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $errors[] = 'Se requiere PHP 7.4 o superior. Versión actual: ' . PHP_VERSION;
    }
    
    // Verificar extensiones necesarias
    $requiredExtensions = ['pdo', 'pdo_mysql', 'pdo_pgsql', 'pdo_sqlite', 'zip', 'json', 'mbstring'];
    
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "Extensión requerida no disponible: $ext";
        }
    }
    
    return $errors;
}

// Crear directorios necesarios
function createDirectories() {
    $dirs = [
        __DIR__ . '/../logs',
        __DIR__ . '/../uploads',
        __DIR__ . '/../generated-app',
        __DIR__ . '/../src/shared/assets/fonts'
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception("No se pudo crear el directorio: $dir");
            }
        }
    }
}

// Verificar permisos
function checkPermissions() {
    $writablePaths = [
        __DIR__ . '/../logs',
        __DIR__ . '/../uploads',
        __DIR__ . '/../generated-app'
    ];
    
    $errors = [];
    
    foreach ($writablePaths as $path) {
        if (!is_writable($path)) {
            $errors[] = "Directorio no escribible: $path";
        }
    }
    
    return $errors;
}

// Ejecutar verificaciones
try {
    echo "Verificando requisitos del sistema...\n";
    $reqErrors = checkRequirements();
    
    if (!empty($reqErrors)) {
        foreach ($reqErrors as $error) {
            echo "ERROR: $error\n";
        }
        exit(1);
    }
    
    echo "Creando directorios necesarios...\n";
    createDirectories();
    
    echo "Verificando permisos...\n";
    $permErrors = checkPermissions();
    
    if (!empty($permErrors)) {
        foreach ($permErrors as $error) {
            echo "ERROR: $error\n";
        }
        exit(1);
    }
    
    echo "¡Inicialización completada exitosamente!\n";
    echo "El proyecto está listo para su uso.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}