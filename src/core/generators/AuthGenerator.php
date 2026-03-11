<?php
// src/core/generators/AuthGenerator.php

require_once __DIR__ . '/../utils/Common.php';

class AuthGenerator {
    
    private function generateAuthController() {
        return '<?php
require_once \'../config/Database.php\';

class AuthController {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function login($username, $password) {
        // Consulta para obtener el usuario
        $query = "SELECT id, username, password, name FROM user WHERE username = :username";
        $result = $this->db->query($query, [\'username\' => $username]);
        
        if (empty($result)) {
            return [\'success\' => false, \'message\' => \'Usuario o contraseña incorrectos\'];
        }
        
        $user = $result[0];
        
        // Verificar la contraseña
        if (password_verify($password, $user[\'password\'])) {
            // Contraseña correcta, iniciar sesión
            session_start();
            $_SESSION[\'user_id\'] = $user[\'id\'];
            $_SESSION[\'username\'] = $user[\'username\'];
            $_SESSION[\'name\'] = $user[\'name\'] ?? $user[\'username\'];
            $_SESSION[\'authenticated\'] = true;
            
            return [\'success\' => true, \'message\' => \'Inicio de sesión exitoso\'];
        } else {
            return [\'success\' => false, \'message\' => \'Usuario o contraseña incorrectos\'];
        }
    }
    
    public function logout() {
        session_start();
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), \'\', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        return [\'success\' => true, \'message\' => \'Sesión cerrada exitosamente\'];
    }
    
    public function isAuthenticated() {
        session_start();
        return isset($_SESSION[\'authenticated\']) && $_SESSION[\'authenticated\'] === true;
    }
    
    public function getCurrentUser() {
        session_start();
        if ($this->isAuthenticated()) {
            return [
                \'id\' => $_SESSION[\'user_id\'],
                \'username\' => $_SESSION[\'username\'],
                \'name\' => $_SESSION[\'name\']
            ];
        }
        return null;
    }
}

// Manejar solicitudes AJAX
if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {
    header(\'Content-Type: application/json\');
    
    $action = $_POST[\'action\'] ?? \'\';
    $controller = new AuthController();
    
    switch ($action) {
        case \'login\':
            $username = $_POST[\'username\'] ?? \'\';
            $password = $_POST[\'password\'] ?? \'\';
            
            if (empty($username) || empty($password)) {
                echo json_encode([\'success\' => false, \'message\' => \'Usuario y contraseña son requeridos\']);
                exit;
            }
            
            $result = $controller->login($username, $password);
            echo json_encode($result);
            break;
            
        case \'logout\':
            $result = $controller->logout();
            echo json_encode($result);
            break;
            
        case \'check_auth\':
            $isAuthenticated = $controller->isAuthenticated();
            $user = $isAuthenticated ? $controller->getCurrentUser() : null;
            echo json_encode([
                \'authenticated\' => $isAuthenticated,
                \'user\' => $user
            ]);
            break;
            
        default:
            echo json_encode([\'success\' => false, \'message\' => \'Acción no válida\']);
    }
    
    exit();
}';
    }
    
    private function generateLoginView() {
        return '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #fd7e14;
        }
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h2 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: #6c757d;
            margin: 0;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(253, 126, 20, 0.25);
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: #e06b12;
            border-color: #e06b12;
        }
        .alert {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2><i class="bi bi-lock"></i> Iniciar Sesión</h2>
                <p>Accede a tu cuenta para continuar</p>
            </div>

            <form id="loginForm">
                <div class="mb-3">
                    <label for="username" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <div id="loginMessage"></div>

                <button type="submit" class="btn btn-primary w-100">Iniciar Sesión</button>
            </form>
        </div>
    </div>

    <script src="../js/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $("#loginForm").on("submit", function(e) {
                e.preventDefault();

                $.ajax({
                    url: "../php/auth.php",
                    method: "POST",
                    data: {
                        action: "login",
                        username: $("#username").val(),
                        password: $("#password").val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $("#loginMessage").html(\'<div class="alert alert-success">\'+response.message+"</div>");
                            // Redirigir al dashboard después de un breve delay
                            setTimeout(function() {
                                window.location.href = "../index.html";
                            }, 1000);
                        } else {
                            $("#loginMessage").html(\'<div class="alert alert-danger">\'+response.message+"</div>");
                        }
                    },
                    error: function() {
                        $("#loginMessage").html(\'<div class="alert alert-danger">Error de conexión</div>\');
                    }
                });
            });
        });
    </script>
</body>
</html>';
    }
    
    private function generateAuthConfig() {
        return '<?php
// Configuración de autenticación

// Nombre de la tabla de usuarios (debe existir en la base de datos)
define(\'AUTH_USER_TABLE\', \'user\');

// Nombre del campo para el nombre completo (opcional)
define(\'AUTH_NAME_FIELD\', \'name\');

// Nombre del campo para el nombre de usuario
define(\'AUTH_USERNAME_FIELD\', \'username\');

// Nombre del campo para la contraseña
define(\'AUTH_PASSWORD_FIELD\', \'password\');

// Duración de la sesión en segundos (opcional, por defecto 8 horas)
define(\'SESSION_DURATION\', 8 * 60 * 60);
';
    }

    public function generateAuthFiles($appData) {
        $authEnabled = $appData['authEnabled'] ?? false;

        if (!$authEnabled) {
            return []; // No generar archivos de autenticación si no está habilitada
        }

        $files = [];

        // Archivo de controlador de autenticación
        $authController = $this->generateAuthController();
        $files['php/auth.php'] = $authController;

        // Vista de login
        $loginView = $this->generateLoginView();
        $files['templates/login.html'] = $loginView;

        // Archivo de configuración de autenticación
        $authConfig = $this->generateAuthConfig();
        $files['config/auth.php'] = $authConfig;

        return $files;
    }
}