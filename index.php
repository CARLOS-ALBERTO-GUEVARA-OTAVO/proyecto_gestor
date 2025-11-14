<?php
session_start();
// Si el usuario no ha iniciado sesión, redirigirlo a la página de login.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login/login.php');
    exit;
}

// Obtenemos el folderId inicial para la carga de la página.
// Esto permite que los enlaces directos a carpetas específicas sigan funcionando.
$defaultFolderId = "1w1X74_EI9LDVhkTrrgA89etnvofGhYSN";
$initialFolderId = filter_input(INPUT_GET, 'folderId', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: $defaultFolderId;
// Si se pasa un folderId en la URL, se usará. Si no, se deja vacío para que el backend decida.
// Esto permite que los enlaces directos a carpetas específicas sigan funcionando (si el usuario tiene permiso).
$initialFolderId = filter_input(INPUT_GET, 'folderId', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visor de Archivos de Google Drive</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* --- Reset Básico y Variables de Color --- */
        :root {
            --color-bg: #f8f9fa;
            --color-surface: #ffffff;
            --color-primary: #007bff;
            --color-primary-dark: #0056b3;
            --color-text-primary: #212529;
            --color-text-secondary: #6c757d;
            --color-border: #dee2e6;
            --color-success: #198754;
            --color-success-bg: #d1e7dd;
            --color-error: #dc3545;
            --color-error-bg: #f8d7da;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--color-bg);
            color: var(--color-text-primary);
            margin: 0;
        }

        /* --- Modal de Advertencia de Inactividad --- */
        .inactivity-modal {
            display: none; /* Oculto por defecto */
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
        }
        .inactivity-modal-content {
            background-color: #fff;
            padding: 25px 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,.5);
            max-width: 400px;
        }

        /* --- Barra de Información del Usuario --- */
        .user-info-header {
            background-color: var(--color-surface);
            padding: 10px 20px;
            text-align: center;
            border-bottom: 1px solid var(--color-border);
            font-size: 0.9rem;
            color: var(--color-text-secondary);
            box-shadow: var(--shadow-sm);
        }

        .container {
            /* Aumentamos el ancho máximo para aprovechar mejor las pantallas grandes */
            max-width: 1200px; 
            margin: 20px auto;
            padding: 0 15px;
        }

        .card {
            /* La tarjeta ahora ocupará el 100% del ancho de su contenedor padre */
            width: 100%; 
            background-color: var(--color-surface);
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .card-wrapper {
            display: flex; /* Usamos flexbox para centrar la tarjeta fácilmente */
        }

        .header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--color-border);
        }

        .header-top { 
            display: flex; 
            flex-wrap: wrap;
            gap: 16px;
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 16px;
        }

        #folder-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-text-primary);
            margin: 0;
        }

        .status {
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: opacity 0.5s ease-in-out;
            margin: 0;
        }
        .status-success {
            background-color: var(--color-success-bg);
            color: var(--color-success);
        }
        .status-error {
            background-color: var(--color-error-bg);
            color: var(--color-error);
        }

        main {
            padding: 16px 24px 24px;
        }

        /* --- Breadcrumbs (Ruta de navegación) --- */
        #breadcrumb-container {
            margin-bottom: 16px;
            font-size: 0.95rem;
            color: var(--color-text-secondary);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        #breadcrumb-container a {
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        #breadcrumb-container a:hover {
            color: var(--color-primary-dark);
            text-decoration: underline;
        }
        #breadcrumb-container .separator { margin: 0 8px; }
        #breadcrumb-container .current-folder { font-weight: 500; color: var(--color-text-primary); }

        /* --- Lista de Archivos --- */
        .file-list { list-style: none; padding: 0; margin: 0; }
        .file-item {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start; /* Alineamos al inicio para mejor estructura vertical */
            padding: 12px 8px;
            border-bottom: 1px solid var(--color-border);
            transition: background-color 0.2s ease-in-out;
        }
        .file-item:last-child { border-bottom: none; }
        .file-item:hover { background-color: var(--color-bg); }

        .file-item a {
            flex-grow: 1;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #1f2937;
            font-weight: 500;
            font-size: 1rem; /* Aumentamos el tamaño del nombre del archivo */
            min-width: 250px; /* Evita que el nombre se comprima demasiado */
            padding-top: 4px; /* Pequeño ajuste vertical */
        }
        .file-item a:hover { color: var(--color-primary-dark); }
        .file-icon { width: 22px; height: 22px; margin-right: 14px; flex-shrink: 0; }

        .file-dates {
            display: flex;
            flex-direction: column;
            align-items: flex-end; /* Alineamos las fechas a la derecha */
            font-size: 0.875rem; /* Aumentamos el tamaño de la fuente de las fechas */
            color: var(--color-text-secondary);
            flex-shrink: 0;
            padding-left: 16px;
            text-align: right; /* Alineamos el texto a la derecha */
            line-height: 1.5; /* Mejoramos el espaciado entre líneas */
        }

        .no-files { color: var(--color-text-secondary); padding: 40px 0; text-align: center; font-style: italic; }

        /* --- Formulario de Búsqueda --- */
        .search-form { display: flex; flex-grow: 1; max-width: 400px; }
        .search-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--color-border);
            border-radius: 6px 0 0 6px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .search-input:focus { 
            outline: none; 
            border-color: var(--color-primary); 
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25); 
        }

        .search-button {
            padding: 10px 16px;
            border: 1px solid var(--color-primary);
            background-color: var(--color-primary);
            color: white;
            border-radius: 0 6px 6px 0;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .search-button:hover { background-color: var(--color-primary-dark); }

        /* --- Ubicación del archivo en resultados de búsqueda --- */
        .file-location {
            font-size: 0.875rem; /* Mismo tamaño que las fechas */
            color: var(--color-text-secondary);
            text-align: right; /* Aseguramos alineación a la derecha */
            margin-bottom: 4px; /* Espacio entre la ubicación y la fecha de modificación */
        }
        .file-location > span {
            font-weight: 500; /* Hacemos "En carpeta:" un poco más notorio */
            color: #555;
        }

        .file-location a { color: var(--color-primary); text-decoration: none; font-weight: normal; }
        .file-location a:hover { text-decoration: underline; }

        /* --- Botón de Cerrar Sesión --- */
        .footer-actions {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid var(--color-border);
            text-align: right;
        }
        .logout-link {
            color: var(--color-error);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        .logout-link:hover {
            background-color: var(--color-error-bg);
        }

        /* --- Media Queries para Responsividad --- */
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                align-items: flex-start;
            }
            .search-form {
                width: 100%;
                max-width: none;
            }
            .file-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .file-dates {
                align-items: flex-start; /* Mantenemos la alineación para móvil */
                padding-left: 0;
                margin-top: 8px;
                width: 100%;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>

    <header class="user-info-header">
        <p style="margin: 0;">
            Usuario: <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong> | 
            Cargo: <strong><?php echo htmlspecialchars($_SESSION['usuario_cargo_nombre'] ?? 'No asignado'); ?></strong> | 
            Rol: <strong><?php echo htmlspecialchars($_SESSION['usuario_rol_nombre'] ?? 'No asignado'); ?></strong>
        </p>
    </header>

    <!-- Modal de Advertencia de Inactividad -->
    <div id="inactivity-warning-modal" class="inactivity-modal">
        <div class="inactivity-modal-content">
            <h2>¡Tu sesión está a punto de expirar!</h2>
            <p>Por seguridad, tu sesión se cerrará automáticamente por inactividad.</p>
            <p>La sesión se cerrará en <strong id="countdown-timer">10</strong> segundos.</p>
            <p><small>Mueve el mouse o presiona cualquier tecla para continuar.</small></p>
        </div>
    </div>

    <div class="container">
        <div class="card">
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
                <div id="breadcrumb-container"></div>
                <div id="file-list-container">
                    <p class="no-files">Cargando archivos...</p>
                </div>
            </div>
            <div class="footer-actions">
                <a href="login/logout.php" class="logout-link">Cerrar sesión 🔒</a>
            </div>
        </main>
    </div>

    <script src="inactivity-timer.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const folderTitle = document.getElementById('folder-title');
        const statusMessage = document.getElementById('status-message');
        const breadcrumbContainer = document.getElementById('breadcrumb-container');
        const fileListContainer = document.getElementById('file-list-container');
        const searchForm = document.getElementById('search-form');
        const searchInput = document.getElementById('search-input');
        
        const initialFolderId = '<?php echo $initialFolderId; ?>';
        const defaultFolderId = '<?php echo $defaultFolderId; ?>';
        let debounceTimer; // Para el "debouncing" de la búsqueda

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
            breadcrumbContainer.innerHTML = '';
            statusMessage.style.display = 'none';

            try {
                // Se añade un parámetro `_` con la fecha actual para evitar que el navegador cachee la respuesta.
                const response = await fetch(`get_files.php?folderId=${folderId || ''}&_=${new Date().getTime()}`);
                if (!response.ok) {
                    throw new Error(`Error de red: ${response.status}`);
                }
                const data = await response.json();

                // Actualiza la UI con los datos recibidos
                folderTitle.textContent = 'Visor de Archivos'; // Título más genérico
                if (data.folderName) { folderTitle.textContent = data.folderName; }

                showStatusMessage(data.message, data.status);

                breadcrumbContainer.innerHTML = data.breadcrumbHtml;
                fileListContainer.innerHTML = data.fileListHtml;
                
                // Actualiza la URL en la barra de direcciones para soportar historial y recarga
                const url = new URL(window.location.href.split('?')[0]); // URL base sin parámetros
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
            folderTitle.textContent = `Resultados para "${query}"`; // El query ya está sanitizado en el HTML con escapeHTML
            statusMessage.style.display = 'none';
            breadcrumbContainer.innerHTML = ''; // Ocultamos la ruta de navegación en la vista de búsqueda

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
            // Clic en una carpeta (desde la lista normal o desde los resultados de búsqueda)
            const folderLink = event.target.closest('a[data-folderid]');
            if (folderLink) {
                event.preventDefault(); // Previene la recarga de la página
                const folderId = folderLink.getAttribute('data-folderid');

                // Si venimos de una búsqueda, limpiamos el input para que no quede el texto.
                if (searchInput.value.trim() !== '') {
                    searchInput.value = '';
                }

                loadFolder(folderId);
            }
        });

        // Función que decide si buscar o cargar la carpeta por defecto
        function performSearchOrLoadDefault() {
            const query = searchInput.value.trim();
            if (query) {
                searchFiles(query);
            } else {
                loadFolder(''); // Cargar la vista raíz del usuario
            }
        }

        // Manejar el envío del formulario de búsqueda (al presionar Enter o hacer clic en el botón)
        searchForm.addEventListener('submit', function(event) {
            event.preventDefault();
            clearTimeout(debounceTimer); // Cancelamos cualquier búsqueda temporizada pendiente
            performSearchOrLoadDefault();
        });

        // Búsqueda en tiempo real mientras el usuario escribe
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer); // Limpiamos el temporizador anterior en cada pulsación
            debounceTimer = setTimeout(performSearchOrLoadDefault, 400); // Esperamos 400ms después de la última pulsación para ejecutar la búsqueda
        });

        // Maneja los botones de atrás/adelante del navegador
        window.addEventListener('popstate', function(event) {
            const url = new URL(window.location.href);
            const folderId = url.searchParams.get('folderId');
            loadFolder(folderId || '');
        });

        // Carga inicial de la carpeta
        loadFolder(initialFolderId);

        // Inicializa el temporizador de inactividad
        const inactivityTimer = new InactivityTimer({
            logoutUrl: 'login/logout.php',
            warningModalId: 'inactivity-warning-modal',
            countdownSpanId: 'countdown-timer'
        });
    });
    </script>
</body>
</html>
