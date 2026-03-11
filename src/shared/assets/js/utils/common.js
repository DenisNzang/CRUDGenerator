// src/shared/assets/js/utils/common.js

// Archivo de utilidades JavaScript comunes
// Este archivo puede contener funciones de utilidad compartidas para el frontend

function showAlert(message, type = 'info') {
    // Crear una alerta temporal en la parte superior de la página
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Agregar al principio del body o de un contenedor específico
    const container = document.querySelector('.container') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    // Autoeliminar después de 5 segundos
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function changeLanguage(lang) {
    // Función para cambiar el idioma de la aplicación
    // Esta función puede ser expandida para soportar internacionalización
    console.log('Cambiando idioma a: ' + lang);
}

function processDataAndExportToPDF(data, title, filename) {
    // Función para procesar datos y exportar a PDF
    if (!data || !data.data) {
        showAlert('No hay datos para exportar', 'danger');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    // Variables para almacenar información del logotipo
    let logoImgData = null;
    let logoWidth = 0;
    let logoHeight = 0;

    // Intentar obtener el logotipo
    const logoImg = document.querySelector('img[src*="logo"]') || document.querySelector('img[src*="Logo"]');

    // Función para procesar el logotipo y generar el PDF
    function processLogoAndGeneratePDF() {
        if (logoImg) {
            try {
                // Crear un canvas para convertir la imagen a formato que jsPDF pueda usar
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                // Calcular dimensiones proporcionales (máximo 30mm de ancho)
                const maxWidth = 30;
                const ratio = Math.min(maxWidth / logoImg.width * 25.4/72, 1); // Convertir px a mm (72dpi)
                logoWidth = logoImg.width * ratio;
                logoHeight = logoImg.height * ratio;

                canvas.width = logoWidth * 72/25.4; // Convertir mm a puntos
                canvas.height = logoHeight * 72/25.4;

                ctx.drawImage(logoImg, 0, 0, canvas.width, canvas.height);

                // Obtener datos de imagen para usar en todas las páginas
                logoImgData = canvas.toDataURL('image/png');
            } catch (e) {
                console.warn('Error al procesar el logotipo:', e);
            }
        }

        // Preparar datos para la tabla
        const headers = Object.keys(data.data[0] || {});
        const rows = data.data.map(item => headers.map(header => item[header] || ''));

        // Agregar tabla con manejo de logotipo en todas las páginas
        if (typeof doc.autoTable === 'function') {
            // Añadir título en la primera página
            doc.setFontSize(18);
            doc.text(title, 20, 20);

            doc.autoTable({
                head: [headers],
                body: rows,
                startY: 30,
                // Esta función se llama en cada página dibujada
                didDrawPage: function(data) {
                    // Agregar logotipo en cada página
                    if (logoImgData) {
                        // Posición del logotipo en cada página (arriba a la derecha)
                        const pageWidth = doc.internal.pageSize.width;
                        const imgX = pageWidth - 15 - logoWidth; // 15mm del borde derecho
                        const imgY = 10; // 10mm del borde superior

                        doc.addImage(logoImgData, 'PNG', imgX, imgY, logoWidth, logoHeight);
                    }

                    // Agregar título en cada página también
                    doc.setFontSize(18);
                    doc.setFont(undefined, 'normal');
                    doc.text(title, 20, 15);
                }
            });

            doc.save(filename);
            showAlert('PDF generado exitosamente', 'success');
        } else {
            showAlert('Error: jsPDF-AutoTable no está disponible', 'danger');
        }
    }

    // Si la imagen no está completamente cargada, esperar a que se cargue
    if (logoImg && !logoImg.complete) {
        logoImg.onload = processLogoAndGeneratePDF;
        logoImg.onerror = function() {
            // Si hay error al cargar la imagen, continuar sin logotipo
            console.warn('No se pudo cargar el logotipo, generando PDF sin él');
            processLogoAndGeneratePDF();
        };
    } else {
        // Si la imagen ya está cargada o no existe, continuar
        processLogoAndGeneratePDF();
    }
}

function processDataAndExportToExcel(data, filename) {
    // Función para procesar datos y exportar a Excel
    if (!data || !data.data) {
        showAlert('No hay datos para exportar', 'danger');
        return;
    }

    // Convertir datos a formato para Excel
    const headers = Object.keys(data.data[0] || []);
    const rows = [headers, ...data.data.map(item => headers.map(header => item[header] || ''))];

    // Crear libro de trabajo
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(rows);
    XLSX.utils.book_append_sheet(wb, ws, 'Datos');
    
    // Guardar archivo
    XLSX.writeFile(wb, filename);
    showAlert('Excel generado exitosamente', 'success');
}