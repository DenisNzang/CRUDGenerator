# Generador de Aplicaciones CRUD

## Descripción
Herramienta web para generar aplicaciones CRUD (Crear, Leer, Actualizar, Eliminar) completas a partir de estructuras de base de datos existentes.

## Estructura de Directorios
```
generator/
├── src/                          # Código fuente principal
│   ├── core/                     # Componentes centrales del sistema
│   │   ├── config/               # Archivos de configuración
│   │   ├── database/             # Manejo de conexiones y análisis
│   │   ├── generators/           # Generadores de aplicaciones
│   │   └── utils/                # Utilidades comunes
│   ├── frontend/                 # Código frontend
│   │   ├── controllers/          # Controladores frontend
│   │   ├── models/               # Modelos frontend
│   │   └── views/                # Vistas y componentes
│   └── shared/                   # Recursos compartidos
│       ├── assets/               # Recursos estáticos
│       └── templates/            # Plantillas generales
├── api/                          # Endpoints de la API
│   ├── database/
│   ├── generation/
│   └── middleware/
├── config/                       # Configuración del sistema
├── scripts/                      # Scripts de utilidad
├── logs/                         # Archivos de log
├── uploads/                      # Archivos temporales
└── generated-app/                # Aplicaciones generadas
```

## Características
- Soporte para múltiples bases de datos: SQLite, MySQL y PostgreSQL
- Interfaz de usuario en 7 pasos para configurar la aplicación
- Generación automática de código PHP/HTML/JavaScript
- Personalización de campos, vistas y estilo
- Generación de aplicaciones como archivos ZIP descargables

## Requisitos
- PHP 7.4 o superior
- Extensiones PHP: PDO, PDO_SQLITE, PDO_MYSQL, PDO_PGSQL, Zip
- Servidor web (Apache, Nginx, etc.)

## Instalación
1. Copie los archivos del generador a su servidor web
2. Ejecute el script de inicialización: `php scripts/init.php`
3. Asegúrese de que los directorios `uploads/`, `generated-app/` y `logs/` tengan permisos de escritura
4. Acceda al archivo `index.html` desde su navegador

## Seguridad
- Validación de entradas de usuario
- Sanitización de consultas SQL
- Validación de tipos de archivos subidos
- Protección contra XSS e inyección SQL
- Logging de operaciones

## API Endpoints
- `api/database/connection`: Gestión de conexiones a base de datos
- `api/database/analyzer`: Análisis de estructuras de base de datos
- `api/generation/app`: Generación de aplicaciones CRUD

## Contribuciones
Las contribuciones son bienvenidas. Por favor, siga las siguientes pautas:
- Siga el estilo de codificación existente
- Asegúrese de probar todas las funcionalidades antes de enviar cambios
- Documente cualquier cambio importante

## Licencia
Distribuido bajo licencia [MIT].
