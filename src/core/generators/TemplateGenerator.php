<?php
// core/generators/template_generator.php

require_once __DIR__ . '/../utils/Common.php';

/**
 * Clase para generar plantillas HTML y PHP para la aplicación CRUD
 */
class TemplateGenerator {
    private $appData;

    public function __construct($appData) {
        $this->appData = $appData;
    }

    public function generateIndexTemplate() {
        $appTitle = $this->appData['appCustomization']['title'] ?? 'Mi App CRUD';
        $primaryColor = $this->appData['appCustomization']['primaryColor'] ?? '#fd7e14';
        $primaryColorDark = Utils::darkenColor($primaryColor, 20);

        // Generar menú
        $menuItems = '';

        foreach ($this->appData['selectedTables'] as $table) {
            $formattedName = Utils::formatFieldName($table);
            $menuItems .= '<a href="#" class="list-group-item list-group-item-action" data-view="table-' . $table . '">' . $formattedName . '</a>';
        }

        foreach ($this->appData['customQueries'] as $query) {
            $queryId = $query['id'] ?? uniqid('query_');
            $queryName = $query['name'] ?? 'Consulta Personalizada';
            $menuItems .= '<a href="#" class="list-group-item list-group-item-action" data-view="query-' . $queryId . '">' . htmlspecialchars($queryName) . '</a>';
        }

        if (empty($menuItems)) {
            $menuItems = '<div class="list-group-item text-muted">No hay elementos para mostrar</div>';
        }

        // Manejar logo - el archivo real se copia en AppGenerator.php a la carpeta assets/
        // Aquí solo definimos la ruta base, el archivo real se manejará en AppGenerator.php
        $logoFile = $this->appData['appCustomization']['logo'] ?? null;

        if ($logoFile) {
            // Si hay un logo, usar el archivo logo.png en la carpeta assets
            $logoHtml = '<img src="assets/logo.png" alt="Logo" class="logo-img" style="height: 30px; width: auto;">';
        } else {
            // Logo por defecto
            $logoHtml = '<i class="bi bi-database me-2"></i>';
        }

        $content = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{APP_TITLE}}</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link href="css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: {{PRIMARY_COLOR}};
        }
        .navbar {
            background-color: var(--primary-color) !important;
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }
        .navbar-brand, .navbar-nav .nav-link {
            color: white !important;
        }
        .navbar-brand:hover, .navbar-nav .nav-link:hover {
            color: rgba(255,255,255,0.8) !important;
        }
        .btn-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }
        .btn-primary:hover {
            background-color: {{PRIMARY_COLOR_DARK}} !important;
            border-color: {{PRIMARY_COLOR_DARK}} !important;
        }
        .list-group-item.active {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: white !important;
        }
        /* Estilos para el panel de usuario cuando hay autenticación */
        .user-panel {
            display: flex;
            align-items: center;
            padding: 0.1rem 0.2rem;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 0.25rem;
            font-size: 0.75rem;
            min-height: auto;
        }
        .user-panel span, .user-panel button {
            margin: 0 0.1rem;
        }
        .user-panel button {
            padding: 0.1rem 0.3rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
                {{LOGO}}
                <span class="ms-2">{{APP_TITLE}}</span>
            </a>
            <div class="d-flex align-items-center">
                <select id="languageSelector" class="form-select form-select-sm me-2" style="max-width: 120px;" onchange="changeLanguage(this.value)">
                    <option value="es" selected>Español</option>
                    <option value="en">English</option>
                    <option value="fr">Français</option>
                </select>
                <!-- Panel de usuario (solo visible si la autenticación está habilitada) -->
                <div id="userPanel" class="user-panel" style="display: none;">
                    <span id="userName">Cargando...</span>
                    <button class="btn btn-outline-light btn-sm ms-2" onclick="logout()">
                        <i class="bi bi-power"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-3">
        <div class="row">
            <div class="col-md-3">
                <div class="list-group" id="sidebar">
                    {{MENU_ITEMS}}
                </div>
            </div>
            <div class="col-md-9">
                <div id="content">
                    <div class="alert alert-info">
                        <h5>Bienvenido a {{APP_TITLE}}</h5>
                        <p class="mb-0">Selecciona una opción del menú para comenzar.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="js/jquery.dataTables.min.js"></script>
    <script src="js/dataTables.bootstrap5.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/jspdf.min.js"></script>
    <script src="js/jspdf-autotable.min.js"></script>
    <script src="js/xlsx.full.min.js"></script>
    <script src="js/app.js"></script>
    <script>
        // Verificar autenticación si está habilitada
        $(document).ready(function() {
            checkAuthentication();
        });

        function checkAuthentication() {
            // Solo verificar autenticación si está habilitada
            if (typeof authEnabled !== 'undefined' && authEnabled) {
                $.post("php/auth.php", { action: "check_auth" }, function(response) {
                    if (!response.authenticated) {
                        // No autenticado, redirigir al login
                        window.location.href = "templates/login.html";
                    } else {
                        // Mostrar panel de usuario y actualizar información
                        $("#userPanel").show();
                        $("#userName").text(response.user.name || response.user.username);
                        $("#userRole").text("Usuario: " + response.user.username);
                    }
                }).fail(function() {
                    // En caso de error, redirigir al login
                    window.location.href = "templates/login.html";
                });
            }
        }

        function logout() {
            $.post("php/auth.php", { action: "logout" }, function(response) {
                if (response.success) {
                    window.location.href = "templates/login.html";
                }
            }).fail(function() {
                window.location.href = "templates/login.html";
            });
        }
    </script>
</body>
</html>
HTML;

        $content = str_replace('{{APP_TITLE}}', htmlspecialchars($appTitle), $content);
        $content = str_replace('{{PRIMARY_COLOR}}', $primaryColor, $content);
        $content = str_replace('{{PRIMARY_COLOR_DARK}}', $primaryColorDark, $content);
        $content = str_replace('{{MENU_ITEMS}}', $menuItems, $content);
        $content = str_replace('{{LOGO}}', $logoHtml, $content);

        // Si la autenticación está habilitada, agregar script para definir la variable
        $authEnabled = $this->appData['authEnabled'] ?? false;
        if ($authEnabled) {
            $authScript = '<script>var authEnabled = true;</script>';
            $content = str_replace('</head>', $authScript . '</head>', $content);
        } else {
            $authScript = '<script>var authEnabled = false;</script>';
            $content = str_replace('</head>', $authScript . '</head>', $content);
        }

        return $content;
    }

    public function generateTablePHPTemplate($tableName) {
        $quoteChar = Utils::getQuoteCharacterForDbType($this->appData['databaseType']);

        // Determinar si hay campos relacionados
        $hasRelatedFields = false;
        $selectFields = [];
        $joins = [];

        $tableFieldConfig = null;
        if (isset($this->appData['fieldConfigurations'][$tableName])) {
            $tableFieldConfig = $this->appData['fieldConfigurations'][$tableName];
        } elseif (isset($this->appData['field_configurations'][$tableName])) {
            $tableFieldConfig = $this->appData['field_configurations'][$tableName];
        }

        if ($tableFieldConfig) {
            $tableStructure = $this->appData['databaseStructure']['tables'][$tableName] ?? [];
            $foreignKeys = $tableStructure['foreignKeys'] ?? [];

            $selectFields[] = "{$quoteChar}{$tableName}{$quoteChar}.*";

            foreach ($tableStructure['columns'] as $column) {
                $columnName = $column['name'];

                if (isset($tableFieldConfig[$columnName]['relatedField']) && !empty($tableFieldConfig[$columnName]['relatedField'])) {
                    $hasRelatedFields = true;

                    $fkInfo = null;
                    foreach ($foreignKeys as $fk) {
                        if ($fk['column'] === $columnName) {
                            $fkInfo = $fk;
                            break;
                        }
                    }

                    if ($fkInfo) {
                        $refTableAlias = $fkInfo['referenced_table'] . '_r_' . $columnName;
                        $relatedField = $tableFieldConfig[$columnName]['relatedField'];

                        $joins[] = "LEFT JOIN {$quoteChar}{$fkInfo['referenced_table']}{$quoteChar} AS {$quoteChar}{$refTableAlias}{$quoteChar} ON {$quoteChar}{$tableName}{$quoteChar}.{$quoteChar}{$columnName}{$quoteChar} = {$quoteChar}{$refTableAlias}{$quoteChar}.{$quoteChar}{$fkInfo['referenced_column']}{$quoteChar}";

                        $selectFields[] = "{$quoteChar}{$refTableAlias}{$quoteChar}.{$quoteChar}{$relatedField}{$quoteChar} AS {$quoteChar}{$columnName}_related_display{$quoteChar}";
                    }
                }
            }
        } else {
            $selectFields[] = "*";
        }

        // Para la aplicación generada, usamos consultas simples sin lógica compleja de metadatos
        // ya que no tenemos acceso a la configuración original en tiempo de ejecución

        // Determinar si la autenticación está habilitada
        $authEnabled = $this->appData['authEnabled'] ?? false;

        $authInclude = '';
        $authHeaderCode = '';
        if ($authEnabled) {
            $authInclude = '
require_once \'../config/auth.php\';';
            $authHeaderCode = '
        // Verificar autenticación si está habilitada
        if (!$this->isAuthenticated()) {
            http_response_code(401);
            echo json_encode([\'success\' => false, \'error\' => \'No autenticado\']);
            exit;
        }';
        }

        return <<<PHP
<?php
require_once '../config/Database.php';
{$authInclude}

class {$tableName}Controller {
    private \$db;
    private \$table = '{$tableName}';
    private \$config;

    public function __construct() {
        \$this->db = new Database();
        \$this->config = require __DIR__ . '/../config/config.php';
    }

    private function isAuthenticated() {
        if (!isset(\$_SESSION)) {
            session_start();
        }
        return isset(\$_SESSION['authenticated']) && \$_SESSION['authenticated'] === true;
    }

    public function read() {
        {$authHeaderCode}
        // Verificar si hay configuraciones de campos relacionados
        \$fieldConfigurations = \$this->config['field_configurations'][\$this->table] ?? [];

        // Determinar el carácter de comillas según el tipo de base de datos
        \$dbType = \$this->config['database']['type'];
        \$quoteChar = \$this->getQuoteCharacterForDbType(\$dbType);

        // Consulta base
        \$selectFields = ["{\$quoteChar}{\$this->table}{\$quoteChar}.*"];
        \$joins = [];

        // Verificar configuraciones de campos
        \$tableStructure = \$this->config['database_structure']['tables'][\$this->table] ?? [];
        \$foreignKeys = \$tableStructure['foreignKeys'] ?? [];

        foreach (\$tableStructure['columns'] as \$column) {
            \$columnName = \$column['name'];

            // Verificar si este campo tiene una configuración de campo relacionado
            if (isset(\$fieldConfigurations[\$columnName]['relatedField']) && !empty(\$fieldConfigurations[\$columnName]['relatedField'])) {
                \$fkInfo = null;
                foreach (\$foreignKeys as \$fk) {
                    if (\$fk['column'] === \$columnName) {
                        \$fkInfo = \$fk;
                        break;
                    }
                }

                if (\$fkInfo) {
                    // Crear alias para la tabla referenciada
                    \$refTableAlias = \$fkInfo['referenced_table'] . '_r_' . \$columnName;
                    \$relatedField = \$fieldConfigurations[\$columnName]['relatedField'];

                    // Agregar JOIN
                    \$joins[] = "LEFT JOIN {\$quoteChar}{\$fkInfo['referenced_table']}{\$quoteChar} AS {\$quoteChar}{\$refTableAlias}{\$quoteChar} ON {\$quoteChar}{\$this->table}{\$quoteChar}.{\$quoteChar}{\$columnName}{\$quoteChar} = {\$quoteChar}{\$refTableAlias}{\$quoteChar}.{\$quoteChar}{\$fkInfo['referenced_column']}{\$quoteChar}";

                    // Agregar campo específico de la tabla relacionada al SELECT con alias
                    \$selectFields[] = "{\$quoteChar}{\$refTableAlias}{\$quoteChar}.{\$quoteChar}{\$relatedField}{\$quoteChar} AS {\$quoteChar}{\$columnName}_related_display{\$quoteChar}";
                }
            }
        }

        \$selectClause = implode(", ", \$selectFields);
        \$joinClause = !empty(\$joins) ? " " . implode(" ", \$joins) : "";

        \$query = "SELECT " . \$selectClause . " FROM " . \$quoteChar . \$this->table . \$quoteChar . \$joinClause . " ORDER BY " . \$quoteChar . \$this->table . \$quoteChar . ".id";

        return \$this->db->query(\$query);
    }

    private function getQuoteCharacterForDbType(\$dbType) {
        switch (\$dbType) {
            case 'sqlite': return '"';
            case 'mysql': return '`';
            case 'postgresql': return '"';
            case 'sqlserver': return '[';
            default: return '`';
        }
    }

    public function create(\$data) {
        {$authHeaderCode}
        // Filtrar valores vacíos y convertir a NULL si es necesario para claves foráneas
        \$filteredData = [];
        foreach (\$data as \$key => \$value) {
            if (\$value === '') {
                // Si es una cadena vacía, usar NULL para campos numéricos
                \$filteredData[\$key] = null;
            } else {
                \$filteredData[\$key] = \$value;
            }
        }

        \$columns = implode(', ', array_keys(\$filteredData));
        \$values = ':' . implode(', :', array_keys(\$filteredData));
        \$query = "INSERT INTO {\$this->table} (\$columns) VALUES (\$values)";

        return \$this->db->execute(\$query, \$filteredData);
    }

    public function update(\$id, \$data) {
        {$authHeaderCode}
        // Filtrar valores vacíos y convertir a NULL si es necesario para claves foráneas
        \$filteredData = [];
        foreach (\$data as \$key => \$value) {
            if (\$value === '') {
                // Si es una cadena vacía, usar NULL para campos numéricos
                \$filteredData[\$key] = null;
            } else {
                \$filteredData[\$key] = \$value;
            }
        }

        \$setClause = [];
        foreach (\$filteredData as \$key => \$value) {
            \$setClause[] = "\$key = :\$key";
        }
        \$setClause = implode(', ', \$setClause);

        \$query = "UPDATE {\$this->table} SET \$setClause WHERE id = :id";
        \$filteredData['id'] = \$id;

        return \$this->db->execute(\$query, \$filteredData);
    }

    public function delete(\$id) {
        {$authHeaderCode}
        \$query = "DELETE FROM {\$this->table} WHERE id = :id";
        return \$this->db->execute(\$query, ['id' => \$id]);
    }

    // Método para obtener opciones de tabla relacionada (para selects de FK)
    public function getForeignKeyOptions(\$foreignTable, \$displayField = null) {
        // Determinar qué campo mostrar
        \$displayColumn = \$displayField ? \$displayField : 'id';

        // Validar nombre de tabla y columna para prevenir inyección de SQL
        // Permitir letras, números y guiones bajos
        if (!preg_match('/^[a-zA-Z0-9_]+\$/u', \$foreignTable) || !preg_match('/^[a-zA-Z0-9_]+\$/u', \$displayColumn)) {
            error_log("Nombre de tabla o columna no válido: foreignTable='{$foreignTable}', displayColumn='{$displayColumn}'");
            return [];
        }

        try {
            // Usar el carácter de comillas apropiado según el tipo de base de datos
            \$dbType = \$this->config['database']['type'];
            \$quoteChar = \$this->getQuoteCharacterForDbType(\$dbType);

            // Construir la consulta SQL de forma segura
            \$query = "SELECT id, {\$quoteChar}{\$displayColumn}{\$quoteChar} AS display_value FROM {\$quoteChar}{\$foreignTable}{\$quoteChar} ORDER BY {\$quoteChar}{\$displayColumn}{\$quoteChar}";
            return \$this->db->query(\$query);
        } catch (Exception \$e) {
            error_log("Error en getForeignKeyOptions: " . \$e->getMessage());
            return [];
        }
    }

    public function find(\$id) {
        {$authHeaderCode}
        // Verificar si hay configuraciones de campos relacionados
        \$fieldConfigurations = \$this->config['field_configurations'][\$this->table] ?? [];

        // Determinar el carácter de comillas según el tipo de base de datos
        \$dbType = \$this->config['database']['type'];
        \$quoteChar = \$this->getQuoteCharacterForDbType(\$dbType);

        // Consulta base
        \$selectFields = ["{\$quoteChar}{\$this->table}{\$quoteChar}.*"];
        \$joins = [];

        // Verificar configuraciones de campos
        \$tableStructure = \$this->config['database_structure']['tables'][\$this->table] ?? [];
        \$foreignKeys = \$tableStructure['foreignKeys'] ?? [];

        foreach (\$tableStructure['columns'] as \$column) {
            \$columnName = \$column['name'];

            // Verificar si este campo tiene una configuración de campo relacionado
            if (isset(\$fieldConfigurations[\$columnName]['relatedField']) && !empty(\$fieldConfigurations[\$columnName]['relatedField'])) {
                \$fkInfo = null;
                foreach (\$foreignKeys as \$fk) {
                    if (\$fk['column'] === \$columnName) {
                        \$fkInfo = \$fk;
                        break;
                    }
                }

                if (\$fkInfo) {
                    // Crear alias para la tabla referenciada
                    \$refTableAlias = \$fkInfo['referenced_table'] . '_r_' . \$columnName;
                    \$relatedField = \$fieldConfigurations[\$columnName]['relatedField'];

                    // Agregar JOIN
                    \$joins[] = "LEFT JOIN {\$quoteChar}{\$fkInfo['referenced_table']}{\$quoteChar} AS {\$quoteChar}{\$refTableAlias}{\$quoteChar} ON {\$quoteChar}{\$this->table}{\$quoteChar}.{\$quoteChar}{\$columnName}{\$quoteChar} = {\$quoteChar}{\$refTableAlias}{\$quoteChar}.{\$quoteChar}{\$fkInfo['referenced_column']}{\$quoteChar}";

                    // Agregar campo específico de la tabla relacionada al SELECT con alias
                    \$selectFields[] = "{\$quoteChar}{\$refTableAlias}{\$quoteChar}.{\$quoteChar}{\$relatedField}{\$quoteChar} AS {\$quoteChar}{\$columnName}_related_display{\$quoteChar}";
                }
            }
        }

        \$selectClause = implode(", ", \$selectFields);
        \$joinClause = !empty(\$joins) ? " " . implode(" ", \$joins) : "";

        \$query = "SELECT " . \$selectClause . " FROM " . \$quoteChar . \$this->table . \$quoteChar . \$joinClause . " WHERE " . \$quoteChar . \$this->table . \$quoteChar . ".id = :id";

        \$result = \$this->db->query(\$query, ['id' => \$id]);
        return \$result[0] ?? null;
    }
}

// Manejo de solicitudes AJAX
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    \$action = \$_POST['action'] ?? '';
    \$controller = new {$tableName}Controller();

    switch (\$action) {
        case 'read':
            \$data = \$controller->read();
            echo json_encode(['success' => true, 'data' => \$data]);
            break;

        case 'create':
            \$data = \$_POST;
            unset(\$data['action']);
            \$result = \$controller->create(\$data);
            echo json_encode(['success' => \$result]);
            break;

        case 'update':
            \$id = \$_POST['id'];
            \$data = \$_POST;
            unset(\$data['action'], \$data['id']);
            \$result = \$controller->update(\$id, \$data);
            echo json_encode(['success' => \$result]);
            break;

        case 'delete':
            \$id = \$_POST['id'];
            \$result = \$controller->delete(\$id);
            echo json_encode(['success' => \$result]);
            break;

        case 'get_foreign_key_options':
            // Caso especial para obtener opciones de tabla relacionada
            \$foreignTable = \$_POST['foreign_table'] ?? '';
            \$displayField = \$_POST['display_field'] ?? null;

            \$options = \$controller->getForeignKeyOptions(\$foreignTable, \$displayField);
            echo json_encode(['success' => true, 'data' => \$options]);
            break;

        case 'find':
            \$id = \$_POST['id'] ?? null;
            if (\$id === null || !is_numeric(\$id)) {
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                break;
            }
            \$result = \$controller->find(\$id);
            if (\$result) {
                echo json_encode(['success' => true, 'data' => \$result]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Registro no encontrado']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
}
?>
PHP;
    }

    public function generateQueryPHPTemplate($query) {
        $queryName = $query['name'];
        $queryId = $query['id'] ?? uniqid('query_');
        $sql = Utils::sanitizeSQL($query['sql']); // Sanitizar la consulta SQL

        // Determinar si la autenticación está habilitada
        $authEnabled = $this->appData['authEnabled'] ?? false;

        $authInclude = '';
        $authHeaderCode = '';
        if ($authEnabled) {
            $authInclude = '
require_once \'../config/auth.php\';';
            $authHeaderCode = '
        // Verificar autenticación si está habilitada
        if (!$this->isAuthenticated()) {
            http_response_code(401);
            echo json_encode([\'success\' => false, \'error\' => \'No autenticado\']);
            exit;
        }';
        }

        return <<<PHP
<?php
require_once '../config/Database.php';
{$authInclude}

class Query{$queryId}Controller {
    private \$db;
    private \$config;

    public function __construct() {
        \$this->db = new Database();
        \$this->config = require __DIR__ . '/../config/config.php';
    }
{$authHeaderCode}

    private function isAuthenticated() {
        if (!isset(\$_SESSION)) {
            session_start();
        }
        return isset(\$_SESSION['authenticated']) && \$_SESSION['authenticated'] === true;
    }

    public function execute() {
        {$authHeaderCode}
        try {
            \$sql = "{$sql}";
            \$result = \$this->db->query(\$sql);
            return ['success' => true, 'data' => \$result];
        } catch (Exception \$e) {
            return ['success' => false, 'error' => \$e->getMessage()];
        }
    }
}

// Manejo de solicitudes AJAX
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    \$action = \$_POST['action'] ?? '';
    \$controller = new Query{$queryId}Controller();

    switch (\$action) {
        case 'read':
            \$result = \$controller->execute();
            echo json_encode(\$result);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
    exit();
}
?>
PHP;
    }

    public function generateTableHTMLTemplate($tableName) {
        // Obtener columnas de la tabla
        $columns = $this->appData['databaseStructure']['tables'][$tableName]['columns'] ?? [];

        // Generar encabezados de tabla
        $headerHtml = '';
        $columnDefs = [];

        foreach ($columns as $column) {
            $columnName = $column['name'];
            $label = Utils::formatFieldName($columnName); // Valor por defecto

            // Verificar si hay configuración de campo personalizada
            if (isset($this->appData['fieldConfigurations'][$tableName]) &&
                isset($this->appData['fieldConfigurations'][$tableName][$columnName])) {

                $fieldConfig = $this->appData['fieldConfigurations'][$tableName][$columnName];

                if (isset($fieldConfig['label']) && !empty($fieldConfig['label'])) {
                    $label = $fieldConfig['label'];
                } elseif (isset($fieldConfig['text']) && !empty($fieldConfig['text'])) {
                    $label = $fieldConfig['text'];
                } else {
                    $label = Utils::formatFieldName($columnName);
                }
            } else {
                $label = Utils::formatFieldName($columnName);
            }

            $headerHtml .= "<th>" . htmlspecialchars($label) . "</th>";

            // Verificar si hay un campo relacionado configurado basado en la configuración
            $dataField = $columnName;

            $hasRelatedField = false;
            $tableFieldConfig = $this->appData['fieldConfigurations'][$tableName] ?? [];

            if (isset($tableFieldConfig[$columnName]['relatedField']) &&
                !empty($tableFieldConfig[$columnName]['relatedField'])) {
                $hasRelatedField = true;
            }

            if ($hasRelatedField) {
                // Si hay un campo relacionado configurado, usamos el campo especial generado en el query
                $dataField = $columnName . '_related_display';
            }

            $columnDefs[] = "{ data: '" . addslashes($dataField) . "' }";
        }

        // Agregar columna de acciones
        $headerHtml .= "<th>Acciones</th>";

        // Generar definiciones de columnas para DataTables
        $dataColumnDefs = implode(",\n                ", $columnDefs);
        $dataColumnDefs .= ",\n                {
                    data: null,
                    render: function(data, type, row) {
                        return '<button class=\"btn btn-sm btn-outline-warning me-1\" onclick=\"edit{$tableName}(' + row.id + ')\">' +
                                   '<i class=\"bi bi-pencil-square\"></i>' +
                               '</button>' +
                               '<button class=\"btn btn-sm btn-outline-danger\" onclick=\"delete{$tableName}(' + row.id + ')\">' +
                                   '<i class=\"bi bi-trash3\"></i>' +
                               '</button>';
                    }
                }";

        // Escapar el nombre de la tabla para uso seguro en JavaScript
        $escapedTableName = Utils::escapeForJavaScript($tableName);

        return <<<HTML
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Gestión de {$tableName}</h5>
        <div class="btn-group" role="group">
            <button class="btn btn-success btn-sm dropdown-toggle" type="button" id="exportBtn{$escapedTableName}" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-cloud-download"></i> Exportar
            </button>
            <ul class="dropdown-menu" aria-labelledby="exportBtn{$escapedTableName}">
                <li><a class="dropdown-item" href="#" onclick="export{$escapedTableName}ToPDF(false); return false;"><i class="bi bi-file-pdf"></i> Exportar Todos a PDF</a></li>
                <li><a class="dropdown-item" href="#" onclick="export{$escapedTableName}ToPDF(true); return false;"><i class="bi bi-file-pdf"></i> Exportar Seleccionados a PDF</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="export{$escapedTableName}ToExcel(false); return false;"><i class="bi bi-file-spreadsheet"></i> Exportar Todos a Excel</a></li>
                <li><a class="dropdown-item" href="#" onclick="export{$escapedTableName}ToExcel(true); return false;"><i class="bi bi-file-spreadsheet"></i> Exportar Seleccionados a Excel</a></li>
            </ul>
            <button class="btn btn-primary btn-sm ms-2" onclick="showCreateModal('{$escapedTableName}')">
                <i class="bi bi-plus-lg"></i> Nuevo
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <button class="btn btn-sm btn-outline-secondary" onclick="toggleSelectAll('{$escapedTableName}')">
                <input type="checkbox" id="selectAll{$escapedTableName}" onchange="toggleSelectAll('{$escapedTableName}')" /> Seleccionar Todos
            </button>
        </div>
        <table id="table-{$escapedTableName}" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAllHeader{$escapedTableName}" onchange="toggleSelectAllRows('{$escapedTableName}')" /></th>
                    {$headerHtml}
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div class="card-footer text-muted text-center small">
        {{COMPANY_INFO_FOOTER}}
    </div>
</div>

<!-- Modal para crear/editar -->
<div class="modal fade" id="modal-{$escapedTableName}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle-{$escapedTableName}">Nuevo Registro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-{$escapedTableName}">
                    <input type="hidden" id="editId-{$escapedTableName}" name="id">
                    <div id="formFields-{$escapedTableName}">
                        {$this->generateFormFields($tableName)}
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="save{$escapedTableName}()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
function load{$escapedTableName}Table() {
    \$('#table-{$escapedTableName}').DataTable({
        ajax: {
            url: 'php/{$escapedTableName}.php',
            type: 'POST',
            data: { action: 'read' },
            dataSrc: function(json) {
                return json.data || [];
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    return '<input type="checkbox" class="row-checkbox" data-id="' + row.id + '" onchange="updateSelectAllState(\'' + '{$escapedTableName}' + '\')" />';
                }
            },
            {$dataColumnDefs}
        ],
        language: {
            url: getLanguageFile()
        }
    });
}

function showCreateModal() {
    \$('#editId-{$escapedTableName}').val('');
    \$('#modalTitle-{$escapedTableName}').text('Nuevo Registro');
    \$('#form-{$escapedTableName}')[0].reset();
    \$('#modal-{$escapedTableName}').modal('show');
}

function edit{$escapedTableName}(id) {
    \$.post('php/{$escapedTableName}.php', { action: 'find', id: id }, function(response) {
        if (response.success && response.data) {
            \$('#editId-{$escapedTableName}').val(response.data.id);
            \$('#modalTitle-{$escapedTableName}').text('Editar Registro #' + response.data.id);

            // Almacenar los valores relacionados para usarlos después de cargar las opciones
            let relatedValues = {};

            for (let key in response.data) {
                if (response.data.hasOwnProperty(key) && key !== 'id') {
                    if (!key.includes('_related_display')) {
                        let field = \$("#" + key + "-{$escapedTableName}");
                        if (field.length > 0) {
                            if (field.is('select')) {
                                // Guardar el valor relacionado para seleccionarlo después
                                relatedValues[key] = response.data[key];

                                // Verificar si el select ya tiene opciones
                                if (field.find('option').length > 1) { // > 1 porque hay al menos la opción "Cargando..."
                                    // Si ya tiene opciones, seleccionar directamente
                                    field.val(response.data[key]);
                                    field.trigger('change');
                                }
                                // Si no tiene opciones, se seleccionará después de que se carguen
                            } else {
                                field.val(response.data[key]);
                            }
                        }
                    }
                }
            }

            // Mostrar el modal
            \$('#modal-{$escapedTableName}').modal('show');

            // Esperar un momento para asegurar que los selects se hayan inicializado y cargar valores relacionados
            setTimeout(function() {
                for (let key in relatedValues) {
                    let fieldId = key + '-{$escapedTableName}';
                    let field = \$("#" + fieldId);
                    if (field.length > 0 && field.is('select')) {
                        // Recargar las opciones y luego seleccionar el valor
                        let foreignTable = field.data('foreign-table');
                        let relatedField = field.data('related-field');
                        let refColumn = field.data('referenced-column') || 'id';

                        if (foreignTable) {
                            // Llamar a loadForeignKeyOptions con el valor a seleccionar
                            loadForeignKeyOptions(fieldId, foreignTable, relatedField, refColumn, relatedValues[key]);
                        } else {
                            // Si no tiene datos de tabla foránea, intentar seleccionar directamente
                            field.val(relatedValues[key]);
                            field.trigger('change');
                        }
                    } else {
                        // Si el campo no existe, intentar encontrarlo con otras variantes del nombre
                        // Esto puede suceder si hay discrepancias en los nombres de tablas
                        let possibleSelectors = [
                            "#" + key + "-{$escapedTableName}",
                            "#" + key + "-" + key.split('_').slice(0, -1).join('_'), // Eliminar última parte si es FK
                            "#" + key
                        ];

                        for (let selector of possibleSelectors) {
                            let possibleField = \$(selector);
                            if (possibleField.length > 0 && possibleField.is('select')) {
                                // Encontramos un campo similar, usarlo
                                let foreignTable = possibleField.data('foreign-table');
                                let relatedField = possibleField.data('related-field');
                                let refColumn = possibleField.data('referenced-column') || 'id';

                                if (foreignTable) {
                                    loadForeignKeyOptions(selector.substring(1), foreignTable, relatedField, refColumn, relatedValues[key]);
                                } else {
                                    // Si no tiene datos de tabla foránea, intentar seleccionar directamente
                                    possibleField.val(relatedValues[key]);
                                    possibleField.trigger('change');
                                }
                                break;
                            }
                        }
                    }
                }
            }, 100);
        } else {
            showAlert('Error al cargar los datos para edición: ' + (response.error || 'Error desconocido'), 'danger');
        }
    }).fail(function(xhr, status, error) {
        showAlert('Error de conexión: ' + error, 'danger');
    });
}

function delete{$escapedTableName}(id) {
    if (confirm('¿Estás seguro de eliminar este registro?')) {
        \$.post('php/{$escapedTableName}.php', { action: 'delete', id: id }, function(response) {
            if (response.success) {
                showAlert('Registro eliminado correctamente', 'success');
                \$('#table-{$escapedTableName}').DataTable().ajax.reload();
            } else {
                showAlert('Error al eliminar el registro: ' + (response.error || 'Error desconocido'), 'danger');
            }
        }).fail(function(xhr, status, error) {
            showAlert('Error de conexión: ' + error, 'danger');
        });
    }
}

function save{$escapedTableName}() {
    const formData = new FormData(document.getElementById('form-{$escapedTableName}'));
    formData.append('action', \$('#editId-{$escapedTableName}').val() ? 'update' : 'create');

    \$.ajax({
        url: 'php/{$escapedTableName}.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                showAlert('Registro guardado correctamente', 'success');
                \$('#modal-{$escapedTableName}').modal('hide');
                \$('#table-{$escapedTableName}').DataTable().ajax.reload();
            } else {
                showAlert('Error al guardar el registro: ' + (response.error || 'Error desconocido'), 'danger');
            }
        },
        error: function(xhr, status, error) {
            showAlert('Error de conexión: ' + error, 'danger');
        }
    });
}

// Funciones de exportación
function export{$escapedTableName}ToPDF(selectedOnly = false) {
    showAlert('Generando PDF...', 'info');

    const table = \$('#table-{$escapedTableName}').DataTable();
    const settings = table.settings()[0];
    const ajaxUrl = settings.ajax ? (typeof settings.ajax === 'string' ? settings.ajax : settings.ajax.url) : null;

    let dataPromise;
    if (selectedOnly) {
        const selectedIds = getSelectedIds('{$escapedTableName}');
        if (selectedIds.length === 0) {
            showAlert('Por favor, selecciona al menos un registro para exportar', 'warning');
            return;
        }

        dataPromise = new Promise((resolve, reject) => {
            if (ajaxUrl) {
                \$.post(ajaxUrl, { action: 'read' }, function(response) {
                    if (response.success && response.data) {
                        const filteredData = response.data.filter(row => selectedIds.includes(row.id));
                        resolve({success: true, data: filteredData});
                    } else {
                        reject(response.error || 'Error al obtener datos');
                    }
                }).fail(reject);
            } else {
                \$.post('php/{$escapedTableName}.php', { action: 'read' }, function(response) {
                    if (response.success && response.data) {
                        const filteredData = response.data.filter(row => selectedIds.includes(row.id));
                        resolve({success: true, data: filteredData});
                    } else {
                        reject(response.error || 'Error al obtener datos');
                    }
                }).fail(reject);
            }
        });
    } else {
        dataPromise = new Promise((resolve, reject) => {
            if (ajaxUrl) {
                \$.post(ajaxUrl, { action: 'read' }, resolve).fail(reject);
            } else {
                \$.post('php/{$escapedTableName}.php', { action: 'read' }, resolve).fail(reject);
            }
        });
    }

    dataPromise.then(function(response) {
        processDataAndExportToPDF(response, 'Reporte de {$tableName}', '{$escapedTableName}_reporte.pdf');
    }).catch(function(error) {
        console.error('Error al obtener datos para exportar a PDF:', error);
        showAlert('Error de conexión al obtener datos para exportar a PDF: ' + error, 'danger');
    });
}

function export{$escapedTableName}ToExcel(selectedOnly = false) {
    showAlert('Generando Excel...', 'info');

    const table = \$('#table-{$escapedTableName}').DataTable();
    const settings = table.settings()[0];
    const ajaxUrl = settings.ajax ? (typeof settings.ajax === 'string' ? settings.ajax : settings.ajax.url) : null;

    let dataPromise;
    if (selectedOnly) {
        const selectedIds = getSelectedIds('{$escapedTableName}');
        if (selectedIds.length === 0) {
            showAlert('Por favor, selecciona al menos un registro para exportar', 'warning');
            return;
        }

        dataPromise = new Promise((resolve, reject) => {
            if (ajaxUrl) {
                \$.post(ajaxUrl, { action: 'read' }, function(response) {
                    if (response.success && response.data) {
                        const filteredData = response.data.filter(row => selectedIds.includes(row.id));
                        resolve({success: true, data: filteredData});
                    } else {
                        reject(response.error || 'Error al obtener datos');
                    }
                }).fail(reject);
            } else {
                \$.post('php/{$escapedTableName}.php', { action: 'read' }, function(response) {
                    if (response.success && response.data) {
                        const filteredData = response.data.filter(row => selectedIds.includes(row.id));
                        resolve({success: true, data: filteredData});
                    } else {
                        reject(response.error || 'Error al obtener datos');
                    }
                }).fail(reject);
            }
        });
    } else {
        dataPromise = new Promise((resolve, reject) => {
            if (ajaxUrl) {
                \$.post(ajaxUrl, { action: 'read' }, resolve).fail(reject);
            } else {
                \$.post('php/{$escapedTableName}.php', { action: 'read' }, resolve).fail(reject);
            }
        });
    }

    dataPromise.then(function(response) {
        processDataAndExportToExcel(response, '{$escapedTableName}_reporte.xlsx');
    }).catch(function(error) {
        console.error('Error al obtener datos para exportar a Excel:', error);
        showAlert('Error de conexión al obtener datos para exportar a Excel: ' + error, 'danger');
    });
}

// Función para obtener IDs de registros seleccionados
function getSelectedIds(tableName) {
    const selectedCheckboxes = document.querySelectorAll('#table-' + tableName + ' .row-checkbox:checked');
    return Array.from(selectedCheckboxes).map(cb => parseInt(cb.dataset.id));
}

// Función para alternar selección de todos
function toggleSelectAll(tableName) {
    const selectAllCheckbox = document.getElementById('selectAll' + tableName);
    const checkboxes = document.querySelectorAll('#table-' + tableName + ' .row-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

// Función para alternar selección de filas
function toggleSelectAllRows(tableName) {
    const selectAllCheckbox = document.getElementById('selectAllHeader' + tableName);
    const checkboxes = document.querySelectorAll('#table-' + tableName + ' .row-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

// Función para actualizar el estado de selección general
function updateSelectAllState(tableName) {
    const checkboxes = document.querySelectorAll('#table-' + tableName + ' .row-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll' + tableName);
    const selectAllHeaderCheckbox = document.getElementById('selectAllHeader' + tableName);

    const checkedCount = document.querySelectorAll('#table-' + tableName + ' .row-checkbox:checked').length;
    const totalCount = checkboxes.length;

    selectAllCheckbox.checked = checkedCount === totalCount && totalCount > 0;
    selectAllHeaderCheckbox.checked = checkedCount === totalCount && totalCount > 0;
}

// Inicializar cuando el template se cargue
setTimeout(function() {
    load{$escapedTableName}Table();
}, 100);
</script>
HTML;

        // Reemplazar el marcador de información de empresa con la información real
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

        $content = str_replace('{{COMPANY_INFO_FOOTER}}', $companyFooter, $content);

        return $content;
    }

    private function generateFormFields($tableName) {
        $html = '';

        // Obtener la estructura de la tabla
        $tableStructure = $this->appData['databaseStructure']['tables'][$tableName] ?? [];
        $tableColumns = $tableStructure['columns'] ?? [];
        $tableForeignKeys = $tableStructure['foreignKeys'] ?? [];

        // Obtener configuraciones de campos
        $fieldConfigurations = $this->appData['fieldConfigurations'][$tableName] ?? [];

        // Procesar cada columna de la tabla
        foreach ($tableColumns as $column) {
            $columnName = $column['name'];
            $columnType = $column['type'];
            $isPrimaryKey = $column['primaryKey'] ?? false;
            $nullable = $column['nullable'] ?? false;

            // Saltar columnas que sean clave primaria si no es para edición
            if ($isPrimaryKey) {
                continue;
            }

            // Obtener configuración para este campo
            $fieldConfig = $fieldConfigurations[$columnName] ?? [];
            $controlType = $fieldConfig['controlType'] ?? Utils::getDefaultControlTypeForColumn($columnType, $isPrimaryKey);
            $label = $fieldConfig['label'] ?? Utils::formatFieldName($columnName);
            $isRequired = ($fieldConfig['required'] ?? (!$nullable && $columnName !== 'id')) ? 'required' : '';

            // Verificar si es una clave foránea
            $isForeignKey = false;
            foreach ($tableForeignKeys as $fk) {
                if ($fk['column'] === $columnName) {
                    $isForeignKey = true;
                    break;
                }
            }

            // Generar HTML para el campo
            $fieldHtml = '<div class="mb-3">';

            if ($isForeignKey) {
                // Para campos de clave foránea, generar un select que se llenará dinámicamente
                $foreignTable = null;
                $referencedColumn = null;
                foreach ($tableForeignKeys as $fk) {
                    if ($fk['column'] === $columnName) {
                        $foreignTable = $fk['referenced_table'];
                        $referencedColumn = $fk['referenced_column'];
                        break;
                    }
                }

                if ($foreignTable) {
                    $fieldHtml .= "<label for='{$columnName}-{$tableName}' class='form-label'>{$label}" . ($isRequired ? ' *' : '') . "</label>";
                    $fieldHtml .= "<select class='form-control' id='{$columnName}-{$tableName}' name='{$columnName}' {$isRequired} data-foreign-table='{$foreignTable}' data-related-field='" . ($fieldConfig['relatedField'] ?? '') . "'>";
                    $fieldHtml .= "<option value=''>Cargando...</option>";
                    $fieldHtml .= "</select>";

                    // Agregar script para cargar opciones dinámicamente
                    $fieldHtml .= "<script>
                        // Cargar opciones para el select de {$columnName}
                        document.addEventListener('DOMContentLoaded', function() {
                            // Solo cargar opciones si no hay un valor previamente seleccionado
                            var selectElement = document.getElementById('{$columnName}-{$tableName}');
                            if (selectElement && selectElement.value === '') {
                                loadForeignKeyOptions('{$columnName}-{$tableName}', '{$foreignTable}', '" . ($fieldConfig['relatedField'] ?? '') . "', '{$referencedColumn}');
                            }
                        });

                        // Recargar cuando se abra el modal de creación/edición
                        document.addEventListener('shown.bs.modal', function() {
                            // Solo recargar si no hay un valor ya seleccionado (por ejemplo, al editar)
                            var selectElement = document.getElementById('{$columnName}-{$tableName}');
                            if (selectElement && selectElement.value === '') {
                                loadForeignKeyOptions('{$columnName}-{$tableName}', '{$foreignTable}', '" . ($fieldConfig['relatedField'] ?? '') . "', '{$referencedColumn}');
                            }
                        });
                    </script>";
                }
            } else {
                // Para campos normales, generar el control según el tipo
                $inputType = Utils::getColumnInputType($columnType);
                $fieldHtml .= "<label for='{$columnName}-{$tableName}' class='form-label'>{$label}" . ($isRequired ? ' *' : '') . "</label>";

                if ($inputType === 'textarea') {
                    $fieldHtml .= "<textarea class='form-control' id='{$columnName}-{$tableName}' name='{$columnName}' {$isRequired}></textarea>";
                } else if ($inputType === 'select') {
                    // Para tipos de datos enum o similares, podríamos generar opciones específicas
                    $fieldHtml .= "<select class='form-control' id='{$columnName}-{$tableName}' name='{$columnName}' {$isRequired}>";
                    $fieldHtml .= "<option value=''>Seleccione...</option>";
                    $fieldHtml .= "</select>";
                } else {
                    $fieldHtml .= "<input type='{$inputType}' class='form-control' id='{$columnName}-{$tableName}' name='{$columnName}' {$isRequired}>";
                }
            }

            $fieldHtml .= '</div>';

            $html .= $fieldHtml;
        }

        return $html;
    }

    public function generateQueryHTMLTemplate($query) {
        $queryName = $query['name'];
        $queryId = $query['id'] ?? uniqid('query_');
        $type = $query['type'];

        // Escapar el nombre de la consulta y el ID para uso seguro en JavaScript
        $escapedQueryName = Utils::escapeForJavaScript($queryName);
        $escapedQueryId = Utils::escapeForJavaScript($queryId);

        return <<<HTML
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">{$queryName}</h5>
        <div class="btn-group" role="group">
            <button class="btn btn-success btn-sm dropdown-toggle" type="button" id="exportBtnQuery{$escapedQueryId}" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-cloud-download"></i> Exportar
            </button>
            <ul class="dropdown-menu" aria-labelledby="exportBtnQuery{$escapedQueryId}">
                <li><a class="dropdown-item" href="#" onclick="exportQuery{$escapedQueryId}ToPDF(); return false;"><i class="bi bi-file-pdf"></i> Exportar a PDF</a></li>
                <li><a class="dropdown-item" href="#" onclick="exportQuery{$escapedQueryId}ToExcel(); return false;"><i class="bi bi-file-spreadsheet"></i> Exportar a Excel</a></li>
            </ul>
        </div>
    </div>
    <div class="card-body">
        <table id="table-query-{$escapedQueryId}" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <!-- Columnas se llenarán dinámicamente -->
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div class="card-footer text-muted text-center small">
        {{COMPANY_INFO_FOOTER}}
    </div>
</div>

<script>
function loadQuery{$escapedQueryId}Table() {
    // Primero obtener datos para determinar columnas
    \$.ajax({
        url: 'php/query_{$escapedQueryId}.php',
        type: 'POST',
        data: { action: 'read' },
        success: function(response) {
            if (response.success && response.data && response.data.length > 0) {
                // Obtener nombres de columnas dinámicamente del primer registro
                const firstRow = response.data[0];
                const columns = Object.keys(firstRow).map(function(key) {
                    return {
                        data: key,
                        title: key.charAt(0).toUpperCase() + key.slice(1)
                    };
                });

                // Agregar estas columnas al encabezado de la tabla
                const headerRow = \$('#table-query-{$escapedQueryId} thead tr');
                headerRow.empty();
                columns.forEach(function(col) {
                    headerRow.append('<th>' + col.title + '</th>');
                });
                headerRow.append('<th>Acciones</th>');

                // Ahora inicializar DataTable con las columnas correctas
                \$('#table-query-{$escapedQueryId}').DataTable({
                    ajax: {
                        url: 'php/query_{$escapedQueryId}.php',
                        type: 'POST',
                        data: { action: 'read' },
                        dataSrc: function(json) {
                            return json.data || [];
                        }
                    },
                    columns: [
                        ...columns.map(function(col) {
                            return { data: col.data };
                        }),
                        {
                            data: null,
                            orderable: false,
                            render: function(data, type, row) {
                                return '<span class="text-muted">N/A</span>';
                            }
                        }
                    ],
                    language: {
                        url: getLanguageFile()
                    }
                });
            } else {
                showAlert('Error al cargar datos de consulta: ' + (response.error || 'No hay datos'), 'danger');
            }
        },
        error: function(xhr, status, error) {
            showAlert('Error de conexión al cargar consulta: ' + error, 'danger');
        }
    });
}

// Funciones de exportación para consultas
function exportQuery{$escapedQueryId}ToPDF() {
    showAlert('Generando PDF...', 'info');

    \$.ajax({
        url: 'php/query_{$escapedQueryId}.php',
        type: 'POST',
        data: { action: 'read' },
        success: function(response) {
            processDataAndExportToPDFQuery(response, 'Reporte de {$escapedQueryName}', 'query_{$escapedQueryId}_reporte.pdf');
        },
        error: function(xhr, status, error) {
            showAlert('Error de conexión al obtener datos para exportar a PDF: ' + error, 'danger');
        }
    });
}

function exportQuery{$escapedQueryId}ToExcel() {
    showAlert('Generando Excel...', 'info');

    \$.ajax({
        url: 'php/query_{$escapedQueryId}.php',
        type: 'POST',
        data: { action: 'read' },
        success: function(response) {
            processDataAndExportToExcelQuery(response, 'query_{$escapedQueryId}_reporte.xlsx');
        },
        error: function(xhr, status, error) {
            showAlert('Error de conexión al obtener datos para exportar a Excel: ' + error, 'danger');
        }
    });
}

// Cargar tabla cuando el documento esté listo
setTimeout(function() {
    loadQuery{$escapedQueryId}Table();
}, 100);
</script>
HTML;

        // Reemplazar el marcador de información de empresa con la información real
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

        $content = str_replace('{{COMPANY_INFO_FOOTER}}', $companyFooter, $content);

        return $content;
    }
}
