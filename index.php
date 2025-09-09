<?php
// Obtenemos el folderId inicial para la carga de la página.
// Esto permite que los enlaces directos a carpetas específicas sigan funcionando.
$defaultFolderId = "1w1X74_EI9LDVhkTrrgA89etnvofGhYSN";
$initialFolderId = filter_input(INPUT_GET, 'folderId', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: $defaultFolderId;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visor de Archivos de Google Drive</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>

    <div class="container">
        <header class="header">
            <h1 id="folder-title">Cargando...</h1>
            <p id="status-message" class="status" style="display: none;"></p>
        </header>
        
        <main>
            <div id="back-link-container"></div>
            <div id="file-list-container">
                <p class="no-files">Cargando archivos...</p>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const folderTitle = document.getElementById('folder-title');
        const statusMessage = document.getElementById('status-message');
        const backLinkContainer = document.getElementById('back-link-container');
        const fileListContainer = document.getElementById('file-list-container');
        
        const initialFolderId = '<?php echo $initialFolderId; ?>';
        const defaultFolderId = '<?php echo $defaultFolderId; ?>';

        // Función para cargar el contenido de una carpeta vía AJAX
        async function loadFolder(folderId) {
            // Muestra un estado de carga
            fileListContainer.innerHTML = '<p class="no-files">Cargando archivos...</p>';
            folderTitle.textContent = 'Cargando...';
            statusMessage.style.display = 'none';

            try {
                const response = await fetch(`get_files.php?folderId=${folderId}`);
                if (!response.ok) {
                    throw new Error(`Error de red: ${response.status}`);
                }
                const data = await response.json();

                // Actualiza la UI con los datos recibidos
                folderTitle.innerHTML = `Archivos en la carpeta "${escapeHTML(data.folderName)}"`;
                
                statusMessage.textContent = data.message;
                statusMessage.className = `status ${data.status === 'error' ? 'status-error' : 'status-success'}`;
                statusMessage.style.display = 'block';

                backLinkContainer.innerHTML = data.backLinkHtml;
                fileListContainer.innerHTML = data.fileListHtml;
                
                // Actualiza la URL en la barra de direcciones para soportar historial y recarga
                const url = new URL(window.location);
                url.searchParams.set('folderId', folderId);
                window.history.pushState({path: url.href}, '', url.href);

            } catch (error) {
                console.error('Error al cargar la carpeta:', error);
                statusMessage.textContent = '❌ Error de conexión al intentar cargar los archivos.';
                statusMessage.className = 'status status-error';
                statusMessage.style.display = 'block';
                fileListContainer.innerHTML = '<p class="no-files">No se pudo cargar el contenido. Revisa la consola para más detalles.</p>';
            }
        }

        // Delegación de eventos para manejar clics en carpetas y enlaces de "volver"
        document.body.addEventListener('click', async function(event) {
            // Clic en una carpeta o enlace de "volver"
            const folderLink = event.target.closest('a[data-folderid]');
            if (folderLink) {
                event.preventDefault(); // Previene la recarga de la página
                const folderId = folderLink.getAttribute('data-folderid');
                loadFolder(folderId);
                return; // Termina la ejecución para no procesar otros clics
            }

            // Clic en el botón de eliminar
            const deleteBtn = event.target.closest('.delete-btn');
            if (deleteBtn) {
                event.preventDefault();
                const fileId = deleteBtn.dataset.fileid;
                const fileName = deleteBtn.dataset.filename;
                
                if (confirm(`¿Estás seguro de que quieres mover a la papelera el archivo "${fileName}"?`)) {
                    await deleteFile(fileId, deleteBtn);
                }
            }
        });

        // Nueva función para eliminar un archivo vía AJAX
        async function deleteFile(fileId, buttonElement) {
            const listItem = buttonElement.closest('.file-item');
            listItem.style.opacity = '0.5'; // Feedback visual
            buttonElement.disabled = true;

            try {
                const response = await fetch('get_files.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', fileId: fileId })
                });

                const data = await response.json();

                statusMessage.textContent = data.message;
                statusMessage.className = `status ${!response.ok || data.status === 'error' ? 'status-error' : 'status-success'}`;
                statusMessage.style.display = 'block';

                if (response.ok && data.status === 'success') {
                    // Animación de salida y eliminación del DOM
                    listItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    listItem.style.transform = 'translateX(-20px)';
                    listItem.style.opacity = '0';
                    setTimeout(() => {
                        listItem.remove();
                        // Comprobar si la lista está vacía después de eliminar
                        if (fileListContainer.querySelector('.file-item') === null) {
                            fileListContainer.innerHTML = '<p class="no-files">La carpeta está vacía.</p>';
                        }
                    }, 300);
                } else {
                    // Hubo un error, restauramos la apariencia
                    listItem.style.opacity = '1';
                    buttonElement.disabled = false;
                }

            } catch (error) {
                console.error('Error al eliminar el archivo:', error);
                statusMessage.textContent = '❌ Error de conexión al intentar eliminar.';
                statusMessage.className = 'status status-error';
                statusMessage.style.display = 'block';
                listItem.style.opacity = '1';
                buttonElement.disabled = false;
            }
        }

        // Maneja los botones de atrás/adelante del navegador
        window.addEventListener('popstate', function(event) {
            const url = new URL(window.location);
            const folderId = url.searchParams.get('folderId') || defaultFolderId;
            loadFolder(folderId);
        });

        // Función para escapar HTML y prevenir ataques XSS
        function escapeHTML(str) {
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        // Carga inicial de la carpeta
        loadFolder(initialFolderId);
    });
    </script>
</body>
</html>
