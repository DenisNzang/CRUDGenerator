<?php
require_once __DIR__ . '/../../php/config.php';
require_once __DIR__ . '/../Database/DatabaseConnection.php';
require_once __DIR__ . '/../Utils/GeneratorUtils.php';

class CRUDGenerator {
    private $appData;
    private $outputDir;

    public function __construct($appData) {
        if (!is_array($appData)) {
            throw new Exception("appData debe ser un array");
        }

        $this->validateAppData($appData);

        $appName = GeneratorUtils::cleanAppName($appData['appCustomization']['title'] ?? 'app');
        $this->outputDir = GENERATED_APPS_DIR . $appName . '_' . uniqid();

        $this->appData = $appData;

        Logger::info("CRUDGenerator instanciado para app: " . ($appData['appCustomization']['title'] ?? 'Sin título'));
        Logger::info("Directorio de salida: " . $this->outputDir);
    }

    private function validateAppData($appData) {
        if (!isset($appData['databaseType']) || !in_array($appData['databaseType'], SUPPORTED_DATABASES)) {
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
        Logger::info("Inicio de generación de aplicación: " . ($this->appData['appCustomization']['title'] ?? 'Sin título'));

        try {
            GeneratorUtils::createDirectory($this->outputDir);
            Logger::info("Directorio de salida creado: " . $this->outputDir);

            $this->generateIndexFile();
            Logger::info("Archivo index.html generado");

            $this->generateConfigFile();
            Logger::info("Archivo de configuración generado");

            $this->generateCRUDFiles();
            Logger::info("Archivos CRUD generados");

            $this->generateAssets();
            Logger::info("Activos generados");

            $this->copyAssets();
            Logger::info("Activos copiados");

            $zipPath = $this->createZip();
            Logger::info("Archivo ZIP generado: " . $zipPath);

            $this->cleanupTempFiles();

            $result = [
                'success' => true,
                'downloadUrl' => $zipPath,
                'outputDir' => $this->outputDir
            ];

            Logger::info("Generación completada exitosamente");
            return $result;

        } catch (Exception $e) {
            Logger::error("Error durante la generación: " . $e->getMessage());
            throw $e;
        }
    }

    private function generateIndexFile() {
        $appTitle = $this->appData['appCustomization']['title'] ?? 'Mi App CRUD';
        $primaryColor = $this->appData['appCustomization']['primaryColor'] ?? '#fd7e14';

        $menuItems = '';

        foreach ($this->appData['selectedTables'] as $table) {
            $formattedName = GeneratorUtils::formatFieldName($table);
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

        $primaryColorDark = GeneratorUtils::darkenColor($primaryColor, 20);

        $logoHtml = '';
        $logoPath = $this->handleLogoUpload();
        if ($logoPath && file_exists($this->outputDir . '/' . $logoPath)) {
            $logoHtml = '<img src="' . $logoPath . '" height="30" class="d-inline-block align-top me-2" alt="Logo">';
        } else {
            $logoHtml = '<i class="bi bi-database me-2"></i>';
            $this->createDefaultLogo();
        }

        $content = <<<'HTML'
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: {{PRIMARY_COLOR}} !important;">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
                {{LOGO}}
                <span class="ms-2">{{APP_TITLE}}</span>
            </a>
            <div class="d-flex">
                <select id="languageSelector" class="form-select form-select-sm me-2" style="max-width: 120px;" onchange="changeLanguage(this.value)">
                    <option value="es" selected>Español</option>
                    <option value="en">English</option>
                    <option value="fr">Français</option>
                </select>
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
</body>
</html>
HTML;

        $content = str_replace('{{APP_TITLE}}', htmlspecialchars($appTitle), $content);
        $content = str_replace('{{PRIMARY_COLOR}}', $primaryColor, $content);
        $content = str_replace('{{PRIMARY_COLOR_DARK}}', $primaryColorDark, $content);
        $content = str_replace('{{MENU_ITEMS}}', $menuItems, $content);
        $content = str_replace('{{LOGO}}', $logoHtml, $content);

        file_put_contents($this->outputDir . '/index.html', $content);
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

        GeneratorUtils::createDirectory($this->outputDir . '/config');
        file_put_contents($this->outputDir . '/config/config.php', '<?php return ' . var_export($config, true) . '; ?>');
        file_put_contents($this->outputDir . '/config/config.json', json_encode($config, JSON_PRETTY_PRINT));
    }

    private function generateCRUDFiles() {
        GeneratorUtils::createDirectory($this->outputDir . '/php');
        GeneratorUtils::createDirectory($this->outputDir . '/templates');

        foreach ($this->appData['selectedTables'] as $table) {
            $this->generateTableCRUD($table);
        }

        foreach ($this->appData['customQueries'] as $query) {
            $this->generateQueryCRUD($query);
        }

        $referencedTables = $this->getReferencedTables();
        foreach ($referencedTables as $table) {
            if (!in_array($table, $this->appData['selectedTables'])) {
                $this->generateBasicTableController($table);
            }
        }
    }

    private function getReferencedTables() {
        $referencedTables = [];

        foreach ($this->appData['selectedTables'] as $tableName) {
            $tableStructure = $this->appData['databaseStructure']['tables'][$tableName] ?? [];
            $foreignKeys = $tableStructure['foreignKeys'] ?? [];

            foreach ($foreignKeys as $fk) {
                if (!in_array($fk['referenced_table'], $referencedTables)) {
                    $referencedTables[] = $fk['referenced_table'];
                }
            }
        }

        return $referencedTables;
    }

    private function generateBasicTableController($tableName) {
        $controllerCode = "<?php\n";
        $controllerCode .= "require_once '../config/Database.php';\n\n";
        $controllerCode .= "class {$tableName}Controller {\n";
        $controllerCode .= "    private \$db;\n";
        $controllerCode .= "    private \$table = '{$tableName}';\n\n";
        $controllerCode .= "    public function __construct() {\n";
        $controllerCode .= "        \$this->db = new Database();\n";
        $controllerCode .= "    }\n\n";

        $controllerCode .= "    public function read() {\n";
        $controllerCode .= "        \$query = \"SELECT * FROM `{\$this->table}` ORDER BY id\";\n";
        $controllerCode .= "        return \$this->db->query(\$query);\n";
        $controllerCode .= "    }\n\n";

        $controllerCode .= "    public function getForeignKeyOptions(\$foreignTable, \$displayField = null) {\n";
        $controllerCode .= "        \$displayColumn = \$displayField ? \$displayField : 'id';\n\n";
        $controllerCode .= "        if (!preg_match('/^[a-zA-Z0-9_]+\$/u', \$foreignTable) || !preg_match('/^[a-zA-Z0-9_]+\$/u', \$displayColumn)) {\n";
        $controllerCode .= "            return [];\n";
        $controllerCode .= "        }\n\n";
        $controllerCode .= "        \$query = \"SELECT id, `\".\$displayColumn.\"` AS display_value FROM `\".\$foreignTable.\"` ORDER BY `\".\$displayColumn.\"`;\";\n";
        $controllerCode .= "        return \$this->db->query(\$query);\n";
        $controllerCode .= "    }\n\n";

        $controllerCode .= "    public function find(\$id) {\n";
        $controllerCode .= "        \$query = \"SELECT * FROM {\$this->table} WHERE id = :id\";\n";
        $controllerCode .= "        \$result = \$this->db->query(\$query, ['id' => \$id]);\n";
        $controllerCode .= "        return \$result[0] ?? null;\n";
        $controllerCode .= "    }\n";
        $controllerCode .= "}";

        $controllerCode .= "\n\n";
        $controllerCode .= "// Manejo de solicitudes AJAX para tablas referenciadas\n";
        $controllerCode .= "if (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n";
        $controllerCode .= "    header('Content-Type: application/json');\n\n";
        $controllerCode .= "    \$action = \$_POST['action'] ?? '';\n";
        $controllerCode .= "    \$controller = new {$tableName}Controller();\n\n";
        $controllerCode .= "    switch (\$action) {\n";
        $controllerCode .= "        case 'get_foreign_key_options':\n";
        $controllerCode .= "            \$foreignTable = \$_POST['foreign_table'] ?? '';\n";
        $controllerCode .= "            \$displayField = \$_POST['display_field'] ?? null;\n\n";
        $controllerCode .= "            \$options = \$controller->getForeignKeyOptions(\$foreignTable, \$displayField);\n";
        $controllerCode .= "            echo json_encode(['success' => true, 'data' => \$options]);\n";
        $controllerCode .= "            break;\n\n";
        $controllerCode .= "        case 'read':\n";
        $controllerCode .= "            \$data = \$controller->read();\n";
        $controllerCode .= "            echo json_encode(['success' => true, 'data' => \$data]);\n";
        $controllerCode .= "            break;\n\n";
        $controllerCode .= "        case 'find':\n";
        $controllerCode .= "            \$id = \$_POST['id'];\n";
        $controllerCode .= "            \$data = \$controller->find(\$id);\n";
        $controllerCode .= "            echo json_encode(['success' => true, 'data' => \$data]);\n";
        $controllerCode .= "            break;\n\n";
        $controllerCode .= "        default:\n";
        $controllerCode .= "            echo json_encode(['success' => false, 'error' => 'Acción no válida']);\n";
        $controllerCode .= "    }\n";
        $controllerCode .= "}\n";
        $controllerCode .= "?>\n";

        file_put_contents($this->outputDir . "/php/{$tableName}.php", $controllerCode);
    }

    private function generateTableCRUD($tableName) {
        $phpContent = $this->generateTablePHP($tableName);
        file_put_contents($this->outputDir . "/php/{$tableName}.php", $phpContent);

        $htmlContent = $this->generateTableHTML($tableName);
        file_put_contents($this->outputDir . "/templates/{$tableName}.html", $htmlContent);
    }

    private function generateQueryCRUD($query) {
        $phpContent = $this->generateQueryPHP($query);
        $queryId = $query['id'] ?? uniqid('query_');
        file_put_contents($this->outputDir . "/php/query_{$queryId}.php", $phpContent);

        $htmlContent = $this->generateQueryHTML($query);
        file_put_contents($this->outputDir . "/templates/query_{$queryId}.html", $htmlContent);
    }

    private function generateTablePHP($tableName) {
        $quoteChar = GeneratorUtils::getQuoteCharacterForDbType($this->appData['databaseType']);

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

        $selectClause = implode(", ", $selectFields);
        $joinClause = !empty($joins) ? " " . implode(" ", $joins) : "";

        if ($hasRelatedFields) {
            $readFunction = "
    public function read() {
        \$quoteChar = '{$quoteChar}';
        \$selectClause = stripslashes('" . addslashes($selectClause) . "');
        \$joinClause = stripslashes('" . addslashes($joinClause) . "');
        \$query = 'SELECT ' . \$selectClause . ' FROM ' . \$quoteChar . '{$tableName}' . \$quoteChar . \$joinClause . ' ORDER BY ' . \$quoteChar . '{$tableName}' . \$quoteChar . '.id';
        return \$this->db->query(\$query);
    }";
        } else {
            $readFunction = "
    public function read() {
        \$quoteChar = '{$quoteChar}';
        \$query = 'SELECT * FROM ' . \$quoteChar . '{$tableName}' . \$quoteChar . ' ORDER BY id';
        return \$this->db->query(\$query);
    }";
        }

        $phpCode = <<<PHP
<?php
require_once '../config/Database.php';

class {$tableName}Controller {
    private \$db;
    private \$table = '{$tableName}';

    public function __construct() {
        \$this->db = new Database();
    }

{$readFunction}

    public function create(\$data) {
        \$columns = implode(', ', array_keys(\$data));
        \$values = ':' . implode(', :', array_keys(\$data));
        \$query = "INSERT INTO {\$this->table} (\$columns) VALUES (\$values)";

        return \$this->db->execute(\$query, \$data);
    }

    public function update(\$id, \$data) {
        \$setClause = [];
        foreach (\$data as \$key => \$value) {
            \$setClause[] = "\$key = :\$key";
        }
        \$setClause = implode(', ', \$setClause);

        \$query = "UPDATE {\$this->table} SET \$setClause WHERE id = :id";
        \$data['id'] = \$id;

        return \$this->db->execute(\$query, \$data);
    }

    public function delete(\$id) {
        \$query = "DELETE FROM {\$this->table} WHERE id = :id";
        return \$this->db->execute(\$query, ['id' => \$id]);
    }

    public function getForeignKeyOptions(\$foreignTable, \$displayField = null) {
        \$displayColumn = \$displayField ? \$displayField : 'id';

        if (!preg_match('/^[a-zA-Z0-9_]+$/u', \$foreignTable) || !preg_match('/^[a-zA-Z0-9_]+$/u', \$displayColumn)) {
            return [];
        }

        \$query = "SELECT id, `".\$displayColumn."` AS display_value FROM `".\$foreignTable."` ORDER BY `".\$displayColumn."`";
        return \$this->db->query(\$query);
    }

    public function find(\$id) {
        \$quoteChar = GeneratorUtils::getQuoteCharacterForDbType(\$this->appData['databaseType']);

        \$hasRelatedFields = false;
        \$selectFields = [];
        \$joins = [];

        \$tableFieldConfig = null;
        if (isset(\$this->appData['fieldConfigurations']['{$tableName}'])) {
            \$tableFieldConfig = \$this->appData['fieldConfigurations']['{$tableName}'];
        } elseif (isset(\$this->appData['field_configurations']['{$tableName}'])) {
            \$tableFieldConfig = \$this->appData['field_configurations']['{$tableName}'];
        }

        if (\$tableFieldConfig) {
            \$tableStructure = \$this->appData['databaseStructure']['tables']['{$tableName}'] ?? [];
            \$foreignKeys = \$tableStructure['foreignKeys'] ?? [];

            \$selectFields[] = "{\$quoteChar}{$tableName}{\$quoteChar}.*";

            foreach (\$tableStructure['columns'] as \$column) {
                \$columnName = \$column['name'];

                if (isset(\$tableFieldConfig[\$columnName]['relatedField']) && !empty(\$tableFieldConfig[\$columnName]['relatedField'])) {
                    \$hasRelatedFields = true;

                    \$fkInfo = null;
                    foreach (\$foreignKeys as \$fk) {
                        if (\$fk['column'] === \$columnName) {
                            \$fkInfo = \$fk;
                            break;
                        }
                    }

                    if (\$fkInfo) {
                        \$refTableAlias = \$fkInfo['referenced_table'] . '_r_' . \$columnName;
                        \$relatedField = \$tableFieldConfig[\$columnName]['relatedField'];

                        \$joins[] = "LEFT JOIN {\$quoteChar}{\$fkInfo['referenced_table']}{\$quoteChar} AS {\$quoteChar}{\$refTableAlias}{\$quoteChar} ON {\$quoteChar}{$tableName}{\$quoteChar}.{\$quoteChar}{\$columnName}{\$quoteChar} = {\$quoteChar}{\$refTableAlias}{\$quoteChar}.{\$quoteChar}{\$fkInfo['referenced_column']}{\$quoteChar}";

                        \$selectFields[] = "{\$quoteChar}{\$refTableAlias}{\$quoteChar}.{\$quoteChar}{\$relatedField}{\$quoteChar} AS {\$quoteChar}{\$columnName}_related_display{\$quoteChar}";
                    }
                }
            }

            \$selectClause = implode(", ", \$selectFields);
            \$joinClause = !empty(\$joins) ? " " . implode(" ", \$joins) : "";

            \$query = "SELECT " . \$selectClause . " FROM " . \$quoteChar . '{$tableName}' . \$quoteChar . \$joinClause . " WHERE " . \$quoteChar . '{$tableName}' . \$quoteChar . ".id = :id";
        } else {
            \$query = "SELECT * FROM {\$this->table} WHERE id = :id";
        }

        \$result = \$this->db->query(\$query, ['id' => \$id]);
        return \$result[0] ?? null;
    }
}

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

        return $phpCode;
    }

    private function generateFormFields($tableName) {
        $html = '';

        $tableStructure = $this->appData['databaseStructure']['tables'][$tableName] ?? [];
        $tableColumns = $tableStructure['columns'] ?? [];
        $tableForeignKeys = $tableStructure['foreignKeys'] ?? [];

        $fieldConfigurations = $this->appData['fieldConfigurations'][$tableName] ?? [];

        foreach ($tableColumns as $column) {
            $columnName = $column['name'];
            $columnType = $column['type'];
            $isPrimaryKey = $column['primaryKey'] ?? false;
            $nullable = $column['nullable'] ?? false;

            if ($isPrimaryKey) {
                continue;
            }

            $fieldConfig = $fieldConfigurations[$columnName] ?? [];
            $controlType = $fieldConfig['controlType'] ?? GeneratorUtils::getDefaultControlTypeForColumn($columnType, $isPrimaryKey);
            $label = $fieldConfig['label'] ?? GeneratorUtils::formatFieldName($columnName);
            $isRequired = ($fieldConfig['required'] ?? (!$nullable && $columnName !== 'id')) ? 'required' : '';

            $isForeignKey = false;
            foreach ($tableForeignKeys as $fk) {
                if ($fk['column'] === $columnName) {
                    $isForeignKey = true;
                    break;
                }
            }

            $fieldHtml = '<div class="mb-3">';

            if ($isForeignKey) {
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

                    $fieldHtml .= "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            loadForeignKeyOptions('{$columnName}-{$tableName}', '{$foreignTable}', '" . ($fieldConfig['relatedField'] ?? '') . "', '{$referencedColumn}');
                        });

                        document.addEventListener('shown.bs.modal', function() {
                            loadForeignKeyOptions('{$columnName}-{$tableName}', '{$foreignTable}', '" . ($fieldConfig['relatedField'] ?? '') . "', '{$referencedColumn}');
                        });
                    </script>";
                }
            } else {
                $inputType = GeneratorUtils::getColumnInputType($columnType);
                $fieldHtml .= "<label for='{$columnName}-{$tableName}' class='form-label'>{$label}" . ($isRequired ? ' *' : '') . "</label>";

                if ($inputType === 'textarea') {
                    $fieldHtml .= "<textarea class='form-control' id='{$columnName}-{$tableName}' name='{$columnName}' {$isRequired}></textarea>";
                } else if ($inputType === 'select') {
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

    private function generateTableHTML($tableName) {
        $columns = $this->appData['databaseStructure']['tables'][$tableName]['columns'] ?? [];

        $headerHtml = '';
        $columnDefs = [];

        foreach ($columns as $column) {
            $columnName = $column['name'];

            // Verificar si el campo debe mostrarse en listados
            $showInList = true; // Por defecto, mostrar los campos

            if (isset($this->appData['fieldConfigurations'][$tableName]) &&
                isset($this->appData['fieldConfigurations'][$tableName][$columnName])) {

                $fieldConfig = $this->appData['fieldConfigurations'][$tableName][$columnName];

                // Verificar si showInList está definido en cualquiera de los formatos posibles
                if (isset($fieldConfig['showInList'])) {
                    $showInList = $fieldConfig['showInList'];
                } elseif (isset($fieldConfig['show-in-list'])) {
                    $showInList = $fieldConfig['show-in-list'];
                }
            } elseif (isset($this->appData['field_configurations'][$tableName]) &&
                      isset($this->appData['field_configurations'][$tableName][$columnName])) {

                $fieldConfig = $this->appData['field_configurations'][$tableName][$columnName];

                // Verificar si showInList está definido en cualquiera de los formatos posibles
                if (isset($fieldConfig['showInList'])) {
                    $showInList = $fieldConfig['showInList'];
                } elseif (isset($fieldConfig['show-in-list'])) {
                    $showInList = $fieldConfig['show-in-list'];
                }
            }

            // Si no debe mostrarse en listados, omitir esta columna
            if (!$showInList) {
                continue;
            }

            $label = GeneratorUtils::formatFieldName($columnName);

            if (isset($this->appData['fieldConfigurations'][$tableName]) &&
                isset($this->appData['fieldConfigurations'][$tableName][$columnName])) {

                $fieldConfig = $this->appData['fieldConfigurations'][$tableName][$columnName];

                if (isset($fieldConfig['label']) && !empty($fieldConfig['label'])) {
                    $label = $fieldConfig['label'];
                } elseif (isset($fieldConfig['text']) && !empty($fieldConfig['text'])) {
                    $label = $fieldConfig['text'];
                } else {
                    $label = GeneratorUtils::formatFieldName($columnName);
                }
            } else {
                $label = GeneratorUtils::formatFieldName($columnName);
            }

            $headerHtml .= "<th>" . htmlspecialchars($label) . "</th>";

            $dataField = $columnName;

            $hasRelatedField = false;
            if (isset($this->appData['fieldConfigurations'][$tableName][$columnName]['relatedField']) &&
                !empty($this->appData['fieldConfigurations'][$tableName][$columnName]['relatedField'])) {
                $hasRelatedField = true;
            } elseif (isset($this->appData['field_configurations'][$tableName][$columnName]['relatedField']) &&
                      !empty($this->appData['field_configurations'][$tableName][$columnName]['relatedField'])) {
                $hasRelatedField = true;
            }

            if ($hasRelatedField) {
                $dataField = $columnName . '_related_display';
            }

            $columnDefs[] = "{ data: '" . addslashes($dataField) . "' }";
        }

        $headerHtml .= "<th>Acciones</th>";

        $dataColumnDefs = implode(",\n                ", $columnDefs);
        $dataColumnDefs .= ",\n                {
                    data: null,
                    render: function(data, type, row) {
                        return '<button class=\"btn btn-sm btn-warning me-1\" onclick=\"edit{$tableName}(' + row.id + ')\">' +
                                   '<i class=\"bi bi-pencil\"></i>' +
                               '</button>' +
                               '<button class=\"btn btn-sm btn-danger\" onclick=\"delete{$tableName}(' + row.id + ')\">' +
                                   '<i class=\"bi bi-trash\"></i>' +
                               '</button>';
                    }
                }";

        $escapedTableName = GeneratorUtils::escapeForJavaScript($tableName);

        return <<<HTML
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Gestión de {$tableName}</h5>
        <div class="btn-group" role="group">
            <button class="btn btn-success btn-sm dropdown-toggle" type="button" id="exportBtn{$escapedTableName}" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-download"></i> Exportar
            </button>
            <ul class="dropdown-menu" aria-labelledby="exportBtn{$escapedTableName}">
                <li><a class="dropdown-item" href="#" onclick="export{$escapedTableName}ToPDF(false); return false;"><i class="bi bi-file-pdf"></i> Exportar Todos a PDF</a></li>
                <li><a class="dropdown-item" href="#" onclick="export{$escapedTableName}ToPDF(true); return false;"><i class="bi bi-file-pdf"></i> Exportar Seleccionados a PDF</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="export{$escapedTableName}ToExcel(false); return false;"><i class="bi bi-file-excel"></i> Exportar Todos a Excel</a></li>
                <li><a class="dropdown-item" href="#" onclick="export{$escapedTableName}ToExcel(true); return false;"><i class="bi bi-file-excel"></i> Exportar Seleccionados a Excel</a></li>
            </ul>
            <button class="btn btn-primary btn-sm ms-2" onclick="showCreateModal('{$escapedTableName}')">
                <i class="bi bi-plus-circle"></i> Nuevo
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
console.log('Script de tabla {$escapedTableName} ejecutándose...');

function load{$escapedTableName}Table() {
    console.log('Inicializando DataTable para {$escapedTableName}');
    try {
        \$('#table-{$escapedTableName}').DataTable({
            ajax: {
                url: 'php/{$escapedTableName}.php',
                type: 'POST',
                data: { action: 'read' },
                dataSrc: function(json) {
                    console.log('Datos recibidos para tabla {$escapedTableName}:', json);
                    if (json.error) {
                        console.error('Error en la respuesta del servidor para {$escapedTableName}:', json.error);
                        showAlert('Error al cargar datos: ' + json.error, 'danger');
                    }
                    return json.data || [];
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX al cargar datos para tabla {$escapedTableName}:', error, status, xhr);
                    showAlert('Error de conexión al cargar datos para {$escapedTableName}: ' + error, 'danger');
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
            },
            error: function(e, settings, techNote) {
                console.error('Error de DataTables para {$escapedTableName}:', techNote, e);
                showAlert('Error interno en la tabla {$escapedTableName}: ' + techNote, 'danger');
            }
        });
        console.log('DataTable inicializado correctamente para {$escapedTableName}');
    } catch (error) {
        console.error('Error inicializando DataTable para {$escapedTableName}:', error);
        showAlert('Error al inicializar la tabla: ' + error.message, 'danger');
    }
}

function showCreateModal() {
    \$('#editId-{$escapedTableName}').val('');
    \$('#modalTitle-{$escapedTableName}').text('Nuevo Registro');
    \$('#form-{$escapedTableName}')[0].reset();
    \$('#modal-{$escapedTableName}').modal('show');
}

function edit{$escapedTableName}(id) {
    console.log('Cargando datos para editar registro:', id);

    \$.post('php/{$escapedTableName}.php', { action: 'find', id: id }, function(response) {
        console.log('Respuesta recibida para editar:', response);

        if (response && typeof response === 'object') {
            if (response.success === false) {
                showAlert('Error al obtener datos para editar: ' + (response.error || 'Error desconocido'), 'danger');
                return;
            }

            let recordData = response.data || response;
            let recordId = recordData.id || (Array.isArray(recordData) && recordData.length > 0 ? recordData[0].id : null);

            if (recordData && recordId) {
                \$('#editId-{$escapedTableName}').val(recordId);
                \$('#modalTitle-{$escapedTableName}').text('Editar Registro #' + recordId);

                for (let key in recordData) {
                    if (recordData.hasOwnProperty(key)) {
                        if (!key.includes('_related_display')) {
                            let field = \$("#" + key + "-{$escapedTableName}");
                            if (field.length > 0) {
                                if (field.is('select')) {
                                    field.val(recordData[key]).trigger('change');
                                } else {
                                    field.val(recordData[key]);
                                }
                            }
                        }
                    }
                }

                \$('#modal-{$escapedTableName}').modal('show');
            } else {
                console.error('Datos inválidos recibidos para edición:', recordData);
                showAlert('No se pudo cargar los datos del registro para editar: datos inválidos', 'danger');
            }
        } else {
            showAlert('No se pudo cargar los datos del registro para editar', 'danger');
        }
    }).fail(function(xhr, status, error) {
        console.error('Error AJAX al editar:', xhr, status, error);
        showAlert('Error al cargar los datos para edición: ' + error, 'danger');
    });
}

function delete{$escapedTableName}(id) {
    if (confirm('¿Estás seguro de eliminar este registro?')) {
        console.log('Eliminando registro:', id);
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

function getSelectedIds(tableName) {
    const selectedCheckboxes = document.querySelectorAll('#table-' + tableName + ' .row-checkbox:checked');
    return Array.from(selectedCheckboxes).map(cb => parseInt(cb.dataset.id));
}

function toggleSelectAll(tableName) {
    const selectAllCheckbox = document.getElementById('selectAll' + tableName);
    const checkboxes = document.querySelectorAll('#table-' + tableName + ' .row-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

function toggleSelectAllRows(tableName) {
    const selectAllCheckbox = document.getElementById('selectAllHeader' + tableName);
    const checkboxes = document.querySelectorAll('#table-' + tableName + ' .row-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

function updateSelectAllState(tableName) {
    const checkboxes = document.querySelectorAll('#table-' + tableName + ' .row-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll' + tableName);
    const selectAllHeaderCheckbox = document.getElementById('selectAllHeader' + tableName);

    const checkedCount = document.querySelectorAll('#table-' + tableName + ' .row-checkbox:checked').length;
    const totalCount = checkboxes.length;

    selectAllCheckbox.checked = checkedCount === totalCount && totalCount > 0;
    selectAllHeaderCheckbox.checked = checkedCount === totalCount && totalCount > 0;
}

function processDataAndExportToPDF(response, title, filename) {
    if (response.success && response.data) {
        waitForJsPDF(function() {
            try {
                const data = response.data;

                const { jsPDF } = window;
                const doc = new jsPDF();

                let logoYPosition = 20;
                let logoAdded = false;

                const logoSelectors = ['nav img', '.navbar-brand img', 'header img', '.logo', '[alt="Logo"]', 'img[src*="logo"]'];

                for (const selector of logoSelectors) {
                    const logoImg = document.querySelector(selector);
                    if (logoImg && logoImg.complete && logoImg.src && !logoImg.src.includes('bi-database')) {
                        try {
                            doc.addImage(logoImg.src, 'JPEG', 15, 10, 30, 15, undefined, 'FAST');
                            logoYPosition = 35;
                            logoAdded = true;
                            break;
                        } catch (e) {
                            console.debug('No se pudo agregar el logo con selector', selector, ':', e);
                        }
                    }
                }

                if (!logoAdded) {
                    const commonLogoPaths = [
                        'assets/logo.png',
                        'assets/logo.jpg',
                        'assets/default-logo.png',
                        'img/logo.png',
                        'img/logo.jpg',
                        'img/default-logo.png'
                    ];

                    for (const logoPath of commonLogoPaths) {
                        try {
                            doc.addImage(logoPath, 'PNG', 15, 10, 30, 15, undefined, 'FAST');
                            logoYPosition = 35;
                            logoAdded = true;
                            break;
                        } catch (e) {
                            continue;
                        }
                    }
                }

                doc.setFontSize(18);
                doc.text(title, 20, logoYPosition);

                const headers = [];
                \$('#table-{$escapedTableName} thead th').each(function() {
                    const text = \$(this).text();
                    const \$th = \$(this);
                    if (text !== 'Acciones' && !\$th.find('input[type="checkbox"]').length > 0) {
                        headers.push(text);
                    }
                });

                const headerFields = [];
                \$('#table-{$escapedTableName} thead th').each(function() {
                    const text = \$(this).text();
                    const \$th = \$(this);
                    if (text !== 'Acciones' && !\$th.find('input[type="checkbox"]').length > 0) {
                        headerFields.push(findDataFieldForHeader(text, \$('#table-{$escapedTableName}')));
                    }
                });

                const tableData = data.map(row => {
                    const rowData = [];
                    for (let i = 0; i < headerFields.length; i++) {
                        const dataField = headerFields[i];
                        if (dataField && row.hasOwnProperty(dataField)) {
                            rowData.push(row[dataField]);
                        } else {
                            rowData.push(getValueForHeader(row, headers[i]));
                        }
                    }
                    return rowData;
                });

                if (tableData.length > 0 && headers.length > 0) {
                    let startY = logoYPosition + 10;

                    doc.autoTable({
                        head: [headers],
                        body: tableData,
                        startY: startY,
                        styles: {
                            fontSize: 10
                        },
                        headStyles: {
                            fillColor: [253, 126, 20]
                        }
                    });
                }

                doc.save(filename);
                showAlert('PDF generado exitosamente', 'success');
            } catch (error) {
                console.error('Error al generar PDF:', error);
                showAlert('Error al generar PDF: ' + error.message, 'danger');
            }
        });
    } else {
        showAlert('Error al obtener datos para exportar: ' + (response.error || 'Error desconocido'), 'danger');
    }
}

function findDataFieldForHeader(headerText, tableElement) {
    const dt = tableElement.DataTable();
    if (dt) {
        const columns = dt.columns().header();
        const columnIndex = Array.from(columns).findIndex(header =>
            \$(header).text().trim() === headerText.trim()
        );

        if (columnIndex !== -1) {
            const settings = dt.settings()[0];
            const columnData = settings.aoColumns[columnIndex];
            if (columnData && columnData.data) {
                return columnData.data;
            }
        }
    }

    return null;
}

function waitForJsPDF(callback) {
    let attempts = 0;
    const maxAttempts = 50;

    function checkJsPDF() {
        attempts++;

        if (typeof window.jsPDF !== 'undefined' && typeof window.jsPDF.jsPDF !== 'undefined') {
            callback();
        } else if (typeof window.jspdf !== 'undefined' && typeof window.jspdf.jsPDF !== 'undefined') {
            window.jsPDF = window.jspdf.jsPDF;
            if (typeof window.jspdf.autoTable !== 'undefined') {
                window.jsPDF.autoTable = window.jspdf.autoTable;
            }
            callback();
        } else if (typeof window.jspdf !== 'undefined' && typeof window.jspdf.default !== 'undefined' && typeof window.jspdf.default.jsPDF !== 'undefined') {
            window.jsPDF = window.jspdf.default.jsPDF;
            if (window.jspdf.default.autoTable) {
                window.jsPDF.autoTable = window.jspdf.default.autoTable;
            }
            callback();
        } else if (attempts < maxAttempts) {
            setTimeout(checkJsPDF, 100);
        } else {
            showAlert('Error: jsPDF no se ha cargado correctamente después de 5 segundos', 'danger');
        }
    }

    checkJsPDF();
}

function normalizeHeader(header) {
    return header.toLowerCase()
        .replace(/\s+/g, '')
        .replace(/\./g, '')
        .replace(/[^a-z0-9_]/gi, '');
}

function getValueForHeader(row, header) {
    const normalizedHeader = normalizeHeader(header);

    for (const key in row) {
        if (row.hasOwnProperty(key) && key !== 'DT_RowId') {
            const normalizedKey = normalizeHeader(key);

            if (normalizedKey.includes('_related_display')) {
                const baseField = key.replace('_related_display', '');
                const normalizedBaseField = normalizeHeader(baseField);

                if (normalizedHeader.includes(normalizedBaseField) || normalizedBaseField.includes(normalizedHeader)) {
                    return row[key];
                }
            }
        }
    }

    for (const key in row) {
        if (row.hasOwnProperty(key) && key !== 'DT_RowId') {
            if (normalizeHeader(key) === normalizedHeader) {
                return row[key];
            }
        }
    }

    for (const key in row) {
        if (row.hasOwnProperty(key) && key !== 'DT_RowId') {
            const normalizedKey = normalizeHeader(key);
            if (normalizedHeader.includes(normalizedKey) || normalizedKey.includes(normalizedHeader)) {
                return row[key];
            }
        }
    }

    const possibleRelatedField = header.toLowerCase().replace(/\s+/g, '_');
    if (row.hasOwnProperty(possibleRelatedField . '_related_display')) {
        return row[$possibleRelatedField . '_related_display'];
    }

    for (const key in row) {
        if (row.hasOwnProperty(key) && key !== 'DT_RowId' && key.includes('_related_display')) {
            const baseFieldName = key.replace('_related_display', '');
            if (normalizedHeader.includes(normalizeHeader(baseFieldName)) ||
                normalizeHeader(baseFieldName).includes(normalizedHeader)) {
                return row[key];
            }
        }
    }

    return '';
}

function processDataAndExportToExcel(response, filename) {
    if (response.success && response.data) {
        const data = response.data;

        const wb = XLSX.utils.book_new();

        const headers = [];
        const headerFields = [];
        \$('#table-{$escapedTableName} thead th').each(function() {
            const text = \$(this).text();
            const \$th = \$(this);
            if (text !== 'Acciones' && !\$th.find('input[type="checkbox"]').length > 0) {
                headers.push(text);
                headerFields.push(findDataFieldForHeader(text, \$('#table-{$escapedTableName}')));
            }
        });

        const excelData = [headers];

        data.forEach(row => {
            const rowData = [];
            for (let i = 0; i < headerFields.length; i++) {
                const dataField = headerFields[i];
                if (dataField && row.hasOwnProperty(dataField)) {
                    rowData.push(row[dataField]);
                } else {
                    rowData.push(getValueForHeader(row, headers[i]));
                }
            }
            excelData.push(rowData);
        });

        const ws = XLSX.utils.aoa_to_sheet(excelData);

        XLSX.utils.book_append_sheet(wb, ws, '{$escapedTableName}');

        XLSX.writeFile(wb, filename);
        showAlert('Excel generado exitosamente', 'success');
    } else {
        showAlert('Error al obtener datos para exportar: ' + (response.error || 'Error desconocido'), 'danger');
    }
}

console.log('Template {$escapedTableName} cargado, inicializando DataTable...');
setTimeout(function() {
    load{$escapedTableName}Table();
}, 100);
</script>
HTML;
    }

    private function generateQueryPHP($query) {
        $queryName = $query['name'];
        $queryId = $query['id'] ?? uniqid('query_');
        $sql = GeneratorUtils::sanitizeSQL($query['sql']);
        $type = $query['type'];

        return <<<PHP
<?php
require_once '../config/Database.php';

class Query{$queryId}Controller {
    private \$db;

    public function __construct() {
        \$this->db = new Database();
    }

    public function execute() {
        try {
            \$sql = "{$sql}";
            \$result = \$this->db->query(\$sql);
            return ['success' => true, 'data' => \$result];
        } catch (Exception \$e) {
            return ['success' => false, 'error' => \$e->getMessage()];
        }
    }
}

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

    private function generateQueryHTML($query) {
        $queryName = $query['name'];
        $queryId = $query['id'] ?? uniqid('query_');
        $type = $query['type'];

        $escapedQueryName = GeneratorUtils::escapeForJavaScript($queryName);
        $escapedQueryId = GeneratorUtils::escapeForJavaScript($queryId);

        return <<<HTML
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">{$queryName}</h5>
        <div class="btn-group" role="group">
            <button class="btn btn-success btn-sm dropdown-toggle" type="button" id="exportBtnQuery{$escapedQueryId}" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-download"></i> Exportar
            </button>
            <ul class="dropdown-menu" aria-labelledby="exportBtnQuery{$escapedQueryId}">
                <li><a class="dropdown-item" href="#" onclick="exportQuery{$escapedQueryId}ToPDF(); return false;"><i class="bi bi-file-pdf"></i> Exportar a PDF</a></li>
                <li><a class="dropdown-item" href="#" onclick="exportQuery{$escapedQueryId}ToExcel(); return false;"><i class="bi bi-file-excel"></i> Exportar a Excel</a></li>
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
</div>

<script>
function loadQuery{$escapedQueryId}Table() {
    console.log('Inicializando DataTable para consulta {$escapedQueryName}');
    try {
        \$.ajax({
            url: 'php/query_{$escapedQueryId}.php',
            type: 'POST',
            data: { action: 'read' },
            success: function(response) {
                console.log('Respuesta del servidor para consulta:', response);
                if (response.success && response.data && response.data.length > 0) {
                    const firstRow = response.data[0];
                    const columns = Object.keys(firstRow).map(function(key) {
                        return {
                            data: key,
                            title: key.charAt(0).toUpperCase() + key.slice(1)
                        };
                    });

                    const headerRow = \$('#table-query-{$escapedQueryId} thead tr');
                    headerRow.empty();
                    columns.forEach(function(col) {
                        headerRow.append('<th>' + col.title + '</th>');
                    });
                    headerRow.append('<th>Acciones</th>');

                    \$('#table-query-{$escapedQueryId}').DataTable({
                        ajax: {
                            url: 'php/query_{$escapedQueryId}.php',
                            type: 'POST',
                            data: { action: 'read' },
                            dataSrc: function(json) {
                                console.log('Datos recibidos para consulta {$escapedQueryId}:', json);
                                if (json.error) {
                                    console.error('Error en la respuesta del servidor para consulta {$escapedQueryId}:', json.error);
                                    showAlert('Error al cargar datos: ' + json.error, 'danger');
                                }
                                return json.data || [];
                            },
                            error: function(xhr, status, error) {
                                console.error('Error AJAX al cargar datos para consulta {$escapedQueryId}:', error, status, xhr);
                                showAlert('Error de conexión al cargar datos para consulta {$escapedQueryId}: ' + error, 'danger');
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
                        },
                        responsive: true,
                        processing: true,
                        serverSide: false,
                        error: function(e, settings, techNote) {
                            console.error('Error de DataTables para consulta {$escapedQueryId}:', techNote, e);
                            showAlert('Error interno en la tabla de consulta {$escapedQueryId}: ' + techNote, 'danger');
                        }
                    });
                } else {
                    console.error('Error en respuesta de consulta:', response.error || 'No hay datos');
                    showAlert('Error al cargar datos de consulta: ' + (response.error || 'Error desconocido'), 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error obteniendo datos de consulta:', error);
                showAlert('Error de conexión al cargar consulta: ' + error, 'danger');
            }
        });
        console.log('DataTable de consulta inicializado correctamente');
    } catch (error) {
        console.error('Error inicializando DataTable de consulta:', error);
        showAlert('Error al cargar la consulta: ' + error.message, 'danger');
    }
}

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
            console.error('Error obteniendo datos de consulta para exportar a PDF:', error);
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
            console.error('Error obteniendo datos de consulta para exportar a Excel:', error);
            showAlert('Error de conexión al obtener datos para exportar a Excel: ' + error, 'danger');
        }
    });
}

function processDataAndExportToPDFQuery(response, title, filename) {
    if (response.success && response.data && response.data.length > 0) {
        waitForJsPDF(function() {
            try {
                const data = response.data;

                const { jsPDF } = window;
                const doc = new jsPDF();

                let logoYPosition = 20;
                let logoAdded = false;

                const logoSelectors = ['nav img', '.navbar-brand img', 'header img', '.logo', '[alt="Logo"]', 'img[src*="logo"]'];

                for (const selector of logoSelectors) {
                    const logoImg = document.querySelector(selector);
                    if (logoImg && logoImg.complete && logoImg.src && !logoImg.src.includes('bi-database')) {
                        try {
                            doc.addImage(logoImg.src, 'JPEG', 15, 10, 30, 15, undefined, 'FAST');
                            logoYPosition = 35;
                            logoAdded = true;
                            break;
                        } catch (e) {
                            console.debug('No se pudo agregar el logo con selector', selector, ':', e);
                        }
                    }
                }

                if (!logoAdded) {
                    const commonLogoPaths = [
                        'assets/logo.png',
                        'assets/logo.jpg',
                        'assets/default-logo.png',
                        'img/logo.png',
                        'img/logo.jpg',
                        'img/default-logo.png'
                    ];

                    for (const logoPath of commonLogoPaths) {
                        try {
                            doc.addImage(logoPath, 'PNG', 15, 10, 30, 15, undefined, 'FAST');
                            logoYPosition = 35;
                            logoAdded = true;
                            break;
                        } catch (e) {
                            continue;
                        }
                    }
                }

                doc.setFontSize(18);
                doc.text(title, 20, logoYPosition);

                const headers = [];
                \$('#table-query-{$escapedQueryId} thead th').each(function() {
                    const text = \$(this).text();
                    const \$th = \$(this);
                    if (text !== 'Acciones' && !\$th.find('input[type="checkbox"]').length > 0) {
                        headers.push(text);
                    }
                });

                const headerFields = [];
                \$('#table-query-{$escapedQueryId} thead th').each(function() {
                    const text = \$(this).text();
                    const \$th = \$(this);
                    if (text !== 'Acciones' && !\$th.find('input[type="checkbox"]').length > 0) {
                        headerFields.push(findDataFieldForHeader(text, \$('#table-query-{$escapedQueryId}')));
                    }
                });

                const tableData = data.map(row => {
                    const rowData = [];
                    for (let i = 0; i < headerFields.length; i++) {
                        const dataField = headerFields[i];
                        if (dataField && row.hasOwnProperty(dataField)) {
                            rowData.push(row[dataField]);
                        } else {
                            rowData.push(getValueForHeader(row, headers[i]));
                        }
                    }
                    return rowData;
                });

                if (tableData.length > 0 && headers.length > 0) {
                    let startY = logoYPosition + 10;

                    doc.autoTable({
                        head: [headers],
                        body: tableData,
                        startY: startY,
                        styles: {
                            fontSize: 10
                        },
                        headStyles: {
                            fillColor: [253, 126, 20]
                        },
                        didParseCell: function(data) {
                        }
                    });
                }

                doc.save(filename);
                showAlert('PDF generado exitosamente', 'success');
            } catch (error) {
                console.error('Error al generar PDF para consulta:', error);
                showAlert('Error al generar PDF: ' + error.message, 'danger');
            }
        });
    } else {
        showAlert('Error al obtener datos para exportar: ' + (response.error || 'No hay datos'), 'danger');
    }
}

function processDataAndExportToExcelQuery(response, filename) {
    if (response.success && response.data && response.data.length > 0) {
        const data = response.data;

        const wb = XLSX.utils.book_new();

        const headers = [];
        \$('#table-query-{$escapedQueryId} thead th').each(function() {
            const text = \$(this).text();
            const \$th = \$(this);
            if (text !== 'Acciones' && !\$th.find('input[type="checkbox"]').length > 0) {
                headers.push(text);
            }
        });

        const excelData = [headers];
        data.forEach(row => {
            const rowData = [];
            headers.forEach(header => {
                rowData.push(getValueForHeader(row, header));
            });
            excelData.push(rowData);
        });

        const ws = XLSX.utils.aoa_to_sheet(excelData);

        XLSX.utils.book_append_sheet(wb, ws, 'Consulta_{$escapedQueryId}');

        XLSX.writeFile(wb, filename);
        showAlert('Excel generado exitosamente', 'success');
    } else {
        showAlert('Error al obtener datos para exportar: ' + (response.error || 'No hay datos'), 'danger');
    }
}

console.log('Template de consulta {$escapedQueryName} cargado, inicializando DataTable...');
setTimeout(function() {
    loadQuery{$escapedQueryId}Table();
}, 100);
</script>
HTML;
    }

    private function generateAssets() {
        GeneratorUtils::createDirectory($this->outputDir . '/css');
        GeneratorUtils::createDirectory($this->outputDir . '/js');

        file_put_contents($this->outputDir . '/js/app.js', $this->generateMainJS());
        file_put_contents($this->outputDir . '/config/Database.php', $this->generateDatabaseClass());
    }

    private function generateMainJS() {
        return <<<'JS'
// Aplicación CRUD Generada - Archivo Principal
class CRUDApp {
    constructor() {
        this.currentView = '';
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadFirstView();
    }

    bindEvents() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('#sidebar a[data-view]') ||
                e.target.closest('#sidebar a[data-view]')) {
                e.preventDefault();
                const menuItem = e.target.matches('#sidebar a[data-view]') ?
                    e.target : e.target.closest('#sidebar a[data-view]');
                this.loadView(menuItem.getAttribute('data-view'), menuItem);
            }
        });
    }

    loadFirstView() {
        const firstMenuItem = document.querySelector('#sidebar a[data-view]');
        if (firstMenuItem) {
            this.loadView(firstMenuItem.getAttribute('data-view'), firstMenuItem);
        } else {
            document.getElementById('content').innerHTML = `
                <div class="alert alert-warning">
                    <h5>No hay elementos disponibles</h5>
                    <p class="mb-0">No se han configurado tablas ni consultas para esta aplicación.</p>
                </div>
            `;
        }
    }

    loadView(viewId, menuItem) {
        console.log('Cargando vista:', viewId);
        this.currentView = viewId;
        const content = document.getElementById('content');

        content.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2">Cargando...</p>
            </div>
        `;

        document.querySelectorAll('#sidebar a').forEach(item => {
            item.classList.remove('active');
        });
        if (menuItem) {
            menuItem.classList.add('active');
        }

        if (viewId.startsWith('table-')) {
            const tableName = viewId.replace('table-', '');
            this.loadTableView(tableName);
        } else if (viewId.startsWith('query-')) {
            const queryId = viewId.replace('query-', '');
            this.loadQueryView(queryId);
        } else {
            content.innerHTML = `
                <div class="alert alert-danger">
                    <h5>Error</h5>
                    <p class="mb-0">Tipo de vista no reconocido: ${viewId}</p>
                </div>
            `;
        }
    }

    loadTableView(tableName) {
        const content = document.getElementById('content');
        console.log('Cargando tabla:', tableName);

        fetch(`templates/${tableName}.html`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Template no encontrado (${response.status}): ${response.statusText}`);
                }
                return response.text();
            })
            .then(html => {
                console.log('Template cargado correctamente');
                content.innerHTML = html;
                this.executeScripts(content);
            })
            .catch(error => {
                console.error('Error cargando tabla:', error);
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <h5>Error al cargar la tabla "${tableName}"</h5>
                        <p>No se pudo cargar la interfaz para esta tabla.</p>
                        <div class="mt-2">
                            <p class="mb-1 small text-muted">Posibles causas:</p>
                            <ul class="small text-muted mb-0">
                                <li>El archivo templates/${tableName}.html no existe</li>
                                <li>Error de red o permisos</li>
                                <li>Problema con el servidor web</li>
                            </ul>
                        </div>
                        <p class="mt-2 mb-0 small"><strong>Error técnico:</strong> ${error.message}</p>
                    </div>
                `;
            });
    }

    loadQueryView(queryId) {
        const content = document.getElementById('content');
        console.log('Cargando consulta:', queryId);

        fetch(`templates/query_${queryId}.html`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Template no encontrado (${response.status}): ${response.statusText}`);
                }
                return response.text();
            })
            .then(html => {
                console.log('Template de consulta cargado correctamente');
                content.innerHTML = html;
                this.executeScripts(content);
            })
            .catch(error => {
                console.error('Error cargando consulta:', error);
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <h5>Error al cargar la consulta</h5>
                        <p>No se pudo cargar la interfaz para esta consulta personalizada.</p>
                        <div class="mt-2">
                            <p class="mb-1 small text-muted">Posibles causas:</p>
                            <ul class="small text-muted mb-0">
                                <li>El archivo templates/query_${queryId}.html no existe</li>
                                <li>Error de red o permisos</li>
                                <li>Problema con el servidor web</li>
                            </ul>
                        </div>
                        <p class="mt-2 mb-0 small"><strong>Error técnico:</strong> ${error.message}</p>
                    </div>
                `;
            });
    }

    executeScripts(container) {
        const scripts = container.querySelectorAll('script');
        console.log('Ejecutando', scripts.length, 'scripts');

        scripts.forEach((script, index) => {
            try {
                if (script.textContent && (script.textContent.includes('DataTable') || script.textContent.includes('$.') || script.textContent.includes('jQuery'))) {
                    this.waitForLibraries(() => {
                        this.executeScriptElement(script);
                    });
                } else {
                    this.executeScriptElement(script);
                }

                console.log('Script ejecutado:', index);

            } catch (error) {
                console.error('Error ejecutando script:', index, error);
            }
        });
    }

    waitForLibraries(callback) {
        const checkLibraries = () => {
            if (typeof $ !== 'undefined' &&
                typeof $.fn !== 'undefined' &&
                typeof $.fn.DataTable !== 'undefined') {
                callback();
            } else {
                setTimeout(checkLibraries, 100);
            }
        };
        checkLibraries();
    }

    executeScriptElement(script) {
        const newScript = document.createElement('script');

        for (let attr of script.attributes) {
            try {
                newScript.setAttribute(attr.name, attr.value);
            } catch (e) {
                console.warn('Error copiando atributo:', attr.name, e);
            }
        }

        if (script.src) {
            newScript.src = script.src;
            newScript.onload = () => console.log('Script externo cargado:', script.src);
            newScript.onerror = (e) => console.error('Error cargando script externo:', script.src, e);
        } else {
            try {
                newScript.textContent = script.textContent || '';
            } catch (e) {
                console.warn('Error estableciendo contenido del script:', e);
                const scriptContent = script.textContent || '';
                newScript.textContent = scriptContent.replace(/<!\[CDATA\[|\]\]>/g, '');
            }
        }

        if (document.body) {
            document.body.appendChild(newScript);
        } else {
            document.head.appendChild(newScript);
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando aplicación CRUD...');
    window.crudApp = new CRUDApp();
});

function changeLanguage(lang) {
    location.reload();
}

function showAlert(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    const content = document.getElementById('content');
    if (content) {
        content.prepend(alert);

        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
}

function formatDate(dateString) {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES');
    } catch (error) {
        return dateString;
    }
}

function formatCurrency(amount) {
    if (!amount) return '';
    try {
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    } catch (error) {
        return amount;
    }
}

function debugApp() {
    console.log('Estado de la aplicación:', {
        currentView: window.crudApp?.currentView,
        menuItems: document.querySelectorAll('#sidebar a[data-view]').length,
        content: document.getElementById('content')?.innerHTML?.substring(0, 100) + '...'
    });
}

function loadForeignKeyOptions(selectId, foreignTable, displayField, referencedColumnName) {
    const selectElement = document.getElementById(selectId);

    if (!selectElement) {
        console.error('No se encontró el elemento select con ID:', selectId);
        return;
    }

    selectElement.innerHTML = '<option value="">Cargando opciones...</option>';
    selectElement.disabled = true;

    $.ajax({
        url: 'php/' + foreignTable + '.php',
        type: 'POST',
        data: {
            action: 'get_foreign_key_options',
            foreign_table: foreignTable,
            display_field: displayField
        },
        success: function(response) {
            if (response.success && response.data && Array.isArray(response.data)) {
                selectElement.innerHTML = '<option value="">Seleccione...</option>';

                response.data.forEach(function(row) {
                    const option = document.createElement('option');
                    option.value = row.id;
                    option.textContent = row.display_value || row.id;
                    selectElement.appendChild(option);
                });

                selectElement.disabled = false;

                const previousValue = selectElement.getAttribute('data-prev-value');
                if (previousValue) {
                    selectElement.value = previousValue;
                    selectElement.removeAttribute('data-prev-value');
                }
            } else {
                console.error('Error al cargar opciones de clave foránea:', response.error || 'Datos inválidos');
                selectElement.innerHTML = '<option value="">Error al cargar...</option>';
                selectElement.disabled = false;
            }
        },
        error: function(xhr, status, error) {
            console.error('Error de conexión al cargar opciones de clave foránea:', error);
            selectElement.innerHTML = '<option value="">Error de conexión...</option>';
            selectElement.disabled = false;
        }
    });
}

function getLanguageFile() {
    const selectedLanguage = "es";

    const languageMap = {
        'es': 'js/dataTables.spanish.json',
        'en': 'js/dataTables.english.json',
        'fr': 'js/dataTables.french.json'
    };

    return languageMap[selectedLanguage] || languageMap['es'];
}
JS;
    }

    private function generateDatabaseClass() {
        return <<<'PHP'
<?php
class Database {
    private $pdo;
    private $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/config.php';
        $this->connect();
    }

    private function connect() {
        $dbConfig = $this->config['database'];

        try {
            switch ($dbConfig['type']) {
                case 'sqlite':
                    $this->connectSQLite($dbConfig['connection']);
                    break;
                case 'mysql':
                    $this->connectMySQL($dbConfig['connection']);
                    break;
                case 'postgresql':
                    $this->connectPostgreSQL($dbConfig['connection']);
                    break;
                default:
                    throw new Exception("Tipo de base de datos no soportado: " . $dbConfig['type']);
            }

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (PDOException $e) {
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }

    private function connectSQLite($connection) {
        $filePath = __DIR__ . '/../' . ($connection['file'] ?? 'database.sqlite');

        if (!file_exists($filePath)) {
            throw new Exception("Archivo de base de datos no encontrado: " . $filePath);
        }

        $this->pdo = new PDO("sqlite:" . $filePath);
    }

    private function connectMySQL($connection) {
        $dsn = "mysql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $connection['username'], $connection['password']);
    }

    private function connectPostgreSQL($connection) {
        $dsn = "pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}";
        $this->pdo = new PDO($dsn, $connection['username'], $connection['password']);
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error en consulta: " . $e->getMessage());
        }
    }

    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new Exception("Error en ejecución: " . $e->getMessage());
        }
    }

    public function getLastInsertId() {
        return $this->pdo->lastInsertId();
    }
}
?>
PHP;
    }

    private function copyAssets() {
        GeneratorUtils::createDirectory($this->outputDir . '/css');
        GeneratorUtils::createDirectory($this->outputDir . '/css/fonts');
        GeneratorUtils::createDirectory($this->outputDir . '/js');
        GeneratorUtils::createDirectory($this->outputDir . '/assets');
        GeneratorUtils::createDirectory($this->outputDir . '/assets/db');

        $cssFiles = [
            'bootstrap.min.css',
            'bootstrap-icons.css',
            'dataTables.bootstrap5.min.css'
        ];

        foreach ($cssFiles as $cssFile) {
            $source = __DIR__ . '/../../css/' . $cssFile;
            $dest = $this->outputDir . '/css/' . $cssFile;
            if (file_exists($source)) {
                copy($source, $dest);
            }
        }

        $this->copyBootstrapIconsFonts();

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
            $source = __DIR__ . '/../../js/' . $jsFile;
            $dest = $this->outputDir . '/js/' . $jsFile;
            if (file_exists($source)) {
                copy($source, $dest);
            }
        }

        $langSource = __DIR__ . '/../../js/dataTables.spanish.json';
        $langDest = $this->outputDir . '/js/dataTables.spanish.json';
        if (file_exists($langSource)) {
            copy($langSource, $langDest);
        }

        if ($this->appData['databaseType'] === 'sqlite' && isset($this->appData['connectionData']['file'])) {
            $this->copySqliteFile();
        }
    }

    private function copyBootstrapIconsFonts() {
        $sourceFontsDir = __DIR__ . '/../../css/fonts/';

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

    private function copySqliteFile() {
        $connectionData = $this->appData['connectionData'];

        $uploadDir = __DIR__ . '/../../uploads/';

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

        $sourceFile = $latestFile;

        if ($sourceFile && file_exists($sourceFile)) {
            $originalName = $this->getOriginalSqliteFileName($sourceFile);

            $destPath = $this->outputDir . '/assets/db/' . $originalName;

            if (copy($sourceFile, $destPath)) {
                Logger::info("Base de datos SQLite copiada: $originalName");

                $this->updateSqliteConfigPath($originalName);
            } else {
                Logger::error("Error al copiar la base de datos SQLite: $originalName");
                throw new Exception("No se pudo copiar la base de datos SQLite");
            }
        } else {
            Logger::error("Archivo SQLite original no encontrado");
            throw new Exception("Archivo SQLite no encontrado para copiar");
        }
    }

    private function getOriginalSqliteFileName($tempFilePath) {
        $ext = pathinfo($tempFilePath, PATHINFO_EXTENSION);
        if (empty($ext)) {
            $ext = 'db';
        }

        $files = scandir(__DIR__ . '/../../uploads/');
        $tempFileName = basename($tempFilePath);

        $nameParts = explode('_', $tempFileName);
        if (count($nameParts) > 1) {
            return 'original_database.' . $ext;
        } else {
            return 'database.' . $ext;
        }
    }

    private function updateSqliteConfigPath($dbName) {
        $configPath = $this->outputDir . '/config/config.php';
        if (file_exists($configPath)) {
            $config = require $configPath;

            if (isset($config['database']['connection'])) {
                $config['database']['connection']['file'] = "assets/db/$dbName";

                $configContent = "<?php return " . var_export($config, true) . "; ?>";
                file_put_contents($configPath, $configContent);
            }
        }
    }

    private function handleLogoUpload() {
        if (isset($this->appData['appCustomization']['logo']) && $this->appData['appCustomization']['logo']) {
            $logoData = $this->appData['appCustomization']['logo'];

            if (is_array($logoData) && isset($logoData['tmp_name']) && file_exists($logoData['tmp_name'])) {
                $uploadDir = $this->outputDir . '/assets/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileExtension = pathinfo($logoData['name'], PATHINFO_EXTENSION);
                $fileName = 'logo.' . $fileExtension;
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($logoData['tmp_name'], $filePath)) {
                    return 'assets/' . $fileName;
                }
            } elseif (is_string($logoData)) {
                if (file_exists($logoData)) {
                    $uploadDir = $this->outputDir . '/assets/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $fileExtension = pathinfo($logoData, PATHINFO_EXTENSION);
                    $fileName = 'logo.' . $fileExtension;
                    $destPath = $uploadDir . $fileName;

                    if (copy($logoData, $destPath)) {
                        return 'assets/' . $fileName;
                    }
                } elseif (strpos($logoData, 'data:image') === 0) {
                    $uploadDir = $this->outputDir . '/assets/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

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
                            return 'assets/' . $fileName;
                        }
                    }
                }
            }
        }

        $this->createDefaultLogo();
        return 'assets/default-logo.png';
    }

    private function createDefaultLogo() {
        $assetsDir = $this->outputDir . '/assets/';
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }

        $logoPath = $assetsDir . 'default-logo.png';

        $svgContent = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="30" height="30" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg">
    <rect width="30" height="30" fill="#fd7e14" rx="4"/>
    <text x="15" y="20" font-family="Arial, sans-serif" font-size="12" font-weight="bold" fill="white" text-anchor="middle">CRUD</text>
</svg>';

        file_put_contents($logoPath, $svgContent);
    }

    private function createZip() {
        $zip = new ZipArchive();
        $zipPath = $this->outputDir . '/application.zip';

        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $this->addFolderToZip($this->outputDir, $zip);
            $zip->close();

            return 'generated-app/' . basename($this->outputDir) . '/application.zip';
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

    private function cleanupTempFiles() {
        $uploadDir = __DIR__ . '/../../uploads/';

        if (!is_dir($uploadDir)) {
            return;
        }

        $files = scandir($uploadDir);

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = $uploadDir . $file;

                if (is_file($filePath) && strpos($file, 'sqlite_') === 0) {
                    if (unlink($filePath)) {
                        Logger::info("Archivo temporal eliminado: " . $file);
                    } else {
                        Logger::error("No se pudo eliminar archivo temporal: " . $file);
                    }
                }
            }
        }
    }
}