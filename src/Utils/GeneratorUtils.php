<?php
require_once __DIR__ . '/../../php/config.php';

/**
 * Clase de utilidades para la generación de aplicaciones CRUD
 */
class GeneratorUtils {
    
    /**
     * Formatea un nombre de campo para mostrar
     */
    public static function formatFieldName($name) {
        $name = str_replace('_', ' ', $name);
        $name = preg_replace('/([A-Z])/', ' $1', $name);
        return trim(ucfirst($name));
    }

    /**
     * Limpia el nombre de la aplicación para uso en directorios
     */
    public static function cleanAppName($appName) {
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $appName);
        $cleanName = preg_replace('/_{2,}/', '_', $cleanName);
        $cleanName = trim($cleanName, '_');
        return $cleanName ?: 'app';
    }

    /**
     * Escapa caracteres para uso seguro en JavaScript
     */
    public static function escapeForJavaScript($str) {
        return addslashes($str);
    }

    /**
     * Oscurece un color hexadecimal
     */
    public static function darkenColor($color, $percent) {
        $color = str_replace('#', '', $color);
        if (strlen($color) == 3) {
            $color = $color[0].$color[0].$color[1].$color[1].$color[2].$color[2];
        }

        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));

        $r = max(0, min(255, $r - ($r * $percent / 100)));
        $g = max(0, min(255, $g - ($g * $percent / 100)));
        $b = max(0, min(255, $b - ($b * $percent / 100)));

        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
                   . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
                   . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene el carácter de comillas según el tipo de base de datos
     */
    public static function getQuoteCharacterForDbType($dbType) {
        switch ($dbType) {
            case 'sqlite':
                return '"';
            case 'mysql':
                return '`';
            case 'postgresql':
                return '"';
            case 'sqlserver':
                return '[';
            default:
                return '`';
        }
    }

    /**
     * Determina el tipo de control predeterminado para una columna
     */
    public static function getDefaultControlTypeForColumn($columnType, $isPrimaryKey = false) {
        if ($isPrimaryKey) {
            return 'hidden';
        }

        $columnType = strtolower($columnType);

        if (strpos($columnType, 'int') !== false || strpos($columnType, 'number') !== false) {
            return 'number';
        } else if (strpos($columnType, 'date') !== false) {
            return 'date';
        } else if (strpos($columnType, 'text') !== false) {
            return 'textarea';
        } else if (strpos($columnType, 'bool') !== false) {
            return 'checkbox';
        } else {
            return 'text';
        }
    }

    /**
     * Determina el tipo de input para un campo
     */
    public static function getColumnInputType($columnType) {
        $columnType = strtolower($columnType);

        if (strpos($columnType, 'int') !== false || strpos($columnType, 'number') !== false) {
            return 'number';
        } else if (strpos($columnType, 'date') !== false) {
            return 'date';
        } else if (strpos($columnType, 'time') !== false) {
            return 'datetime-local';
        } else if (strpos($columnType, 'email') !== false) {
            return 'email';
        } else if (strpos($columnType, 'url') !== false) {
            return 'url';
        } else if (strpos($columnType, 'text') !== false) {
            return 'textarea';
        } else if (strpos($columnType, 'bool') !== false) {
            return 'checkbox';
        } else {
            return 'text';
        }
    }

    /**
     * Valida y sanitiza una consulta SQL
     */
    public static function sanitizeSQL($sql) {
        $sql = trim($sql);
        if (!preg_match('/^(SELECT|WITH|WITH\s+RECURSIVE)\s+/i', $sql)) {
            throw new Exception("Solo se permiten consultas SELECT");
        }

        $forbiddenCommands = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER', 'TRUNCATE', 'EXEC', 'CALL'];
        foreach ($forbiddenCommands as $command) {
            if (preg_match("/\b$command\b/i", $sql)) {
                throw new Exception("Comando no permitido: $command");
            }
        }

        return htmlspecialchars($sql, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Crea directorios recursivamente
     */
    public static function createDirectory($path) {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}