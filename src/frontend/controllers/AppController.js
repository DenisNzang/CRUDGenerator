class CRUDGeneratorApp {
    constructor() {
        // Inicializar variables con valores por defecto que luego se sobrescribirán si hay estado guardado
        this.currentStep = 1;
        this.totalSteps = 9;
        this.databaseType = null;
        this.connectionData = {};
        this.databaseStructure = {};
        this.selectedTables = [];
        this.customQueries = [];
        this.fieldConfigurations = {};
        this.appCustomization = {};

        // Verificar dependencias primero
        this.checkDependencies();

        // Vincular eventos para cargar proyecto
        this.bindProjectEvents();

        // Restaurar estado desde localStorage si existe - ESTO CAMBIARÁ LOS VALORES INICIALIZADOS ANTES
        this.loadState();

        this.init();
    }

    bindProjectEvents() {
        // Vincular evento para cargar archivo de proyecto
        const projectFileInput = document.getElementById('projectFileInput');
        if (projectFileInput) {
            projectFileInput.addEventListener('change', (e) => {
                this.loadProjectFromFile(e.target.files[0]);
            });
        }
    }

    async loadProjectFromFile(file) {
        if (!file) {
            showAlert('Por favor, selecciona un archivo de proyecto', 'warning');
            return;
        }

        if (!file.name.endsWith('.sti')) {
            showAlert('El archivo debe tener extensión .sti', 'warning');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const projectData = JSON.parse(e.target.result);

                // Cargar los datos del proyecto en las propiedades de la aplicación
                this.databaseType = projectData.databaseType;
                this.connectionData = projectData.connectionData || {};
                this.databaseStructure = projectData.databaseStructure || {};
                this.selectedTables = projectData.selectedTables || [];
                this.customQueries = projectData.customQueries || [];
                this.fieldConfigurations = projectData.fieldConfigurations || {};
                this.appCustomization = projectData.appCustomization || {};
                this.authEnabled = projectData.authEnabled || false;

                // Actualizar la interfaz con los datos cargados
                this.updateInterfaceFromProject(projectData);

                // Avanzar al siguiente paso o al paso correspondiente
                this.currentStep = 3; // Ir al paso de análisis
                this.showStep(this.currentStep);
                this.updateNavigation();
                this.updateStepper(this.currentStep);

                showAlert('Proyecto cargado exitosamente', 'success');
            } catch (error) {
                showAlert('Error al cargar el proyecto: ' + error.message, 'danger');
            }
        };

        reader.onerror = () => {
            showAlert('Error al leer el archivo de proyecto', 'danger');
        };

        reader.readAsText(file);
    }

    updateInterfaceFromProject(projectData) {
        // Actualizar tipo de base de datos
        if (projectData.databaseType) {
            document.querySelectorAll('input[name="databaseType"]').forEach(radio => {
                if (radio.value === projectData.databaseType) {
                    radio.checked = true;
                }
            });
            this.handleDatabaseTypeChange(projectData.databaseType);
        }

        // Actualizar datos de conexión
        if (projectData.connectionData) {
            if (projectData.databaseType === 'sqlite') {
                // Para SQLite, manejar el archivo
                if (projectData.connectionData.file) {
                    // Aquí se manejaría la carga del archivo SQLite
                }
            } else {
                // Para MySQL/PostgreSQL, actualizar campos
                if (projectData.connectionData.host) document.getElementById('host').value = projectData.connectionData.host;
                if (projectData.connectionData.port) document.getElementById('port').value = projectData.connectionData.port;
                if (projectData.connectionData.database) document.getElementById('database').value = projectData.connectionData.database;
                if (projectData.connectionData.schema) document.getElementById('schema').value = projectData.connectionData.schema;
                if (projectData.connectionData.username) document.getElementById('username').value = projectData.connectionData.username;
            }
        }

        // Actualizar tablas seleccionadas
        if (projectData.selectedTables && Array.isArray(projectData.selectedTables)) {
            this.selectedTables = projectData.selectedTables;
            // Actualizar checkboxes en la interfaz
            projectData.selectedTables.forEach(tableName => {
                const checkbox = document.getElementById(`table-${tableName}`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
        }

        // Actualizar consultas personalizadas
        if (projectData.customQueries && Array.isArray(projectData.customQueries)) {
            this.customQueries = projectData.customQueries;
            // Actualizar la interfaz de consultas personalizadas
            this.updateCustomQueriesUI();
        }

        // Actualizar configuración de campos
        if (projectData.fieldConfigurations) {
            this.fieldConfigurations = projectData.fieldConfigurations;
        }

        // Actualizar personalización de la aplicación
        if (projectData.appCustomization) {
            if (projectData.appCustomization.title) document.getElementById('appTitle').value = projectData.appCustomization.title;
            if (projectData.appCustomization.primaryColor) document.getElementById('primaryColor').value = projectData.appCustomization.primaryColor;
        }

        // Actualizar autenticación
        if (projectData.authEnabled !== undefined) {
            document.getElementById('enableAuth').checked = projectData.authEnabled;
            const authConfigDiv = document.getElementById('authConfig');
            if (authConfigDiv) {
                if (projectData.authEnabled) {
                    authConfigDiv.classList.remove('d-none');
                } else {
                    authConfigDiv.classList.add('d-none');
                }
            }
        }
    }

    checkDependencies() {
        if (typeof $ === 'undefined') {
            throw new Error('jQuery no está cargado');
        }
        if (typeof bootstrap === 'undefined') {
            console.warn('Bootstrap no está cargado');
        }
        // DataTables se verifica cuando se usa
    }

    init() {
        // Inicializar con valores por defecto solo si no han sido restaurados
        if (this.currentStep === undefined) {
            this.currentStep = 1;
        }
        if (this.databaseType === undefined) {
            this.databaseType = null;
        }
        if (this.connectionData === undefined) {
            this.connectionData = {};
        }
        if (this.databaseStructure === undefined) {
            this.databaseStructure = {};
        }
        if (this.selectedTables === undefined) {
            this.selectedTables = [];
        }
        if (this.customQueries === undefined) {
            this.customQueries = [];
        }
        if (this.fieldConfigurations === undefined) {
            this.fieldConfigurations = {};
        }
        if (this.appCustomization === undefined) {
            this.appCustomization = {};
        }

        this.bindEvents();
        this.updateNavigation();
        this.showStep(this.currentStep);

        // Guardar estado inicial
        this.saveState();
    }

    bindEvents() {
        // Navegación entre pasos
        document.getElementById('nextStep').addEventListener('click', () => this.nextStep());
        document.getElementById('prevStep').addEventListener('click', () => this.prevStep());

        // Selección de tipo de base de datos
        document.querySelectorAll('input[name="databaseType"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.handleDatabaseTypeChange(e.target.value);
                this.saveState();
            });
        });

        // Prueba de conexión
        const testConnectionBtn = document.getElementById('testConnection');
        if (testConnectionBtn) {
            testConnectionBtn.addEventListener('click', () => this.testConnection());
        }

        // Agregar consultas personalizadas
        const addQueryBtn = document.getElementById('addQuery');
        if (addQueryBtn) {
            addQueryBtn.addEventListener('click', () => this.addCustomQuery());
        }

        // Generar aplicación
        const generateAppBtn = document.getElementById('generateApp');
        if (generateAppBtn) {
            generateAppBtn.addEventListener('click', () => this.generateApplication());
        }

        // Vista previa del logo
        const appLogoInput = document.getElementById('appLogo');
        if (appLogoInput) {
            appLogoInput.addEventListener('change', (e) => {
                this.previewLogo(e.target.files[0]);
                this.saveState();
            });
        }

        // Agregar eventos para guardar estado en campos importantes
        this.bindFieldEvents();

        // Vincular eventos para el stepper lateral
        this.bindStepperEvents();

        // Vincular evento específico para el logo
        this.bindLogoEvent();

        // Vincular eventos para autenticación
        this.bindAuthEvents();
    }

    bindAuthEvents() {
        const enableAuthCheckbox = document.getElementById('enableAuth');
        const authConfigDiv = document.getElementById('authConfig');

        if (enableAuthCheckbox && authConfigDiv) {
            enableAuthCheckbox.addEventListener('change', (e) => {
                if (e.target.checked) {
                    authConfigDiv.classList.remove('d-none');
                } else {
                    authConfigDiv.classList.add('d-none');
                }
                this.saveState();
            });
        }
    }

    loadAuthStep() {
        const enableAuthCheckbox = document.getElementById('enableAuth');
        if (enableAuthCheckbox) {
            // Cargar el estado de autenticación desde this.appData
            const authEnabled = this.appData?.authEnabled || false;
            enableAuthCheckbox.checked = authEnabled;

            const authConfigDiv = document.getElementById('authConfig');
            if (authConfigDiv) {
                if (enableAuthCheckbox.checked) {
                    authConfigDiv.classList.remove('d-none');
                } else {
                    authConfigDiv.classList.add('d-none');
                }
            }
        }
    }

    bindLogoEvent() {
        const appLogoInput = document.getElementById('appLogo');
        if (appLogoInput) {
            appLogoInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    // Guardar el archivo en la propiedad correspondiente
                    this.appCustomization.logo = file;
                    this.previewLogo(file);
                }
                this.saveState();
            });
        }
    }

    bindStepperEvents() {
        // Agregar evento click a cada item del stepper
        document.querySelectorAll('.stepper-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const stepNumber = parseInt(item.getAttribute('data-step'));
                this.navigateToStep(stepNumber);
            });
        });
    }

    navigateToStep(stepNumber) {
        if (stepNumber >= 1 && stepNumber <= this.totalSteps) {
            // Validar el paso actual solo si estamos navegando hacia atrás o a un paso ya completado
            if (stepNumber < this.currentStep || this.isStepValid(stepNumber)) {
                // Si vamos hacia adelante, validar que los pasos anteriores estén completos
                if (stepNumber > this.currentStep && !this.validateStepsRange(this.currentStep, stepNumber)) {
                    alert('Debes completar los pasos anteriores antes de continuar');
                    return;
                }

                this.currentStep = stepNumber;
                this.showStep(this.currentStep);
                this.updateNavigation();
                this.updateStepper(this.currentStep);

                // Ejecutar acciones específicas del paso si es necesario
                this.handleStepActions(this.currentStep);

                this.saveState();
            } else {
                alert('Debes completar este paso antes de continuar');
            }
        }
    }

    isStepValid(stepNumber) {
        // Un paso es válido si ya ha sido completado previamente
        // En este caso, asumimos que se puede navegar a pasos anteriores sin validación adicional
        return stepNumber <= this.currentStep || this.validateStepUpTo(stepNumber);
    }

    validateStepsRange(fromStep, toStep) {
        // Validar que todos los pasos intermedios estén completos
        for (let i = fromStep; i < toStep; i++) {
            if (!this.validateStepUpTo(i + 1)) {
                return false;
            }
        }
        return true;
    }

    validateStepUpTo(step) {
        // Validar pasos básicos sin mostrar mensajes de error
        switch (step) {
            case 1:
                return this.databaseType !== null;
            case 2:
                // Validar que la información de conexión esté completa
                const connectionData = this.getConnectionData();
                if (this.databaseType === 'sqlite') {
                    return connectionData.file !== null && connectionData.file !== undefined;
                } else {
                    return connectionData.host && connectionData.port && connectionData.database && connectionData.username;
                }
            case 3:
                return this.selectedTables.length > 0;
            default:
                return true;
        }
    }

    bindFieldEvents() {
        // Guardar estado cuando cambian campos importantes
        const importantFields = [
            '#host', '#port', '#database', '#username', '#password',
            '#schema', '#appTitle', '#primaryColor'
        ];

        importantFields.forEach(selector => {
            const element = document.querySelector(selector);
            if (element) {
                element.addEventListener('input', () => this.saveState());
                element.addEventListener('change', () => this.saveState());
            }
        });
    }

    saveState() {
        // No guardar estado entre sesiones
        // Esta función está vacía para evitar persistencia
    }

    loadState() {
        // No restaurar configuraciones entre sesiones
        // Iniciar siempre con valores por defecto
        this.currentStep = 1;
        this.databaseType = null;
        this.connectionData = {};
        this.databaseStructure = {};
        this.selectedTables = [];
        this.customQueries = [];
        this.fieldConfigurations = {};
        this.appCustomization = {};

        console.log('Generador iniciado con estado limpio');
    }

    clearState() {
        try {
            localStorage.removeItem('crudGeneratorState');
        } catch (e) {
            console.warn('No se pudo limpiar el estado de localStorage:', e);
        }
    }

    handleDatabaseTypeChange(type) {
        this.databaseType = type;
        // Mostrar configuración apropiada
        document.querySelectorAll('.database-config').forEach(el => el.classList.add('d-none'));
        if (type === 'sqlite') {
            const sqliteConfig = document.getElementById('sqlite-config');
            if (sqliteConfig) sqliteConfig.classList.remove('d-none');
        } else {
            const serverConfig = document.getElementById('server-config');
            if (serverConfig) serverConfig.classList.remove('d-none');
        }
    }

    async testConnection() {
        const statusElement = document.getElementById('connectionStatus');
        if (!statusElement) return;

        statusElement.innerHTML = '<div class="alert alert-info">Probando conexión...</div>';

        try {
            const connectionData = this.getConnectionData();

            // Verificar si DatabaseManager existe
            if (typeof DatabaseManager === 'undefined') {
                throw new Error('DatabaseManager no está disponible');
            }

            const result = await DatabaseManager.testConnection(this.databaseType, connectionData);

            if (result.success) {
                statusElement.innerHTML = '<div class="alert alert-success">✓ Conexión exitosa</div>';
            } else {
                statusElement.innerHTML = `<div class="alert alert-danger">✗ Error: ${result.error}</div>`;
            }
        } catch (error) {
            statusElement.innerHTML = `<div class="alert alert-danger">✗ Error: ${error.message}</div>`;
        }
    }

    getConnectionData() {
        if (this.databaseType === 'sqlite') {
            const fileInput = document.getElementById('sqliteFile');
            return {
                file: fileInput ? fileInput.files[0] : null
            };
        } else {
            return {
                host: document.getElementById('host')?.value || '',
                port: document.getElementById('port')?.value || '',
                database: document.getElementById('database')?.value || '',
                schema: document.getElementById('schema')?.value || 'public',
                username: document.getElementById('username')?.value || '',
                password: document.getElementById('password')?.value || ''
            };
        }
    }

    nextStep() {
        if (this.validateCurrentStep()) {
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.showStep(this.currentStep);
                this.updateNavigation();
                // Ejecutar acciones específicas del paso
                this.handleStepActions(this.currentStep);
            }
        }
    }

    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
            this.updateNavigation();
        }
    }

    showStep(step) {
        // Ocultar todos los pasos
        document.querySelectorAll('.step-content').forEach(el => {
            el.classList.add('d-none');
        });
        // Mostrar paso actual
        const currentStepElement = document.getElementById(`step-${step}`);
        if (currentStepElement) {
            currentStepElement.classList.remove('d-none');
        }
        // Actualizar stepper
        this.updateStepper(step);

        // Controlar visibilidad de paneles según el paso
        this.updatePanelsVisibility(step);

        // Si estamos en el paso de autenticación, cargar la configuración actual
        if (step === 7) {
            this.loadAuthStep();
        }

        // Si estamos en el paso de guardar proyecto, cargar la configuración
        if (step === 8) {
            this.loadSaveProjectStep();
        }
    }

    updatePanelsVisibility(step) {
        // Mostrar panel de carga de proyecto solo en el primer paso
        const projectLoadTop = document.getElementById('projectLoadTop');
        const projectLoadPanel = document.getElementById('projectLoadPanel');

        if (projectLoadTop) {
            if (step === 1) {
                projectLoadTop.classList.remove('d-none');
            } else {
                projectLoadTop.classList.add('d-none');
            }
        }

        if (projectLoadPanel) {
            if (step === 1) {
                projectLoadPanel.classList.remove('d-none');
            } else {
                projectLoadPanel.classList.add('d-none');
            }
        }
    }

    updateStepper(step) {
        document.querySelectorAll('.stepper-item').forEach((item, index) => {
            const stepNumber = index + 1;
            item.classList.remove('active', 'completed');
            if (stepNumber < step) {
                item.classList.add('completed');
            } else if (stepNumber === step) {
                item.classList.add('active');
            }
        });
    }

    updateNavigation() {
        const prevButton = document.getElementById('prevStep');
        const nextButton = document.getElementById('nextStep');

        if (!prevButton || !nextButton) return;

        // Actualizar texto del botón siguiente en el último paso
        if (this.currentStep === this.totalSteps) {
            nextButton.classList.add('d-none');
        } else {
            nextButton.classList.remove('d-none');
            nextButton.innerHTML = `Siguiente <i class="bi bi-arrow-right"></i>`;
        }

        // Ocultar botón anterior en el primer paso
        if (this.currentStep === 1) {
            prevButton.classList.add('d-none');
        } else {
            prevButton.classList.remove('d-none');
        }
    }

    validateCurrentStep() {
        switch (this.currentStep) {
            case 1:
                return this.validateStep1();
            case 2:
                return this.validateStep2();
            case 3:
                return this.validateStep3();
            default:
                return true;
        }
    }

    validateStep1() {
        if (!this.databaseType) {
            alert('Por favor, selecciona un tipo de base de datos');
            return false;
        }
        return true;
    }

    validateStep2() {
        const connectionData = this.getConnectionData();
        let isValid = true;
        let errorMessage = '';

        if (this.databaseType === 'sqlite') {
            if (!connectionData.file) {
                errorMessage = 'Por favor, selecciona un archivo de base de datos SQLite';
                isValid = false;
            }
        } else {
            const required = ['host', 'port', 'database', 'username'];
            for (const field of required) {
                if (!connectionData[field]) {
                    errorMessage = `Por favor, completa el campo: ${field}`;
                    isValid = false;
                    break;
                }
            }

            // Validación adicional de formato
            if (connectionData.host && !this.isValidHost(connectionData.host)) {
                errorMessage = 'Formato de host no válido';
                isValid = false;
            }

            if (connectionData.port && !this.isValidPort(connectionData.port)) {
                errorMessage = 'Puerto no válido (debe estar entre 1 y 65535)';
                isValid = false;
            }
        }

        if (!isValid) {
            alert(errorMessage);
        }

        return isValid;
    }

    isValidHost(host) {
        // Validar dirección IP o nombre de dominio
        const ipRegex = /^(\d{1,3}\.){3}\d{1,3}$/;
        const domainRegex = /^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;

        if (ipRegex.test(host)) {
            // Validar rango de IP
            const parts = host.split('.');
            return parts.every(part => parseInt(part) >= 0 && parseInt(part) <= 255);
        }

        return domainRegex.test(host);
    }

    isValidPort(port) {
        const portNum = parseInt(port);
        return !isNaN(portNum) && portNum >= 1 && portNum <= 65535;
    }

    validateStep3() {
        if (this.selectedTables.length === 0) {
            alert('Debes seleccionar al menos una tabla para continuar');
            return false;
        }
        return true;
    }

    async handleStepActions(step) {
        switch (step) {
            case 3:
                await this.analyzeDatabase();
                break;
            case 5:
                this.showFieldsConfiguration();
                break;
            case 7: // Paso de autenticación
                this.loadAuthStep();
                break;
            case 8: // Paso de guardar proyecto
                this.loadSaveProjectStep();
                break;
            case 9: // Paso de generación
                this.loadGenerationStep();
                break;
        }
    }

    async analyzeDatabase() {
        try {
            const connectionData = this.getConnectionData();

            // Mostrar estado de análisis
            const analysisLoading = document.getElementById('analysisLoading');
            const analysisResults = document.getElementById('analysisResults');

            if (analysisLoading) analysisLoading.classList.remove('d-none');
            if (analysisResults) analysisResults.classList.add('d-none');

            // Verificar si DatabaseManager existe
            if (typeof DatabaseManager === 'undefined') {
                throw new Error('DatabaseManager no está disponible para analizar la base de datos');
            }

            this.databaseStructure = await DatabaseManager.analyzeDatabase(this.databaseType, connectionData);

            // Ocultar spinner y mostrar resultados
            if (analysisLoading) analysisLoading.classList.add('d-none');
            if (analysisResults) analysisResults.classList.remove('d-none');

            // Actualizar lista de tablas con checkboxes
            this.updateTablesList();

        } catch (error) {
            // Ocultar elementos de carga
            const analysisLoading = document.getElementById('analysisLoading');
            if (analysisLoading) analysisLoading.classList.add('d-none');

            alert(`Error al analizar la base de datos: ${error.message}`);
            this.prevStep(); // Volver al paso anterior en caso de error
        }
    }

    updateTablesList() {
        const tablesList = document.getElementById('analysisResults');
        if (!tablesList) return;

        tablesList.innerHTML = '';

        if (!this.databaseStructure.tables || Object.keys(this.databaseStructure.tables).length === 0) {
            tablesList.innerHTML = '<div class="alert alert-warning">No se encontraron tablas en la base de datos</div>';
            return;
        }

        // Agregar instrucciones
        const instructions = document.createElement('div');
        instructions.className = 'alert alert-warning mb-4';
        instructions.innerHTML = `
            <i class="bi bi-check-square"></i>
            <strong>Selecciona las tablas</strong> que deseas incluir en tu aplicación CRUD marcando las casillas correspondientes.
        `;
        tablesList.appendChild(instructions);

        // Crear lista simple de tablas con checkboxes
        const tableList = document.createElement('div');
        tableList.className = 'list-group';

        Object.keys(this.databaseStructure.tables).forEach(tableName => {
            const table = this.databaseStructure.tables[tableName];
            const isChecked = this.selectedTables.includes(tableName);

            const listItem = document.createElement('div');
            listItem.className = 'list-group-item d-flex align-items-center';

            listItem.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input table-checkbox"
                           type="checkbox"
                           value="${tableName}"
                           id="table-${tableName}"
                           ${isChecked ? 'checked' : ''}>
                    <label class="form-check-label fw-bold" for="table-${tableName}">
                        ${tableName}
                    </label>
                </div>
            `;

            tableList.appendChild(listItem);
        });

        tablesList.appendChild(tableList);

        // Vincular eventos de los checkboxes
        this.bindTableCheckboxEvents();
    }

    bindTableCheckboxEvents() {
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('table-checkbox')) {
                this.handleTableSelection(e.target.value, e.target.checked);
            }
        });
    }

    handleTableSelection(tableName, selected) {
        if (selected) {
            if (!this.selectedTables.includes(tableName)) {
                this.selectedTables.push(tableName);
            }
        } else {
            this.selectedTables = this.selectedTables.filter(name => name !== tableName);
        }
        console.log('Tablas seleccionadas:', this.selectedTables);
    }

    generateAnalysisTableFields(tableName, table) {
        if (!table.columns || table.columns.length === 0) {
            return '<div class="text-muted">No se encontraron columnas en esta tabla</div>';
        }

        let html = `
            <div class="row">
                <div class="col-12">
                    <h6 class="text-primary mb-3">Estructura detallada de <code>${tableName}</code></h6>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Campo</th>
                            <th>Tipo</th>
                            <th>Nulo</th>
                            <th>Valor por defecto</th>
                            <th>Clave</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        table.columns.forEach(column => {
            const isPrimaryKey = column.primaryKey || false;
            const isForeignKey = table.foreignKeys ? table.foreignKeys.some(fk => fk.column === column.name) : false;

            let keyBadge = '';
            if (isPrimaryKey) {
                keyBadge = '<span class="badge bg-danger">PRIMARY</span>';
            } else if (isForeignKey) {
                keyBadge = '<span class="badge bg-info">FOREIGN</span>';
            }

            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <strong>${column.name}</strong>
                            ${isPrimaryKey ? '<i class="bi bi-key text-danger ms-2" title="Clave primaria"></i>' : ''}
                            ${isForeignKey ? '<i class="bi bi-link-45deg text-info ms-2" title="Clave foránea"></i>' : ''}
                        </div>
                    </td>
                    <td><code class="small">${column.type}</code></td>
                    <td class="text-center">${column.nullable ? '<span class="badge bg-success">SÍ</span>' : '<span class="badge bg-warning">NO</span>'}</td>
                    <td class="text-center">${column.default ? `<code class="small">${column.default}</code>` : '<span class="text-muted">-</span>'}</td>
                    <td class="text-center">${keyBadge || '<span class="text-muted">-</span>'}</td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        // Información adicional de la tabla
        if (table.foreignKeys && table.foreignKeys.length > 0) {
            html += `
                <div class="row mt-4">
                    <div class="col-12">
                        <h6 class="text-info mb-2"><i class="bi bi-diagram-3 me-2"></i>Relaciones foráneas</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-info">
                                    <tr>
                                        <th>Columna local</th>
                                        <th>Tabla referenciada</th>
                                        <th>Columna referenciada</th>
                                    </tr>
                                </thead>
                                <tbody>
            `;

            table.foreignKeys.forEach(fk => {
                html += `
                    <tr>
                        <td><code class="text-primary">${fk.column}</code></td>
                        <td><code class="text-success">${fk.referenced_table}</code></td>
                        <td><code class="text-info">${fk.referenced_column}</code></td>
                    </tr>
                `;
            });

            html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }

        // Resumen de la tabla
        const totalColumns = table.columns ? table.columns.length : 0;
        const primaryKeys = table.columns ? table.columns.filter(col => col.primaryKey).length : 0;
        const foreignKeys = table.foreignKeys ? table.foreignKeys.length : 0;
        const notNullColumns = table.columns ? table.columns.filter(col => !col.nullable).length : 0;

        html += `
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 bg-light">
                        <div class="card-header bg-transparent border-0">
                            <h6 class="mb-0 text-dark"><i class="bi bi-graph-up me-2"></i>Resumen de la tabla</h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row text-center">
                                <div class="col-3">
                                    <div class="fw-bold text-primary fs-5">${totalColumns}</div>
                                    <small class="text-muted">Campos totales</small>
                                </div>
                                <div class="col-3">
                                    <div class="fw-bold text-success fs-5">${primaryKeys}</div>
                                    <small class="text-muted">Claves primarias</small>
                                </div>
                                <div class="col-3">
                                    <div class="fw-bold text-info fs-5">${foreignKeys}</div>
                                    <small class="text-muted">Relaciones</small>
                                </div>
                                <div class="col-3">
                                    <div class="fw-bold text-warning fs-5">${notNullColumns}</div>
                                    <small class="text-muted">NOT NULL</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        return html;
    }

    addCustomQuery() {
        const container = document.getElementById('customQueriesContainer');
        if (!container) return;

        const queryId = 'query_' + Date.now();

        const queryItem = document.createElement('div');
        queryItem.className = 'query-item card mb-3';
        queryItem.setAttribute('data-query-id', queryId);
        queryItem.innerHTML = `
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Nombre de la consulta</label>
                        <input type="text" class="form-control query-name" placeholder="Ej: Ventas por mes">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tipo</label>
                        <select class="form-select query-type">
                            <option value="readonly">Solo lectura</option>
                            <option value="editable">Editable</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">Consulta SQL</label>
                    <textarea class="form-control query-sql" rows="3" placeholder="SELECT * FROM tabla WHERE condición"></textarea>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger mt-2 remove-query">
                    <i class="bi bi-trash"></i> Eliminar
                </button>
            </div>
        `;

        container.appendChild(queryItem);

        // Inmediatamente agregar la consulta al array con valores vacíos para que esté disponible
        if (!this.customQueries.some(q => q.id === queryId)) {
            this.customQueries.push({ id: queryId, name: '', type: 'readonly', sql: '' });
        }

        // Vincular evento de eliminación
        queryItem.querySelector('.remove-query').addEventListener('click', () => {
            queryItem.remove();
            this.customQueries = this.customQueries.filter(q => q.id !== queryId);
        });

        // Vincular eventos de entrada para guardar datos
        const inputs = queryItem.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('change', () => this.saveCustomQueryData(queryId));
            input.addEventListener('input', () => this.saveCustomQueryData(queryId));
        });
    }

    saveCustomQueryData(queryId) {
        const queryElement = document.querySelector(`[data-query-id="${queryId}"]`);
        if (queryElement) {
            const name = queryElement.querySelector('.query-name').value;
            const type = queryElement.querySelector('.query-type').value;
            const sql = queryElement.querySelector('.query-sql').value;

            // Permitir guardar consultas aunque estén temporalmente vacías o incompletas
            const existingIndex = this.customQueries.findIndex(q => q.id === queryId);

            if (name && sql) {
                // Si tiene nombre y SQL, actualizar/agregar normalmente
                if (existingIndex >= 0) {
                    this.customQueries[existingIndex] = { id: queryId, name, type, sql };
                } else {
                    this.customQueries.push({ id: queryId, name, type, sql });
                }
            } else if (existingIndex >= 0) {
                // Si la consulta ya existe pero ahora está vacía, removerla
                this.customQueries.splice(existingIndex, 1);
            }
        }
        console.log('Consultas personalizadas:', this.customQueries);
    }

    showFieldsConfiguration() {
        const container = document.getElementById('fieldsConfiguration');
        if (!container) return;

        container.innerHTML = '';

        if (this.selectedTables.length === 0 && this.customQueries.length === 0) {
            container.innerHTML = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    No hay tablas ni consultas seleccionadas para configurar.
                </div>
            `;
            return;
        }

        // Crear pestañas
        const navTabs = document.createElement('ul');
        navTabs.className = 'nav nav-tabs mb-3';
        navTabs.id = 'fieldsConfigTabs';

        const tabContent = document.createElement('div');
        tabContent.className = 'tab-content';
        tabContent.id = 'fieldsConfigContent';

        let isFirstTab = true; // Bandera para saber si es la primera pestaña que se debe activar

        // Pestañas para tablas seleccionadas
        this.selectedTables.forEach((tableName) => {
            const isActive = isFirstTab;
            if (isFirstTab) isFirstTab = false; // Ya encontramos la primera pestaña activa

            // Pestaña
            const navItem = document.createElement('li');
            navItem.className = 'nav-item';
            navItem.innerHTML = `
                <button class="nav-link ${isActive ? 'active' : ''}" data-bs-toggle="tab"
                        data-bs-target="#tab-${this.sanitizeId(tableName)}" type="button">
                    ${tableName}
                </button>
            `;
            navTabs.appendChild(navItem);

            // Contenido de la pestaña
            const tabPane = document.createElement('div');
            tabPane.className = `tab-pane fade ${isActive ? 'show active' : ''}`;
            tabPane.id = `tab-${this.sanitizeId(tableName)}`;

            const tableConfig = this.databaseStructure.tables[tableName];
            if (tableConfig) {
                tabPane.innerHTML = this.generateFieldsConfiguration(tableName, tableConfig);
            } else {
                tabPane.innerHTML = `<div class="alert alert-warning">No se encontró información para la tabla ${tableName}</div>`;
            }
            tabContent.appendChild(tabPane);
        });

        // Pestañas para consultas personalizadas
        this.customQueries.forEach((query, index) => {
            const isActive = isFirstTab; // Será activa si es la primera pestaña (cuando no hay tablas) o la primera consulta después de las tablas
            if (isFirstTab) isFirstTab = false; // Ya encontramos la primera pestaña activa

            const queryId = this.sanitizeId('query-' + query.id);

            const navItem = document.createElement('li');
            navItem.className = 'nav-item';
            navItem.innerHTML = `
                <button class="nav-link ${isActive ? 'active' : ''}" data-bs-toggle="tab"
                        data-bs-target="#tab-${queryId}" type="button">
                    ${query.name || 'Consulta ' + (index + 1)}
                </button>
            `;
            navTabs.appendChild(navItem);

            const tabPane = document.createElement('div');
            tabPane.className = `tab-pane fade ${isActive ? 'show active' : ''}`;
            tabPane.id = `tab-${queryId}`;
            tabPane.innerHTML = this.generateQueryFieldsConfiguration(query);
            tabContent.appendChild(tabPane);
        });

        container.appendChild(navTabs);
        container.appendChild(tabContent);

        // Vincular eventos después de crear la interfaz
        setTimeout(() => {
            this.bindFieldConfigEvents();
        }, 100);
    }

    bindFieldConfigEvents() {
        document.addEventListener('change', (e) => {
            if (e.target.hasAttribute('data-table') && e.target.hasAttribute('data-field')) {
                const configType = e.target.getAttribute('data-type') || e.target.type;
                const value = e.target.type === 'checkbox' ? e.target.checked : e.target.value;

                this.saveFieldConfiguration(
                    e.target.getAttribute('data-table'),
                    e.target.getAttribute('data-field'),
                    configType,
                    value
                );
            }
        });

        document.addEventListener('input', (e) => {
            if (e.target.hasAttribute('data-table') && e.target.hasAttribute('data-field') && e.target.type !== 'checkbox') {
                this.saveFieldConfiguration(
                    e.target.getAttribute('data-table'),
                    e.target.getAttribute('data-field'),
                    e.target.getAttribute('data-type') || e.target.type,
                    e.target.value
                );
            }
        });
    }

    saveFieldConfiguration(tableName, fieldName, configType, value) {
        if (!this.fieldConfigurations[tableName]) {
            this.fieldConfigurations[tableName] = {};
        }

        if (!this.fieldConfigurations[tableName][fieldName]) {
            this.fieldConfigurations[tableName][fieldName] = {};
        }

        // Mapear tipos de configuración
        let configKey = configType;

        // Manejar configuración específica para campo relacionado
        if (configType === 'related-field') {
            configKey = 'relatedField';
        }

        this.fieldConfigurations[tableName][fieldName][configKey] = value;

        console.log('Configuración guardada:', this.fieldConfigurations);
    }

    sanitizeId(id) {
        return id.replace(/[^a-zA-Z0-9-_]/g, '_');
    }

    generateFieldsConfiguration(tableName, tableConfig) {
        let html = `
            <h5>Configuración de campos para: <code>${tableName}</code></h5>
            <div class="alert alert-info small">
                <i class="bi bi-info-circle"></i> Configura cómo se mostrarán los campos en listados y formularios.
            </div>
        `;

        if (!tableConfig.columns || tableConfig.columns.length === 0) {
            html += '<div class="alert alert-warning">No se encontraron columnas en esta tabla</div>';
            return html;
        }

        tableConfig.columns.forEach(column => {
            html += this.generateFieldConfigCard(tableName, column);
        });

        return html;
    }

    generateQueryFieldsConfiguration(query) {
        const queryId = query.id || 'query_unkown';
        const queryName = query.name || 'Consulta personalizada';

        // Para consultas personalizadas, no tenemos la estructura de columnas hasta que se ejecute
        // Pero podemos simular la configuración de campos como si fueran columnas
        // Suponiendo que la consulta ya ha sido analizada o que el usuario puede especificar campos

        // Por ahora, como no tenemos la estructura real de la consulta, mostraremos una configuración genérica
        // pero en un entorno real, esto se debería obtener analizando el resultado de la consulta

        return `
            <h5>Configuración de campos para: <code>${queryName}</code></h5>
            <div class="alert alert-info small">
                <i class="bi bi-info-circle"></i> Configura cómo se mostrarán los campos en listados y formularios.
            </div>
            <div class="card">
                <div class="card-body">
                    <h6>Consulta SQL:</h6>
                    <pre class="bg-light p-3 border rounded"><code>${query.sql}</code></pre>
                    <p class="text-muted small">
                        Tipo: ${query.type === 'editable' ? 'Editable' : 'Solo lectura'}
                    </p>

                    <div class="mt-4">
                        <h6>Campos de la consulta</h6>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            Los campos de la consulta personalizada se determinarán automáticamente al ejecutar la aplicación.
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Configuración general de la consulta</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Etiqueta para la consulta</label>
                                            <input type="text" class="form-control"
                                                   value="${queryName}"
                                                   data-table="query_${queryId}"
                                                   data-field="query_label"
                                                   data-type="label">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Tipo de vista</label>
                                            <select class="form-select"
                                                    data-table="query_${queryId}"
                                                    data-field="query_view_type"
                                                    data-type="controlType">
                                                <option value="table" ${query.viewType === 'table' ? 'selected' : ''}>Tabla</option>
                                                <option value="cards" ${query.viewType === 'cards' ? 'selected' : ''}>Tarjetas</option>
                                                <option value="list" ${query.viewType === 'list' ? 'selected' : ''}>Lista</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    generateFieldConfigCard(tableName, column) {
        const fieldId = `${tableName}_${column.name}`;
        const isPrimaryKey = column.primaryKey || false;
        const isForeignKey = this.isForeignKey(tableName, column.name);

        // Obtener configuración existente
        const fieldConfig = this.fieldConfigurations[tableName]?.[column.name] || {};
        const currentLabel = fieldConfig.label || this.formatFieldName(column.name);
        const currentControlType = fieldConfig.controlType || this.getDefaultControlType(column.type, isPrimaryKey);
        const showInList = fieldConfig.showInList !== undefined ? fieldConfig.showInList : true;
        const required = fieldConfig.required !== undefined ? fieldConfig.required : !column.nullable;

        // Si es clave foránea, obtener información de la relación
        let fkRelatedFieldHtml = '';
        let fkInfo = null;

        if (isForeignKey && this.databaseStructure.tables) {
            // Buscar la relación FK en la estructura
            const table = this.databaseStructure.tables[tableName];
            const fk = table.foreignKeys?.find(fk => fk.column === column.name);

            if (fk) {
                fkInfo = fk;
                const referencedTable = this.databaseStructure.tables[fk.referenced_table];

                if (referencedTable && referencedTable.columns) {
                    // Generar opciones para el campo relacionado
                    let relatedFieldOptions = '<option value="">-- Seleccione campo --</option>';

                    referencedTable.columns.forEach(refColumn => {
                        // Omitimos tipos de datos no adecuados para mostrar como texto, como blobs
                        if (!refColumn.type.includes('blob') && !refColumn.type.includes('binary')) {
                            const selected = (fieldConfig.relatedField === refColumn.name) ? 'selected' : '';
                            relatedFieldOptions += `<option value="${refColumn.name}" ${selected}>${refColumn.name} (${refColumn.type})</option>`;
                        }
                    });

                    const currentRelatedField = fieldConfig.relatedField || '';

                    fkRelatedFieldHtml = `
                        <div class="d-inline-block mx-2" style="width: 220px;">
                            <label class="form-label small fw-bold">Rel.:</label>
                            <select class="form-select form-select-sm d-inline-block" style="width: 150px;"
                                    data-table="${tableName}" data-field="${column.name}" data-type="related-field">
                                ${relatedFieldOptions}
                            </select>
                        </div>
                    `;
                }
            }
        }

        return `
            <div class="field-config-compact card mb-1">
    <div class="card-body p-2">
        <div class="d-flex flex-wrap align-items-center">
            <div class="me-2 d-inline-flex align-items-center gap-1" style="width: 200px;">
                <h6 class="card-title mb-0 small fw-bold">${column.name}</h6>
                <code class="small">${column.type}</code>
            ${isPrimaryKey ? '<span class="badge bg-danger badge-sm me-2">PK</span>' : ''}
            ${isForeignKey ? `<span class="badge bg-info badge-sm me-2">FK</span>` : ''}
            </div>
            
            <!-- Etiqueta -->
            <div class="me-2 d-inline-flex align-items-center gap-1" style="width: 175px;">
                <label class="form-label small fw-bold mb-0">Etiqueta</label>
                <input type="text" class="form-control form-control-sm" style="width: 120px;"
                       value="${currentLabel}"
                       data-table="${tableName}" data-field="${column.name}">
            </div>
            
            <!-- Control -->
            <div class="me-2 d-inline-flex align-items-center gap-1" style="width: 200px;">
                <label class="form-label small fw-bold mb-0">Control</label>
                <select class="form-select form-select-sm"
                        data-table="${tableName}" data-field="${column.name}">
                    ${this.generateControlOptions(column, currentControlType)}
                </select>
            </div>
            
            <!-- Mostrar en Listados -->
            <div class="form-check me-2 d-flex align-items-center gap-1" style="width: 75px;">
                <input class="form-check-input" type="checkbox"
                       data-table="${tableName}" data-field="${column.name}"
                       data-type="show-in-list" ${showInList ? 'checked' : ''}>
                <label class="form-check-label small mb-0">Mostrar</label>
            </div>
            
            <!-- Requerido -->
            <div class="form-check me-2 d-flex align-items-center gap-1" style="width: 75px;">
                <input class="form-check-input" type="checkbox"
                       data-table="${tableName}" data-field="${column.name}"
                       data-type="required" ${required ? 'checked' : ''}>
                <label class="form-check-label small mb-0">Requerido</label>
            </div>
            
            <!-- Configuraciones específicas del tipo -->
            ${this.isNumericType(column.type) ? this.generateNumericFormatConfig(tableName, column, fieldConfig) : ''}
            ${this.isDateType(column.type) ? this.generateDateFormatConfig(tableName, column, fieldConfig) : ''}
            ${fkRelatedFieldHtml}
        </div>
    </div>
</div>
        `;
    }

    isForeignKey(tableName, columnName) {
        const table = this.databaseStructure.tables[tableName];
        return table.foreignKeys && table.foreignKeys.some(fk => fk.column === columnName);
    }

    generateControlOptions(column, currentControlType) {
        const options = {
            text: 'Campo de texto',
            textarea: 'Área de texto',
            number: 'Campo numérico',
            email: 'Campo de email',
            date: 'Selector de fecha',
            datetime: 'Selector de fecha y hora',
            select: 'Lista desplegable',
            checkbox: 'Casilla de verificación',
            radio: 'Botones de opción',
            hidden: 'Campo oculto'
        };

        let html = '';
        for (const [value, label] of Object.entries(options)) {
            const selected = currentControlType === value ? 'selected' : '';
            html += `<option value="${value}" ${selected}>${label}</option>`;
        }
        return html;
    }

    getDefaultControlType(columnType, isPrimaryKey = false) {
        if (isPrimaryKey) return 'hidden';
        if (this.isDateType(columnType)) return 'date';
        if (this.isNumericType(columnType)) return 'number';
        if (columnType.includes('text') || columnType.includes('char')) {
            return columnType.includes('text') ? 'text' : 'textarea';
        }
        return 'text';
    }

    isNumericType(type) {
        const numericTypes = ['int', 'integer', 'decimal', 'float', 'double', 'number', 'numeric', 'real'];
        return numericTypes.some(numericType => type.toLowerCase().includes(numericType));
    }

    isDateType(type) {
        const dateTypes = ['date', 'time', 'datetime', 'timestamp'];
        return dateTypes.some(dateType => type.toLowerCase().includes(dateType));
    }

    generateNumericFormatConfig(tableName, column, fieldConfig) {
        const currentFormat = fieldConfig.format || 'decimal';
        const currentDecimals = fieldConfig.decimals || '2';

        return `
            <div class="me-2 d-inline-flex align-items-center gap-1">
                <label class="form-label small fw-bold mb-0">Formato</label>
                <select class="form-select form-select-sm d-inline-block" style="width: 15ch;" data-table="${tableName}" data-field="${column.name}" data-type="format">
                    <option value="integer" ${currentFormat === 'integer' ? 'selected' : ''}>Entero</option>
                    <option value="decimal" ${currentFormat === 'decimal' ? 'selected' : ''}>Decimal</option>
                    <option value="currency" ${currentFormat === 'currency' ? 'selected' : ''}>Moneda</option>
                </select>
            </div>
            <div class="me-2 d-inline-flex align-items-center gap-1">
                <label class="form-label small fw-bold mb-0">Decimales</label>
                <input type="number" class="form-control form-control-sm d-inline-block" style="width: auto;" value="${currentDecimals}" min="0" max="6" data-table="${tableName}" data-field="${column.name}" data-type="decimals">
            </div>
        `;
    }

    generateDateFormatConfig(tableName, column, fieldConfig) {
        const currentFormat = fieldConfig.format || 'medium';

        return `
            <div class="me-2 d-inline-flex align-items-center gap-1">
                <label class="form-label small fw-bold mb-0">Formato</label>
                <select class="form-select form-select-sm d-inline-block" style="width: 15ch;" data-table="${tableName}" data-field="${column.name}" data-type="format">
                        <option value="short" ${currentFormat === 'short' ? 'selected' : ''}>Corta</option>
                        <option value="medium" ${currentFormat === 'medium' ? 'selected' : ''}>Media</option>
                        <option value="long" ${currentFormat === 'long' ? 'selected' : ''}>Larga</option>
                </select>
            </div>
        `;
    }

    formatFieldName(name) {
        return name
            .replace(/_/g, ' ')
            .replace(/([A-Z])/g, ' $1')
            .replace(/^./, str => str.toUpperCase())
            .trim();
    }

    previewLogo(file) {
        if (file) {
            // Guardar el archivo en la propiedad correspondiente
            this.appCustomization.logo = file;

            const reader = new FileReader();
            reader.onload = (e) => {
                const logoPreview = document.getElementById('logoPreview');
                if (logoPreview) {
                    logoPreview.innerHTML = `
                        <img src="${e.target.result}" class="img-fluid" style="max-height: 100px;">
                        <p class="small text-muted mt-2">${file.name}</p>
                    `;
                }
            };
            reader.readAsDataURL(file);
        }
    }

    async generateApplication() {
        const progressElement = document.getElementById('generationProgress');
        const resultElement = document.getElementById('generationResult');

        if (!progressElement || !resultElement) {
            alert('Elementos de la interfaz no encontrados');
            return;
        }

        const progressBar = progressElement.querySelector('.progress-bar');
        const progressText = document.getElementById('progressText');

        // Mostrar progreso
        progressElement.classList.remove('d-none');
        resultElement.classList.add('d-none');

        try {
            // Recopilar todos los datos
            const appData = {
                databaseType: this.databaseType,
                connectionData: this.getConnectionData(),
                selectedTables: this.selectedTables,
                customQueries: this.customQueries,
                fieldConfigurations: this.fieldConfigurations,
                databaseStructure: this.databaseStructure,
                appCustomization: {
                    title: document.getElementById('appTitle')?.value || 'Mi App CRUD',
                    primaryColor: document.getElementById('primaryColor')?.value || '#007bff',
                    logo: document.getElementById('appLogo')?.files[0] || null,
                    companyInfo: {
                        name: document.getElementById('companyName')?.value || '',
                        address: document.getElementById('companyAddress')?.value || '',
                        city: document.getElementById('companyCity')?.value || '',
                        province: document.getElementById('companyProvince')?.value || '',
                        country: document.getElementById('companyCountry')?.value || '',
                        phone: document.getElementById('companyPhone')?.value || '',
                        email: document.getElementById('companyEmail')?.value || '',
                        website: document.getElementById('companyWebsite')?.value || ''
                    }
                },
                authEnabled: document.getElementById('enableAuth')?.checked || false
            };

            console.log('Datos para generación:', appData);

            // Simular progreso
            for (let i = 0; i <= 100; i += 10) {
                if (progressBar) progressBar.style.width = `${i}%`;
                if (progressText) progressText.textContent = this.getProgressMessage(i);
                await this.delay(300);
            }

            // Verificar si CRUDGenerator existe
            if (typeof CRUDGenerator === 'undefined') {
                throw new Error('CRUDGenerator no está disponible');
            }

            // Generar aplicación
            const result = await CRUDGenerator.generate(appData);

            // Limpiar estado después de generación exitosa
            this.clearState();

            // Mostrar resultado
            progressElement.classList.add('d-none');
            resultElement.classList.remove('d-none');
            resultElement.innerHTML = `
                <div class="alert alert-success">
                    <h4><i class="bi bi-check-circle"></i> ¡Aplicación generada exitosamente!</h4>
                    <p>Tu aplicación CRUD ha sido generada y está lista para usar.</p>
                    <div class="mt-3">
                        <a href="${result.downloadUrl}" class="btn btn-success me-2" download>
                            <i class="bi bi-download"></i> Descargar Aplicación
                        </a>
                        <button class="btn btn-outline-primary" onclick="location.reload()">
                            <i class="bi bi-plus-circle"></i> Crear Otra Aplicación
                        </button>
                    </div>
                </div>
            `;

        } catch (error) {
            progressElement.classList.add('d-none');
            resultElement.classList.remove('d-none');
            resultElement.innerHTML = `
                <div class="alert alert-danger">
                    <h4><i class="bi bi-exclamation-triangle"></i> Error en la generación</h4>
                    <p>Ha ocurrido un error al generar la aplicación: ${error.message}</p>
                    <button class="btn btn-outline-secondary mt-2" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Reintentar
                    </button>
                </div>
            `;
        }
    }

    getProgressMessage(progress) {
        const messages = {
            0: 'Preparando...',
            10: 'Configurando estructura...',
            30: 'Generando archivos PHP...',
            50: 'Creando interfaces...',
            70: 'Configurando base de datos...',
            90: 'Finalizando...',
            100: 'Completado!'
        };
        return messages[progress] || `Progreso: ${progress}%`;
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Método para inicializar DataTables de forma segura
    initializeDataTables() {
        if (typeof $.fn.DataTable === 'undefined') {
            console.error('DataTables no está cargado');
            return;
        }

        // Inicializar DataTables solo en elementos existentes
        $('.data-table').DataTable({
            language: {
                url: 'js/dataTables.spanish.json' // Ruta local al archivo de idioma
            }
        });
    }

    loadSaveProjectStep() {
        // Vincular eventos para el paso de guardar proyecto
        const projectLocationSelect = document.getElementById('projectLocation');
        const customLocationDiv = document.getElementById('customLocation');

        if (projectLocationSelect && customLocationDiv) {
            projectLocationSelect.addEventListener('change', (e) => {
                if (e.target.value === 'custom') {
                    customLocationDiv.style.display = 'block';
                } else {
                    customLocationDiv.style.display = 'none';
                }
            });
        }

        // Vincular evento para el botón de guardar proyecto
        const saveProjectBtn = document.getElementById('saveProjectBtn');
        if (saveProjectBtn) {
            saveProjectBtn.addEventListener('click', () => this.saveProject());
        }
    }

    async saveProject() {
        const projectName = document.getElementById('projectName')?.value || 'proyecto_guardado';
        const projectLocation = document.getElementById('projectLocation')?.value || 'default';
        const customPath = document.getElementById('customProjectPath')?.value || '';

        if (!projectName.trim()) {
            showAlert('Por favor, ingresa un nombre para el proyecto', 'warning');
            return;
        }

        // Preparar los datos del proyecto
        const projectData = {
            databaseType: this.databaseType,
            connectionData: this.connectionData,
            databaseStructure: this.databaseStructure,
            selectedTables: this.selectedTables,
            customQueries: this.customQueries,
            fieldConfigurations: this.fieldConfigurations,
            appCustomization: this.appData?.appCustomization || {},
            authEnabled: this.appData?.authEnabled || false,
            timestamp: new Date().toISOString()
        };

        try {
            // Enviar solicitud para guardar el proyecto
            const response = await fetch('api/index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'save_project',
                    projectData: projectData,
                    projectName: projectName,
                    projectLocation: projectLocation,
                    customPath: customPath
                })
            });

            const result = await response.json();

            if (result.success) {
                document.getElementById('saveProjectMessage').innerHTML =
                    '<div class="alert alert-success">Proyecto guardado exitosamente en: ' + result.filePath + '</div>';
                showAlert('Proyecto guardado exitosamente', 'success');
            } else {
                document.getElementById('saveProjectMessage').innerHTML =
                    '<div class="alert alert-danger">Error al guardar proyecto: ' + result.error + '</div>';
                showAlert('Error al guardar proyecto: ' + result.error, 'danger');
            }
        } catch (error) {
            document.getElementById('saveProjectMessage').innerHTML =
                '<div class="alert alert-danger">Error de conexión al guardar proyecto: ' + error.message + '</div>';
            showAlert('Error de conexión al guardar proyecto', 'danger');
        }
    }

    async loadProjectFromFile() {
        const projectFileInput = document.getElementById('projectFileInput');
        if (!projectFileInput) {
            showAlert('No se encontró el campo de archivo de proyecto', 'danger');
            return;
        }

        const file = projectFileInput.files[0];
        if (!file) {
            showAlert('Por favor, selecciona un archivo de proyecto', 'warning');
            return;
        }

        if (!file.name.endsWith('.sti')) {
            showAlert('El archivo debe tener extensión .sti', 'warning');
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const projectData = JSON.parse(e.target.result);

                // Cargar los datos del proyecto en las propiedades de la aplicación
                this.databaseType = projectData.databaseType;
                this.connectionData = projectData.connectionData;
                this.databaseStructure = projectData.databaseStructure;
                this.selectedTables = projectData.selectedTables || [];
                this.customQueries = projectData.customQueries || [];
                this.fieldConfigurations = projectData.fieldConfigurations || {};

                // Actualizar la interfaz con los datos cargados
                this.updateInterfaceFromProject(projectData);

                // Avanzar al siguiente paso o al paso correspondiente
                this.currentStep = 3; // Ir al paso de análisis
                this.showStep(this.currentStep);
                this.updateNavigation();
                this.updateStepper(this.currentStep);

                showAlert('Proyecto cargado exitosamente', 'success');
            } catch (error) {
                showAlert('Error al cargar el proyecto: ' + error.message, 'danger');
            }
        };

        reader.onerror = () => {
            showAlert('Error al leer el archivo de proyecto', 'danger');
        };

        reader.readAsText(file);
    }

    updateInterfaceFromProject(projectData) {
        // Actualizar tipo de base de datos
        if (projectData.databaseType) {
            document.querySelectorAll('input[name="databaseType"]').forEach(radio => {
                if (radio.value === projectData.databaseType) {
                    radio.checked = true;
                }
            });
            this.handleDatabaseTypeChange(projectData.databaseType);
        }

        // Actualizar datos de conexión
        if (projectData.connectionData) {
            if (projectData.databaseType === 'sqlite') {
                // Para SQLite, se manejaría el archivo
                if (projectData.connectionData.file) {
                    // Aquí se manejaría la carga del archivo SQLite
                }
            } else {
                // Para MySQL/PostgreSQL, actualizar campos
                if (projectData.connectionData.host) document.getElementById('host').value = projectData.connectionData.host;
                if (projectData.connectionData.port) document.getElementById('port').value = projectData.connectionData.port;
                if (projectData.connectionData.database) document.getElementById('database').value = projectData.connectionData.database;
                if (projectData.connectionData.schema) document.getElementById('schema').value = projectData.connectionData.schema;
                if (projectData.connectionData.username) document.getElementById('username').value = projectData.connectionData.username;
            }
        }

        // Actualizar tablas seleccionadas
        if (projectData.selectedTables && Array.isArray(projectData.selectedTables)) {
            this.selectedTables = projectData.selectedTables;
            // Actualizar checkboxes en la interfaz
            projectData.selectedTables.forEach(tableName => {
                const checkbox = document.getElementById(`table-${tableName}`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
        }

        // Actualizar configuración de campos
        if (projectData.fieldConfigurations) {
            this.fieldConfigurations = projectData.fieldConfigurations;
        }

        // Actualizar personalización de la aplicación
        if (projectData.appCustomization) {
            if (projectData.appCustomization.title) document.getElementById('appTitle').value = projectData.appCustomization.title;
            if (projectData.appCustomization.primaryColor) document.getElementById('primaryColor').value = projectData.appCustomization.primaryColor;
        }

        // Actualizar autenticación
        if (projectData.authEnabled !== undefined) {
            document.getElementById('enableAuth').checked = projectData.authEnabled;
            const authConfigDiv = document.getElementById('authConfig');
            if (authConfigDiv) {
                if (projectData.authEnabled) {
                    authConfigDiv.classList.remove('d-none');
                } else {
                    authConfigDiv.classList.add('d-none');
                }
            }
        }
    }
}