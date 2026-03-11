<?php
// api/middleware/validation.php

class ValidationMiddleware {
    public static function validateDatabaseConnectionData($data, $dbType) {
        $errors = [];
        
        if ($dbType === 'sqlite') {
            if (!isset($data['sqlite_file']) && !isset($data['file'])) {
                $errors[] = 'Archivo SQLite requerido';
            }
        } else {
            if (empty($data['host'])) {
                $errors[] = 'Host es requerido';
            }
            
            if (empty($data['port'])) {
                $errors[] = 'Port es requerido';
            } elseif (!filter_var($data['port'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 65535]])) {
                $errors[] = 'Port debe ser un número entre 1 y 65535';
            }
            
            if (empty($data['database'])) {
                $errors[] = 'Nombre de base de datos es requerido';
            }
            
            if (empty($data['username'])) {
                $errors[] = 'Usuario es requerido';
            }
        }
        
        return $errors;
    }
    
    public static function validateAppData($appData) {
        $errors = [];
        
        if (!isset($appData['appCustomization']['title'])) {
            $errors[] = 'Título de aplicación es requerido';
        }
        
        if (!isset($appData['databaseType']) || !in_array($appData['databaseType'], unserialize(SUPPORTED_DATABASES))) {
            $errors[] = 'Tipo de base de datos no soportado';
        }
        
        if (!isset($appData['selectedTables']) || !is_array($appData['selectedTables'])) {
            $errors[] = 'Tablas seleccionadas son requeridas y deben ser un array';
        }
        
        return $errors;
    }
}