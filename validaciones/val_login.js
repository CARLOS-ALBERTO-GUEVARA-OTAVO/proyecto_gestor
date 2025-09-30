document.addEventListener("DOMContentLoaded", function () {
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const mensajeError = document.getElementById("mensajeError");
    const loginForm = document.getElementById("loginForm");

    const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function showError(message) {
        mensajeError.textContent = message;
        mensajeError.style.display = message ? 'block' : 'none';
    }

    // Validación en tiempo real de email
    emailInput.addEventListener("input", function () {
        if (regexEmail.test(emailInput.value.trim())) {
            emailInput.classList.remove("input-error");
            emailInput.classList.add("input-ok");
            showError("");
        } else {
            emailInput.classList.remove("input-ok");
            emailInput.classList.add("input-error");
        }
    });

    // Validación en tiempo real de contraseña
    passwordInput.addEventListener("input", function () {
        if (passwordInput.value.length >= 6) {
            passwordInput.classList.remove("input-error");
            passwordInput.classList.add("input-ok");
            showError("");
        } else {
            passwordInput.classList.remove("input-ok");
            passwordInput.classList.add("input-error");
        }
    });

    // Validación final al enviar
    loginForm.addEventListener("submit", function(event) {
        const email = emailInput.value.trim();
        const password = passwordInput.value;
        showError("");

        if (!regexEmail.test(email)) {
            showError("Por favor, ingresa un correo electrónico válido.");
            emailInput.focus();
            event.preventDefault();
            return;
        }

        if (password.length === 0) {
            showError("Por favor, ingresa tu contraseña.");
            passwordInput.focus();
            event.preventDefault();
            return;
        }
    });
});
