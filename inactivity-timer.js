/**
 * Temporizador de Inactividad
 *
 * Este script monitorea la actividad del usuario (movimiento del mouse, clics, teclas)
 * y cierra la sesión después de un período de inactividad. Muestra una advertencia
 * antes de que la sesión expire, dando al usuario la oportunidad de continuar.
 *
 * @version 1.0
 */
function InactivityTimer(options) {
    // --- Configuración ---
    const config = {
        // Tiempo total de inactividad en milisegundos.
        // El usuario pidió 1 minuto (60,000 ms). Puedes cambiarlo a 5 minutos (300,000 ms).
        timeout: options.timeout || 300000,
        // Tiempo en milisegundos antes del timeout para mostrar la advertencia.
        warningTime: options.warningTime || 10000,
        // URL a la que se redirige al expirar el tiempo.
        logoutUrl: options.logoutUrl || '../login/logout.php',
        // Elementos del DOM para la advertencia.
        warningModal: document.getElementById(options.warningModalId),
        countdownSpan: document.getElementById(options.countdownSpanId),
    };

    let timeoutTimer;
    let warningTimer;
    let countdownInterval;

    // --- Funciones Principales ---

    /**
     * Inicia el temporizador principal de inactividad.
     */
    function startMainTimer() {
        // Limpia cualquier temporizador anterior para evitar duplicados.
        clearTimeout(warningTimer);
        clearTimeout(timeoutTimer);
        clearInterval(countdownInterval);
        hideWarning();

        // Temporizador para mostrar la advertencia.
        warningTimer = setTimeout(showWarning, config.timeout - config.warningTime);

        // Temporizador para el logout definitivo.
        timeoutTimer = setTimeout(logout, config.timeout);
    }

    /**
     * Muestra el modal de advertencia e inicia la cuenta regresiva.
     */
    function showWarning() {
        if (!config.warningModal) return;

        let remaining = config.warningTime / 1000;
        config.countdownSpan.textContent = remaining;
        config.warningModal.style.display = 'flex';

        countdownInterval = setInterval(() => {
            remaining--;
            config.countdownSpan.textContent = remaining;
            if (remaining <= 0) {
                clearInterval(countdownInterval);
            }
        }, 1000);
    }

    /**
     * Oculta el modal de advertencia.
     */
    function hideWarning() {
        if (config.warningModal) {
            config.warningModal.style.display = 'none';
        }
    }

    /**
     * Redirige al usuario a la página de logout.
     */
    function logout() {
        window.location.href = config.logoutUrl;
    }

    // --- Eventos de Actividad ---
    // Cualquier actividad del usuario reinicia el temporizador.
    ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, startMainTimer, { passive: true });
    });

    // --- Inicio ---
    // Inicia el temporizador cuando se carga la página.
    startMainTimer();
}