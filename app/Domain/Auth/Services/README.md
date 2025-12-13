# Auth Services

Servicios relacionados con autenticación y seguridad de contraseñas.

## PasswordStrengthCalculator

Servicio centralizado para validación de requisitos de contraseñas.

### Responsabilidades

- Validar que se cumplan TODOS los requisitos obligatorios
- Identificar qué requisitos faltan
- Proporcionar reglas de validación para Laravel
- **Principio DRY**: Una sola fuente de verdad para la lógica de validación

### Uso en Backend (PHP)

```php
use App\Domain\Auth\Services\PasswordStrengthCalculator;

// Validar requisitos mínimos
$meets = PasswordStrengthCalculator::meetsRequirements('MyP@ssw0rd'); // true/false

// Obtener requisitos faltantes
$missing = PasswordStrengthCalculator::getMissingRequirements('abc123'); 
// ['Una letra MAYÚSCULA', 'Un carácter especial...']

// Obtener reglas de validación para Laravel
$rules = PasswordStrengthCalculator::getLaravelRules();
$messages = PasswordStrengthCalculator::getValidationMessages();
```

### Uso en Frontend (JavaScript)

El servicio se exporta automáticamente como clase `PasswordValidator` en `resources/js/password-validator.js`.

```blade
{{-- En tu vista Blade --}}
<x-password-validation-message />
<x-password-match-indicator />

<script>
document.addEventListener('DOMContentLoaded', function() {
    new window.PasswordValidator('password', 'password_confirmation');
});
</script>
```

### Requisitos Mínimos Obligatorios

Para que una contraseña sea válida, **DEBE cumplir TODOS** estos criterios:

| Requisito | Descripción |
|-----------|-------------|
| ✅ Longitud | Mínimo 8 caracteres |
| ✅ Minúscula | Al menos una letra minúscula (a-z) |
| ✅ Mayúscula | Al menos una letra MAYÚSCULA (A-Z) |
| ✅ Número | Al menos un número (0-9) |
| ✅ Especial | Al menos un carácter especial (!@#$%...) |

### Validación (Todo o Nada)

La contraseña **DEBE cumplir TODOS los requisitos** para ser válida. No hay niveles intermedios.

| Estado | Descripción |
|--------|-------------|
| ❌ **Inválida** | Falta uno o más requisitos |
| ✅ **Válida** | Cumple los 5 requisitos |

### Componentes Blade

#### `<x-password-validation-message />`
Muestra mensaje "✅ Contraseña válida" cuando cumple todos los requisitos.

#### `<x-password-match-indicator />`
Muestra mensaje de coincidencia entre contraseña y confirmación.

### Arquitectura

```
app/Domain/Auth/Services/
└── PasswordStrengthCalculator.php  ← Lógica de validación (PHP)

resources/js/
└── password-validator.js           ← Clase para frontend (JS)

resources/views/components/
├── password-validation-message.blade.php  ← Componente UI (mensaje validación)
└── password-match-indicator.blade.php     ← Componente UI (coincidencia)

app/Domain/Admin/Filament/Resources/
└── UserResource.php                ← Usa el servicio para validación
```

### Principios Aplicados

✅ **DRY**: Una sola fuente de verdad para el cálculo  
✅ **Single Responsibility**: Cada clase tiene una responsabilidad clara  
✅ **Open/Closed**: Fácil extender sin modificar código existente  
✅ **Domain-Driven Design**: Servicio en el dominio correcto (`Auth`)  
✅ **Separation of Concerns**: Lógica separada de la presentación  
✅ **Reusability**: Componentes Blade reutilizables

