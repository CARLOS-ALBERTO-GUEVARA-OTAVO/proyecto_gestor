document.addEventListener("DOMContentLoaded", function () {
    let emailInput = document.getElementById("email");
    let passwordInput = document.getElementById("password");
    let mensajeError = document.getElementById("mensajeError");

    // Expresión regular básica para email
    let regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // Validación en tiempo real de email
    emailInput.addEventListener("input", function () {
        if (regexEmail.test(emailInput.value.trim())) {
            emailInput.classList.remove("input-error");
            emailInput.classList.add("input-ok");
            mensajeError.innerHTML = "";
        } else {
            emailInput.classList.remove("input-ok");
            emailInput.classList.add("input-error");
            mensajeError.innerHTML = "Por favor, ingresa un correo válido.";
        }
    });

    // Validación en tiempo real de contraseña
    passwordInput.addEventListener("input", function () {
        if (passwordInput.value.trim().length >= 6) {
            passwordInput.classList.remove("input-error");
            passwordInput.classList.add("input-ok");
            mensajeError.innerHTML = "";
        } else {
            passwordInput.classList.remove("input-ok");
            passwordInput.classList.add("input-error");
            mensajeError.innerHTML = "La contraseña debe tener al menos 6 caracteres.";
        }
    });

    // Validación final al enviar
    document.getElementById("loginForm").addEventListener("submit", function(event) {
        let email = emailInput.value.trim();
        let password = passwordInput.value.trim();
        mensajeError.innerHTML = "";

        if (!regexEmail.test(email)) {
            mensajeError.innerHTML = "Por favor, ingresa un correo válido.";
            event.preventDefault();
            return;
        }

        if (password.length < 6) {
            mensajeError.innerHTML = "La contraseña debe tener al menos 6 caracteres.";
            event.preventDefault();
            return;
        }
    });
});
