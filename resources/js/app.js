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
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "https") === "https",
    enabledTransports: ["ws", "wss"],
});
