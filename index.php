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
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f7f9; /* Fondo gris claro para dar contraste */
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .header-top { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            width: 100%; 
            margin-bottom: 10px;
        }

        #folder-title {
            font-size: 1.5rem;
            font-weight: 500;
            color: #111827;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 20px;
        }

        .status {
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: opacity 0.5s ease-in-out;
            margin: 0;
        }
        .status-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .status-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        main {
            padding: 16px 24px 24px;
        }

        #back-link-container { margin-bottom: 16px; }
        .back-link { color: #007bff; text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }

        .file-list { list-style: none; padding: 0; margin: 0; }
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 8px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease-in-out;
        }
        .file-item:last-child { border-bottom: none; }
        .file-item:hover { background-color: #f9fafb; }

        .file-item a {
            flex-grow: 1;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #1f2937;
            font-weight: 500;
        }
        .file-item a:hover { color: #0056b3; }
        .file-icon { width: 20px; height: 20px; margin-right: 12px; }

        .file-dates {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-size: 0.8em;
            color: #6b7280;
            flex-shrink: 0;
            padding-left: 16px;
        }

        .no-files { color: #6b7280; padding: 40px 0; text-align: center; font-style: italic; }

        .search-form { display: flex; }
        .search-input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px 0 0 6px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .search-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.4); }

        .search-button {
            padding: 8px 16px;
            border: 1px solid #16a34a;
            background-color: #22c55e;
            color: white;
            border-radius: 0 6px 6px 0;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .search-button:hover { background-color: #16a34a; }

        .file-location { font-size: 0.8em; color: #6b7280; text-align: right; padding-left: 16px; }
        .file-location a { color: #007bff; text-decoration: none; font-weight: normal; }
        .file-location a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="container">
        <header class="header">
            <div class="header-top">
                <h1 id="folder-title">Cargando...</h1>
                <form id="search-form" class="search-form">
                    <input type="search" id="search-input" placeholder="Buscar archivos..." class="search-input">
                    <button type="submit" class="search-button">Buscar</button>
                </form>
            </div>
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
        const searchForm = document.getElementById('search-form');
        const searchInput = document.getElementById('search-input');
        
        const initialFolderId = '<?php echo $initialFolderId; ?>';
        const defaultFolderId = '<?php echo $defaultFolderId; ?>';

        // Función centralizada para mostrar mensajes de estado y ocultarlos si son de éxito.
        function showStatusMessage(message, status) {
            statusMessage.textContent = message;
            statusMessage.className = `status status-${status}`;
            statusMessage.style.display = 'block';
            statusMessage.style.opacity = '1';

            // Si es un mensaje de éxito, se desvanece y desaparece después de un tiempo.
            if (status === 'success') {
                setTimeout(() => {
                    statusMessage.style.opacity = '0';
                    setTimeout(() => {
                        statusMessage.style.display = 'none';
                    }, 500); // Coincide con la duración de la transición en CSS
                }, 3500); // El mensaje es visible por 3.5 segundos
            }
        }

        // Función para cargar el contenido de una carpeta vía AJAX
        async function loadFolder(folderId) {
            // Muestra un estado de carga
            fileListContainer.innerHTML = '<p class="no-files">Cargando archivos...</p>';
            folderTitle.textContent = 'Cargando...';
            statusMessage.style.display = 'none';

            try {
                // Se añade un parámetro `_` con la fecha actual para evitar que el navegador cachee la respuesta.
                const response = await fetch(`get_files.php?folderId=${folderId}&_=${new Date().getTime()}`);
                if (!response.ok) {
                    throw new Error(`Error de red: ${response.status}`);
                }
                const data = await response.json();

                // Actualiza la UI con los datos recibidos
                folderTitle.innerHTML = `Archivos en la carpeta "${escapeHTML(data.folderName)}"`;
                showStatusMessage(data.message, data.status);

                backLinkContainer.innerHTML = data.backLinkHtml;
                fileListContainer.innerHTML = data.fileListHtml;
                
                // Actualiza la URL en la barra de direcciones para soportar historial y recarga
                const url = new URL(window.location);
                url.searchParams.set('folderId', folderId);
                window.history.pushState({path: url.href}, '', url.href);

            } catch (error) {
                console.error('Error al cargar la carpeta:', error);
                showStatusMessage('❌ Error de conexión al intentar cargar los archivos.', 'error');
                fileListContainer.innerHTML = '<p class="no-files">No se pudo cargar el contenido. Revisa la consola para más detalles.</p>';
            }
        }

        // Nueva función para buscar archivos en todo el Drive
        async function searchFiles(query) {
            fileListContainer.innerHTML = '<p class="no-files">Buscando archivos...</p>';
            folderTitle.textContent = `Resultados para "${escapeHTML(query)}"`;
            statusMessage.style.display = 'none';
            backLinkContainer.innerHTML = ''; // Ocultamos el enlace "volver" en la vista de búsqueda

            try {
                // Usamos encodeURIComponent para asegurar que caracteres especiales se envíen correctamente
                const response = await fetch(`get_files.php?q=${encodeURIComponent(query)}&_=${new Date().getTime()}`);
                if (!response.ok) {
                    throw new Error(`Error de red: ${response.status}`);
                }
                const data = await response.json();

                showStatusMessage(data.message, data.status);

                fileListContainer.innerHTML = data.fileListHtml;

            } catch (error) {
                console.error('Error en la búsqueda:', error);
                showStatusMessage('❌ Error de conexión al intentar buscar.', 'error');
                fileListContainer.innerHTML = '<p class="no-files">No se pudo realizar la búsqueda.</p>';
            }
        }

        // Delegación de eventos para manejar clics en carpetas y enlaces de "volver"
        document.body.addEventListener('click', function(event) {
            // Clic en una carpeta o enlace de "volver"
            const folderLink = event.target.closest('a[data-folderid]');
            if (folderLink) {
                event.preventDefault(); // Previene la recarga de la página
                const folderId = folderLink.getAttribute('data-folderid');
                loadFolder(folderId);
            }
        });

        // Manejar el envío del formulario de búsqueda
        searchForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const query = searchInput.value.trim();
            if (query) {
                searchFiles(query);
            } else {
                // Si la búsqueda está vacía, volvemos a cargar la carpeta principal
                loadFolder(defaultFolderId);
            }
        });

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
