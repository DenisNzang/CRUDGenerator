<?php
// src/core/generators/TemplateGenerator.php

// Verificar autenticación si está habilitada
if (isset($this->appData['authEnabled']) && $this->appData['authEnabled']) {
    // Incluir la verificación de autenticación
    $authCheck = '
    // Verificar autenticación si está habilitada
    if (!$this->isAuthenticated()) {
        http_response_code(401);
        echo json_encode([\'success\' => false, \'error\' => \'No autenticado\']);
        exit;
    }';
    $authInclude = '
require_once \'../config/auth.php\';';
} else {
    // Si la autenticación no está habilitada, no insertar nada
    $authCheck = '';
    $authInclude = '';
}