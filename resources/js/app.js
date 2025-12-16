import Echo from "laravel-echo";
import Pusher from "pusher-js";
import "./bootstrap";
import { PasswordValidator } from "./password-validator.js";

import.meta.glob(["../fonts/**"]);

// Export PasswordValidator globally for use in blade views
window.PasswordValidator = PasswordValidator;

// Toggle password visibility
window.togglePassword = (inputId) => {
    const input = document.getElementById(inputId);
    const button = document.querySelector(
        `button[onclick="togglePassword('${inputId}')"]`,
    );

    if (input.type === "password") {
        input.type = "text";
        button.setAttribute("aria-label", "Ocultar contrasena");
        button.classList.add("password-visible");
    } else {
        input.type = "password";
        button.setAttribute("aria-label", "Mostrar contrasena");
        button.classList.remove("password-visible");
    }
};

// Configure Laravel Echo for Reverb WebSockets
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: "reverb",
    key: "reverb-key",
    wsHost: window.location.hostname,
    wsPort: 8080,
    wssPort: 8080,
    forceTLS: false,
    enabledTransports: ["ws", "wss"],
});
