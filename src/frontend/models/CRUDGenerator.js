class CRUDGenerator {
    static async generate(appData) {
        const formData = new FormData();
        formData.append('action', 'generate_app');
        formData.append('app_data', JSON.stringify(appData));

        // Buscar el archivo del logo directamente del input file si está disponible
        const logoInput = document.getElementById('appLogo');
        if (logoInput && logoInput.files && logoInput.files[0]) {
            formData.append('app_logo', logoInput.files[0]);
        } else if (appData.appCustomization.logo && typeof appData.appCustomization.logo !== 'string') {
            // Si no está en el input, usar el archivo guardado en appData
            formData.append('app_logo', appData.appCustomization.logo);
        }

        const response = await fetch('api/index.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error);
        }

        return result;
    }
}