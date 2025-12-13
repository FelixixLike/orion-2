/**
 * Password Strength Calculator
 * Calcula la fortaleza de una contrasena y valida coincidencia
 */

export class PasswordValidator {
    constructor(passwordInputId, confirmInputId = null) {
        this.passwordInput = document.getElementById(passwordInputId);
        this.confirmInput = confirmInputId ? document.getElementById(confirmInputId) : null;
        
        this.strengthIndicator = null;
        this.strengthText = null;
        this.strengthBar = null;
        this.matchIndicator = null;
        this.matchMessage = null;
        this.requirementElements = {};
        this.icons = {
            check: `<svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>`,
            circle: `<svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="3" opacity="0.5"/></svg>`,
        };
        
        this.init();
    }
    
    init() {
        if (!this.passwordInput) return;
        
        // Buscar elementos del DOM
        this.validationMessage = document.getElementById('password-validation-message');
        this.validationText = document.getElementById('validation-text');
        this.matchIndicator = document.getElementById('password-match-indicator');
        this.matchMessage = document.getElementById('match-message');
        this.requirementElements = {
            length: document.getElementById('req-length'),
            lowercase: document.getElementById('req-lowercase'),
            uppercase: document.getElementById('req-uppercase'),
            number: document.getElementById('req-number'),
            special: document.getElementById('req-special'),
        };
        
        // Event listeners
        this.passwordInput.addEventListener('input', () => this.onPasswordChange());
        
        if (this.confirmInput) {
            this.confirmInput.addEventListener('input', () => this.checkMatch());
        }
    }
    
    onPasswordChange() {
        const password = this.passwordInput.value;
        
        if (!password || password.length === 0) {
            this.hideValidationMessage();
            this.resetRequirements();
            return;
        }
        
        this.updateRequirementsUI(password);
        this.updateValidationMessage(password);
        
        // Validar coincidencia si ya hay algo en confirmacion
        if (this.confirmInput && this.confirmInput.value) {
            this.checkMatch();
        }
    }
    
    meetsRequirements(password) {
        if (!password || password.length < 8) return false;
        
        return /[a-z]/.test(password) &&  // Minuscula
               /[A-Z]/.test(password) &&  // Mayuscula
               /\d/.test(password) &&      // Numero
               /[^a-zA-Z0-9]/.test(password); // Especial
    }
    
    getMissingRequirements(password) {
        const missing = [];
        
        if (!password) return ['Ingresa una contrasena'];
        
        if (password.length < 8) missing.push('Minimo 8 caracteres');
        if (!/[a-z]/.test(password)) missing.push('Una letra minuscula');
        if (!/[A-Z]/.test(password)) missing.push('Una letra MAYUSCULA');
        if (!/\d/.test(password)) missing.push('Un numero');
        if (!/[^a-zA-Z0-9]/.test(password)) missing.push('Un caracter especial (!@#$%...)');
        
        return missing;
    }
    
    updateValidationMessage(password) {
        if (!this.validationMessage || !this.validationText) return;
        
        const meetsAll = this.meetsRequirements(password);
        
        if (meetsAll) {
            // Mostrar mensaje de exito
            this.validationMessage?.classList.remove('hidden');
            this.validationText.innerHTML = `
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Contrasena valida
            `;
            this.validationText.className = 'flex items-center gap-1.5 text-xs font-semibold text-green-400';
            
            // Quitar estilos de error del input si existen
            this.removeInputError();
        } else {
            // Ocultar mensaje si no cumple todos los requisitos
            this.validationMessage?.classList.add('hidden');
        }
    }
    
    removeInputError() {
        // Quitar clases de error y mensaje de error del servidor
        if (this.passwordInput) {
            // Remover atributos de error
            this.passwordInput.removeAttribute('aria-invalid');
            this.passwordInput.removeAttribute('aria-describedby');
            this.passwordInput.removeAttribute('data-has-error');
            
            // Reemplazar clases de error con clases normales
            this.passwordInput.className = this.passwordInput.className
                .replace('border-red-400', 'border-white/80')
                .replace('ring-1 ring-red-400/50', '')
                .replace('focus:ring-red-400', 'focus:ring-white')
                .replace('focus:border-red-400', 'focus:border-white');
            
            // Ocultar mensaje de error del servidor si existe
            const errorMessage = this.passwordInput.parentElement?.nextElementSibling;
            if (errorMessage && errorMessage.id === 'password-error') {
                errorMessage.style.display = 'none';
            }
        }
    }
    
    checkMatch() {
        if (!this.confirmInput || !this.matchIndicator || !this.matchMessage) return;
        
        const password = this.passwordInput.value;
        const confirmation = this.confirmInput.value;
        
        if (!confirmation || confirmation.length === 0) {
            this.hideMatchIndicator();
            return;
        }
        
        this.showMatchIndicator();
        
        if (password === confirmation) {
            this.matchMessage.innerHTML = `
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Las contrasenas coinciden
            `;
            this.matchMessage.className = 'flex items-center gap-1.5 text-xs font-semibold text-green-400';
        } else {
            this.matchMessage.innerHTML = `
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                Las contrasenas no coinciden
            `;
            this.matchMessage.className = 'flex items-center gap-1.5 text-xs font-semibold text-red-400';
        }
    }
    
    hideValidationMessage() {
        this.validationMessage?.classList.add('hidden');
    }
    
    showMatchIndicator() {
        this.matchIndicator?.classList.remove('hidden');
    }
    
    hideMatchIndicator() {
        this.matchIndicator?.classList.add('hidden');
    }
    
    updateRequirementsUI(password) {
        this.updateRequirement('length', 'Al menos 8 caracteres', password.length >= 8);
        this.updateRequirement('lowercase', 'Una letra minuscula (a-z)', /[a-z]/.test(password));
        this.updateRequirement('uppercase', 'Una letra MAYUSCULA (A-Z)', /[A-Z]/.test(password));
        this.updateRequirement('number', 'Un numero (0-9)', /\d/.test(password));
        this.updateRequirement('special', 'Un caracter especial (!@#$%...)', /[^a-zA-Z0-9]/.test(password));
    }
    
    updateRequirement(key, text, isMet) {
        const elem = this.requirementElements?.[key];
        if (!elem) return;
        
        const icon = isMet ? this.icons.check : this.icons.circle;
        const colorClass = isMet ? 'text-green-400' : 'text-white/40';
        
        elem.innerHTML = `
            <span class="${colorClass} req-icon">${icon}</span>
            ${text}
        `;
        elem.style.opacity = isMet ? '1' : '0.7';
    }
    
    resetRequirements() {
        Object.values(this.requirementElements).forEach(elem => {
            if (elem) {
                elem.style.opacity = '0.6';
            }
        });
    }
}

// Inicializacion global para uso en vistas
if (typeof window !== 'undefined') {
    window.PasswordValidator = PasswordValidator;
}
