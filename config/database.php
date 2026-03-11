<?php
// config/database.php

// Configuración específica de base de datos
return [
    'default_connection' => 'mysql', // sqlite, mysql, postgresql
    
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => '3306',
            'database' => '',
            'username' => '',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ],
        'postgresql' => [
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => '5432',
            'database' => '',
            'username' => '',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
        ]
    ]
];