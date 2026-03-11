class DatabaseManager {
    static async testConnection(databaseType, connectionData) {
        const formData = new FormData();
        formData.append('action', 'test_connection');
        formData.append('database_type', databaseType);

        if (databaseType === 'sqlite') {
            formData.append('sqlite_file', connectionData.file);
        } else {
            Object.keys(connectionData).forEach(key => {
                formData.append(key, connectionData[key]);
            });
        }

        const response = await fetch('api/index.php', {
            method: 'POST',
            body: formData
        });

        return await response.json();
    }

    static async analyzeDatabase(databaseType, connectionData) {
        const formData = new FormData();
        formData.append('action', 'analyze_database');
        formData.append('database_type', databaseType);

        if (databaseType === 'sqlite') {
            formData.append('sqlite_file', connectionData.file);
        } else {
            Object.keys(connectionData).forEach(key => {
                formData.append(key, connectionData[key]);
            });
        }

        const response = await fetch('api/index.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error);
        }

        return result.data;
    }
}