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
        // Vincular eventos de clic en el menú
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
        // Cargar la primera vista disponible
        const firstMenuItem = document.querySelector('#sidebar a[data-view]');
        if (firstMenuItem) {
            this.loadView(firstMenuItem.getAttribute('data-view'), firstMenuItem);
        } else {
            // Si no hay elementos en el menú, mostrar mensaje
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

        // Mostrar carga
        content.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2">Cargando...</p>
            </div>
        `;

        // Actualizar navegación activa
        document.querySelectorAll('#sidebar a').forEach(item => {
            item.classList.remove('active');
        });
        if (menuItem) {
            menuItem.classList.add('active');
        }

        // Determinar tipo de vista y cargar contenido
        if (viewId.startsWith('table-')) {
            const tableName = viewId.replace('table-', '');
            this.loadTableView(tableName);
        } else if (viewId.startsWith('query-')) {
            const queryId = viewId.replace('query-', '');
            this.loadQueryView(queryId);
        } else {
            // Vista no reconocida
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

        // Cargar template de la tabla
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

        // Cargar template de la consulta
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
                // Verificar que jQuery y DataTables estén disponibles antes de ejecutar scripts que los usen
                if (script.textContent && (script.textContent.includes('DataTable') || script.textContent.includes('$.') || script.textContent.includes('jQuery'))) {
                    // Esperar a que jQuery y DataTables estén disponibles
                    this.waitForLibraries(() => {
                        this.executeScriptElement(script);
                    });
                } else {
                    // Para scripts que no dependen de bibliotecas, ejecutar inmediatamente
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
                // Asegurarse de que DataTables haya sido inicializado completamente
                // La extensión Bootstrap5 se carga como parte de DataTables
                callback();
            } else {
                setTimeout(checkLibraries, 100); // Revisar cada 100ms
            }
        };
        checkLibraries();
    }

    executeScriptElement(script) {
        const newScript = document.createElement('script');

        // Copiar todos los atributos de forma segura
        for (let attr of script.attributes) {
            try {
                newScript.setAttribute(attr.name, attr.value);
            } catch (e) {
                console.warn('Error copiando atributo:', attr.name, e);
            }
        }

        // Si tiene src, cargar el script externo
        if (script.src) {
            newScript.src = script.src;
            newScript.onload = () => console.log('Script externo cargado:', script.src);
            newScript.onerror = (e) => console.error('Error cargando script externo:', script.src, e);
        } else {
            // Sanitizar el texto del script para evitar problemas de sintaxis
            try {
                // Usar textContent directamente, sin manipulación innecesaria
                newScript.textContent = script.textContent || '';
            } catch (e) {
                // Si hay un problema con textContent, usar un enfoque alternativo
                console.warn('Error estableciendo contenido del script:', e);
                // Crear script con contenido codificado
                const scriptContent = script.textContent || '';
                // Evitar caracteres especiales que puedan romper la sintaxis
                newScript.textContent = scriptContent.replace(/<!\[CDATA\[|\]\]>/g, '');
            }
        }

        // Asegurarse de que el body exista antes de intentar adjuntar
        if (document.body) {
            document.body.appendChild(newScript);
        } else {
            // Si no hay body, intentar adjuntar al head como fallback
            document.head.appendChild(newScript);
        }
    }
}

// Inicializar la aplicación cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando aplicación CRUD...');
    window.crudApp = new CRUDApp();
});

// Función para cambiar el idioma de la aplicación
function changeLanguage(lang) {
    // No almacenar la preferencia de idioma entre sesiones
    // Recargar la página para aplicar el cambio de idioma
    // Nota: En implementaciones futuras, se podría hacer un cambio dinámico sin recargar
    location.reload();
}

// Utilidades globales
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

// Función global para debugging
function debugApp() {
    console.log('Estado de la aplicación:', {
        currentView: window.crudApp?.currentView,
        menuItems: document.querySelectorAll('#sidebar a[data-view]').length,
        content: document.getElementById('content')?.innerHTML?.substring(0, 100) + '...'
    });
}

// Función para cargar opciones de claves foráneas dinámicamente
function loadForeignKeyOptions(selectId, foreignTable, displayField, referencedColumnName, selectedValue = null) {
    const selectElement = document.getElementById(selectId);

    if (!selectElement) {
        console.error('No se encontró el elemento select con ID:', selectId);
        return;
    }

    // Mostrar estado de carga
    selectElement.innerHTML = '<option value="">Cargando opciones...</option>';
    selectElement.disabled = true;

    // Hacer petición AJAX para obtener las opciones
    $.ajax({
        url: 'php/' + foreignTable + '.php',  // Usar el controlador de la tabla referenciada
        type: 'POST',
        data: {
            action: 'get_foreign_key_options',
            foreign_table: foreignTable,
            display_field: displayField
        },
        success: function(response) {
            if (response.success && response.data && Array.isArray(response.data)) {
                // Limpiar el select
                selectElement.innerHTML = '<option value="">Seleccione...</option>';

                // Agregar opciones al select
                response.data.forEach(function(row) {
                    const option = document.createElement('option');
                    option.value = row.id;
                    // Mostrar el valor del campo relacionado o el ID si no está disponible
                    option.textContent = row.display_value || row.id;
                    selectElement.appendChild(option);
                });

                // Habilitar select
                selectElement.disabled = false;

                // Si se proporcionó un valor para seleccionar, hacerlo después de un breve delay
                // para asegurar que las opciones estén completamente cargadas
                if (selectedValue !== null && selectedValue !== undefined && selectedValue !== '') {
                    setTimeout(function() {
                        selectElement.value = selectedValue;
                        $(selectElement).trigger('change'); // Disparar evento change si se usa Select2 u otra librería

                        // Verificar si el valor se seleccionó correctamente
                        if (selectElement.value != selectedValue) {
                            console.warn('No se pudo seleccionar el valor "' + selectedValue + '" en el select "' + selectId + '"');
                        }
                    }, 50); // Breve retraso para asegurar que las opciones estén disponibles
                } else {
                    // Si el select tenía un valor previo, restaurarlo
                    const previousValue = selectElement.getAttribute('data-prev-value');
                    if (previousValue) {
                        selectElement.value = previousValue;
                        selectElement.removeAttribute('data-prev-value');
                    }
                }
            } else {
                console.error('Error al cargar opciones de clave foránea:', response.error || 'Datos inválidos');
                selectElement.innerHTML = '<option value="">Error al cargar...</option>';
                selectElement.disabled = false;

                // Si se proporcionó un valor para seleccionar, hacerlo después de un breve delay
                if (selectedValue !== null && selectedValue !== undefined && selectedValue !== '') {
                    setTimeout(function() {
                        selectElement.value = selectedValue;
                        $(selectElement).trigger('change');
                    }, 50);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error de conexión al cargar opciones de clave foránea:', error);
            selectElement.innerHTML = '<option value="">Error de conexión...</option>';
            selectElement.disabled = false;

            // Si se proporcionó un valor para seleccionar, hacerlo después de un breve delay
            if (selectedValue !== null && selectedValue !== undefined && selectedValue !== '') {
                setTimeout(function() {
                    selectElement.value = selectedValue;
                    $(selectElement).trigger('change');
                }, 50);
            }
        }
    });
}

// Función para obtener el archivo de idioma correspondiente
function getLanguageFile() {
    // No mantener preferencia de idioma entre sesiones, usar español por defecto
    const selectedLanguage = "es";

    // Mapeo de idiomas a archivos de idioma de DataTables
    const languageMap = {
        'es': 'js/dataTables.spanish.json',
        'en': 'js/dataTables.english.json',
        'fr': 'js/dataTables.french.json'
    };

    // Devolver la ruta al archivo de idioma correspondiente
    return languageMap[selectedLanguage] || languageMap['es'];
}

// Funciones de utilidad para exportación

// Función para esperar a que jsPDF esté disponible
function waitForJsPDF(callback) {
    let attempts = 0;
    const maxAttempts = 50; // 5 segundos con intervalo de 100ms

    function checkJsPDF() {
        attempts++;

        // Verificar si jsPDF está disponible (diferentes posibles formas)
        if (typeof window.jsPDF !== 'undefined' && typeof window.jsPDF.jsPDF !== 'undefined') {
            // jsPDF está disponible en formato completo
            callback();
        } else if (typeof window.jspdf !== 'undefined' && typeof window.jspdf.jsPDF !== 'undefined') {
            // jsPDF está disponible pero en window.jspdf
            window.jsPDF = window.jspdf.jsPDF;
            if (typeof window.jspdf.autoTable !== 'undefined') {
                window.jsPDF.autoTable = window.jspdf.autoTable;
            }
            callback();
        } else if (typeof window.jspdf !== 'undefined' && typeof window.jspdf.default !== 'undefined' && typeof window.jspdf.default.jsPDF !== 'undefined') {
            // jsPDF está disponible en formato ES6 default
            window.jsPDF = window.jspdf.default.jsPDF;
            if (window.jspdf.default.autoTable) {
                window.jsPDF.autoTable = window.jspdf.default.autoTable;
            }
            callback();
        } else if (attempts < maxAttempts) {
            // No está disponible aún, esperar y reintentar
            setTimeout(checkJsPDF, 100);
        } else {
            // Agotaron los intentos
            showAlert('Error: jsPDF no se ha cargado correctamente después de 5 segundos', 'danger');
        }
    }

    checkJsPDF();
}

// Funciones de exportación a PDF
function processDataAndExportToPDF(response, title, filename) {
    if (response.success && response.data) {
        // Esperar a que jsPDF esté disponible y luego procesar
        waitForJsPDF(function() {
            try {
                const data = response.data;

                // Crear un documento PDF con jsPDF
                const { jsPDF } = window;
                const doc = new jsPDF();

                // Intentar agregar logotipo si está disponible
                let logoYPosition = 20; // posición Y inicial
                let logoAdded = false;

                // Intentar múltiples selectores para encontrar el logo
                const logoSelectors = ['nav img', '.navbar-brand img', 'header img', '.logo', '[alt="Logo"]', 'img[src*="logo"]'];

                for (const selector of logoSelectors) {
                    const logoImg = document.querySelector(selector);
                    if (logoImg && logoImg.complete && logoImg.src && !logoImg.src.includes('bi-database')) {
                        try {
                            doc.addImage(logoImg.src, 'JPEG', 15, 10, 30, 15, undefined, 'FAST');
                            logoYPosition = 35; // ajustar posición del título si hay logo
                            logoAdded = true;
                            break;
                        } catch (e) {
                            console.debug('No se pudo agregar el logo con selector', selector, ':', e);
                        }
                    }
                }

                // Si no se pudo agregar con selectores, intentar con rutas comunes de logo
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
                            // Continuar intentando con la siguiente ruta
                            continue;
                        }
                    }
                }

                // Título
                doc.setFontSize(18);
                doc.text(title, 20, logoYPosition);

                // Obtener encabezados de la tabla - buscar la tabla correcta
                const tableName = getCurrentTableName();
                const headers = [];
                const headerFields = []; // Mapeo de encabezados a campos de datos
                $(`#table-${tableName} thead th`).each(function() {
                    const text = $(this).text();
                    const $th = $(this);
                    // Excluir columna de selección (checkbox) y columna de acciones
                    if (text !== 'Acciones' && !$th.find('input[type="checkbox"]').length > 0) {
                        headers.push(text);
                        // Mapear encabezado a campo de datos
                        headerFields.push(findDataFieldForHeader(text, $(`#table-${tableName}`)));
                    }
                });

                // Preparar datos para la tabla PDF
                const tableData = data.map(row => {
                    const rowData = [];
                    // Usar el mapeo de campos para cada encabezado
                    for (let i = 0; i < headerFields.length; i++) {
                        const dataField = headerFields[i];
                        if (dataField && row.hasOwnProperty(dataField)) {
                            rowData.push(row[dataField]);
                        } else {
                            // Si no se encuentra el campo específico, usar la función de búsqueda
                            rowData.push(getValueForHeader(row, headers[i]));
                        }
                    }
                    return rowData;
                });

                // Agregar tabla al PDF
                if (tableData.length > 0 && headers.length > 0) {
                    // Ajustar posición inicial considerando el título
                    let startY = logoYPosition + 10; // posición después del título

                    doc.autoTable({
                        head: [headers],
                        body: tableData,
                        startY: startY,
                        styles: {
                            fontSize: 10
                        },
                        headStyles: {
                            fillColor: [253, 126, 20] // Color naranja principal
                        },
                        // Añadir pie de página con información de empresa
                        didDrawPage: function(data) {
                            // Pie de página
                            const pageHeight = doc.internal.pageSize.height;
                            const footerY = pageHeight - 15;

                            // Obtener información de empresa del DOM si está disponible
                            let companyInfo = '';
                            const companyDiv = document.querySelector('.company-info');
                            if (companyDiv) {
                                companyInfo = companyDiv.innerText || companyDiv.textContent || '';
                            } else {
                                // Si no está disponible, usar el título de la aplicación
                                const titleElement = document.querySelector('.navbar-brand .ms-2');
                                if (titleElement) {
                                    companyInfo = '© ' + new Date().getFullYear() + ' ' + titleElement.textContent.trim();
                                } else {
                                    companyInfo = '© ' + new Date().getFullYear() + ' Aplicación Generada';
                                }
                            }

                            doc.setFontSize(8);
                            doc.setTextColor(100); // Gris oscuro
                            doc.text(companyInfo, data.settings.margin.left, footerY);

                            // Número de página
                            const pageNumber = 'Página ' + doc.internal.getNumberOfPages();
                            doc.text(pageNumber, data.settings.margin.right - 20, footerY);
                        }
                    });
                }

                // Guardar el PDF
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

// Función para procesar datos y exportar a PDF (consultas)
function processDataAndExportToPDFQuery(response, title, filename) {
    if (response.success && response.data && response.data.length > 0) {
        // Esperar a que jsPDF esté disponible y luego procesar
        waitForJsPDF(function() {
            try {
                const data = response.data;

                // Crear un documento PDF con jsPDF
                const { jsPDF } = window;
                const doc = new jsPDF();

                // Intentar agregar logotipo si está disponible
                let logoYPosition = 20; // posición Y inicial
                let logoAdded = false;

                // Intentar múltiples selectores para encontrar el logo
                const logoSelectors = ['nav img', '.navbar-brand img', 'header img', '.logo', '[alt="Logo"]', 'img[src*="logo"]'];

                for (const selector of logoSelectors) {
                    const logoImg = document.querySelector(selector);
                    if (logoImg && logoImg.complete && logoImg.src && !logoImg.src.includes('bi-database')) {
                        try {
                            doc.addImage(logoImg.src, 'JPEG', 15, 10, 30, 15, undefined, 'FAST');
                            logoYPosition = 35; // ajustar posición del título si hay logo
                            logoAdded = true;
                            break;
                        } catch (e) {
                            console.debug('No se pudo agregar el logo con selector', selector, ':', e);
                        }
                    }
                }

                // Si no se pudo agregar con selectores, intentar con rutas comunes de logo
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
                            // Continuar intentando con la siguiente ruta
                            continue;
                        }
                    }
                }

                // Título
                doc.setFontSize(18);
                doc.text(title, 20, logoYPosition);

                // Obtener encabezados de la tabla
                const queryId = getCurrentQueryId();
                const headers = [];
                $(`#table-query-${queryId} thead th`).each(function() {
                    const text = $(this).text();
                    const $th = $(this);
                    // Excluir columna de selección (checkbox) y columna de acciones
                    if (text !== 'Acciones' && !$th.find('input[type="checkbox"]').length > 0) {
                        headers.push(text);
                    }
                });

                // Preparar datos para la tabla PDF
                const tableData = data.map(row => {
                    const rowData = [];
                    // Agregar los valores correspondientes a los encabezados
                    headers.forEach(header => {
                        // Buscar el valor correspondiente en el objeto row
                        for (const key in row) {
                            if (row.hasOwnProperty(key)) {
                                // Verificar si la clave coincide con el encabezado
                                if (normalizeHeader(key) === normalizeHeader(header) ||
                                    normalizeHeader(key).includes(normalizeHeader(header)) ||
                                    normalizeHeader(header).includes(normalizeHeader(key))) {
                                    rowData.push(row[key]);
                                    break;
                                }
                            }
                        }
                    });
                    return rowData;
                });

                // Agregar tabla al PDF
                if (tableData.length > 0 && headers.length > 0) {
                    // Ajustar posición inicial considerando el título
                    let startY = logoYPosition + 10; // posición después del título

                    doc.autoTable({
                        head: [headers],
                        body: tableData,
                        startY: startY,
                        styles: {
                            fontSize: 10
                        },
                        headStyles: {
                            fillColor: [253, 126, 20] // Color naranja principal
                        },
                        // Añadir pie de página con información de empresa
                        didDrawPage: function(data) {
                            // Pie de página
                            const pageHeight = doc.internal.pageSize.height;
                            const footerY = pageHeight - 15;

                            // Obtener información de empresa del DOM si está disponible
                            let companyInfo = '';
                            const companyDiv = document.querySelector('.company-info');
                            if (companyDiv) {
                                companyInfo = companyDiv.innerText || companyDiv.textContent || '';
                            } else {
                                // Si no está disponible, usar el título de la aplicación
                                const titleElement = document.querySelector('.navbar-brand .ms-2');
                                if (titleElement) {
                                    companyInfo = '© ' + new Date().getFullYear() + ' ' + titleElement.textContent.trim();
                                } else {
                                    companyInfo = '© ' + new Date().getFullYear() + ' Aplicación Generada';
                                }
                            }

                            doc.setFontSize(8);
                            doc.setTextColor(100); // Gris oscuro
                            doc.text(companyInfo, data.settings.margin.left, footerY);

                            // Número de página
                            const pageNumber = 'Página ' + doc.internal.getNumberOfPages();
                            doc.text(pageNumber, data.settings.margin.right - 20, footerY);
                        },
                        didParseCell: function(data) {
                            // Opcional: para manejar celdas especiales
                        }
                    });
                }

                // Guardar el PDF
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

// Función auxiliar para procesar datos y exportar a Excel
function processDataAndExportToExcel(response, filename) {
    if (response.success && response.data) {
        const data = response.data;

        // Crear libro de trabajo con SheetJS
        const wb = XLSX.utils.book_new();

        // Obtener encabezados de la tabla - buscar la tabla correcta
        const tableName = getCurrentTableName();
        const headers = [];
        const headerFields = []; // Mapeo de encabezados a campos de datos
        $(`#table-${tableName} thead th`).each(function() {
            const text = $(this).text();
            const $th = $(this);
            // Excluir columna de selección (checkbox) y columna de acciones
            if (text !== 'Acciones' && !$th.find('input[type="checkbox"]').length > 0) {
                headers.push(text);
                // Mapear encabezado a campo de datos
                headerFields.push(findDataFieldForHeader(text, $(`#table-${tableName}`)));
            }
        });

        // Preparar datos incluyendo encabezados
        const excelData = [headers];
        data.forEach(row => {
            const rowData = [];
            // Usar el mapeo de campos para cada encabezado
            for (let i = 0; i < headerFields.length; i++) {
                const dataField = headerFields[i];
                if (dataField && row.hasOwnProperty(dataField)) {
                    rowData.push(row[dataField]);
                } else {
                    // Si no se encuentra el campo específico, usar la función de búsqueda
                    rowData.push(getValueForHeader(row, headers[i]));
                }
            }
            excelData.push(rowData);
        });

        // Crear hoja de trabajo
        const ws = XLSX.utils.aoa_to_sheet(excelData);

        // Agregar hoja al libro
        XLSX.utils.book_append_sheet(wb, ws, tableName);

        // Guardar archivo Excel
        XLSX.writeFile(wb, filename);
        showAlert('Excel generado exitosamente', 'success');
    } else {
        showAlert('Error al obtener datos para exportar: ' + (response.error || 'Error desconocido'), 'danger');
    }
}

// Función auxiliar para procesar datos y exportar a Excel (consultas)
function processDataAndExportToExcelQuery(response, filename) {
    if (response.success && response.data && response.data.length > 0) {
        const data = response.data;

        // Crear libro de trabajo con SheetJS
        const wb = XLSX.utils.book_new();

        // Obtener encabezados de la tabla
        const queryId = getCurrentQueryId();
        const headers = [];
        const headerFields = []; // Mapeo de encabezados a campos de datos
        $(`#table-query-${queryId} thead th`).each(function() {
            const text = $(this).text();
            const $th = $(this);
            // Excluir columna de selección (checkbox) y columna de acciones
            if (text !== 'Acciones' && !$th.find('input[type="checkbox"]').length > 0) {
                headers.push(text);
                // Mapear encabezado a campo de datos
                headerFields.push(findDataFieldForHeader(text, $(`#table-query-${queryId}`)));
            }
        });

        // Preparar datos incluyendo encabezados
        const excelData = [headers];
        data.forEach(row => {
            const rowData = [];
            // Usar el mapeo de campos para cada encabezado
            for (let i = 0; i < headerFields.length; i++) {
                const dataField = headerFields[i];
                if (dataField && row.hasOwnProperty(dataField)) {
                    rowData.push(row[dataField]);
                } else {
                    // Si no se encuentra el campo específico, usar la función de búsqueda
                    rowData.push(getValueForHeader(row, headers[i]));
                }
            }
            excelData.push(rowData);
        });

        // Crear hoja de trabajo
        const ws = XLSX.utils.aoa_to_sheet(excelData);

        // Agregar hoja al libro
        XLSX.utils.book_append_sheet(wb, ws, 'Consulta_' + queryId);

        // Guardar archivo Excel
        XLSX.writeFile(wb, filename);
        showAlert('Excel generado exitosamente', 'success');
    } else {
        showAlert('Error al obtener datos para exportar: ' + (response.error || 'No hay datos'), 'danger');
    }
}

// Función para obtener el nombre de la tabla actual
function getCurrentTableName() {
    // Verificar la vista actual para determinar el nombre de la tabla
    const currentView = window.crudApp?.currentView;
    if (currentView && currentView.startsWith('table-')) {
        return currentView.replace('table-', '');
    }
    return '';
}

// Función para obtener el ID de la consulta actual
function getCurrentQueryId() {
    // Verificar la vista actual para determinar el ID de la consulta
    const currentView = window.crudApp?.currentView;
    if (currentView && currentView.startsWith('query-')) {
        return currentView.replace('query-', '');
    }
    return '';
}

// Función para normalizar encabezados y claves para comparación
function normalizeHeader(header) {
    return header.toLowerCase()
        .replace(/\s+/g, '')  // Remover espacios
        .replace(/\./g, '')   // Remover puntos
        .replace(/[^a-z0-9_]/gi, ''); // Remover caracteres especiales
}

// Función para encontrar el campo de datos correspondiente a un encabezado específico
function findDataFieldForHeader(headerText, tableElement) {
    // Esta función intenta encontrar el campo de datos asociado a un encabezado específico
    // Buscamos en la configuración de DataTables si está disponible
    if (tableElement && tableElement.DataTable) {
        const dt = tableElement.DataTable();
        if (dt) {
            const columns = dt.columns().header();
            const columnIndex = Array.from(columns).findIndex(header =>
                $(header).text().trim() === headerText.trim()
            );

            if (columnIndex !== -1) {
                const settings = dt.settings()[0];
                const columnData = settings.aoColumns[columnIndex];
                if (columnData && columnData.data) {
                    return columnData.data;
                }
            }
        }
    }

    // Si no se puede encontrar mediante DataTables, intentar con la lógica de mapeo
    // Buscar campos que se correspondan con el encabezado
    return null;
}

// Función para obtener el valor adecuado para un encabezado
function getValueForHeader(row, header) {
    const normalizedHeader = normalizeHeader(header);

    // Buscar campos relacionados primero (con _related_display)
    for (const key in row) {
        if (row.hasOwnProperty(key) && key !== 'DT_RowId') {
            const normalizedKey = normalizeHeader(key);

            // Si este campo tiene sufijo _related_display, comprobar si es el que buscamos
            if (normalizedKey.includes('_related_display')) {
                // Extraer el nombre base del campo relacionado
                const baseField = key.replace('_related_display', '');
                const normalizedBaseField = normalizeHeader(baseField);

                // Comparar con el encabezado para ver si coincide
                if (normalizedHeader.includes(normalizedBaseField) || normalizedBaseField.includes(normalizedHeader)) {
                    return row[key];
                }
            }
        }
    }

    // Si no se encontró campo relacionado, buscar el campo exacto
    for (const key in row) {
        if (row.hasOwnProperty(key) && key !== 'DT_RowId') {
            if (normalizeHeader(key) === normalizedHeader) {
                return row[key];
            }
        }
    }

    // Si no hay coincidencia exacta, intentar con coincidencia parcial
    for (const key in row) {
        if (row.hasOwnProperty(key) && key !== 'DT_RowId') {
            const normalizedKey = normalizeHeader(key);
            if (normalizedHeader.includes(normalizedKey) || normalizedKey.includes(normalizedHeader)) {
                return row[key];
            }
        }
    }

    // Último recurso: búsqueda más flexible
    // Verificar si el encabezado coincide con el nombre base de un campo relacionado
    const possibleRelatedField = header.toLowerCase().replace(/\s+/g, '_');
    if (row.hasOwnProperty(possibleRelatedField + '_related_display')) {
        return row[possibleRelatedField + '_related_display'];
    }

    // Buscar campos que podrían estar relacionados con el encabezado
    // Por ejemplo, si el encabezado es "Nombre del Cliente" y hay un campo "customer_name_related_display"
    for (const key in row) {
        if (row.hasOwnProperty(key) && key !== 'DT_RowId' && key.includes('_related_display')) {
            // Extraer el nombre base del campo relacionado
            const baseFieldName = key.replace('_related_display', '');
            // Comparar si el encabezado normalizado contiene parte del nombre del campo o viceversa
            if (normalizedHeader.includes(normalizeHeader(baseFieldName)) ||
                normalizeHeader(baseFieldName).includes(normalizedHeader)) {
                return row[key];
            }
        }
    }

    // Si no se encontró nada, devolver una cadena vacía
    return '';
}