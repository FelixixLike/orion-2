<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" style="width: 600px; border-collapse: collapse; background-color: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 30px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: bold;">
                                {{ config('app.name') }}
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="margin: 0 0 20px 0; color: #333333; font-size: 24px;">
                                ¡Bienvenido, {{ $user->first_name }}!
                            </h2>
                            
                            <p style="margin: 0 0 15px 0; color: #666666; font-size: 16px; line-height: 1.5;">
                                Se ha creado tu cuenta en <strong>{{ config('app.name') }}</strong>.
                            </p>
                            
                            <div style="margin: 20px 0; padding: 20px; background-color: #f8f9fa; border-left: 4px solid #667eea; border-radius: 4px;">
                                <p style="margin: 0 0 10px 0; color: #333333; font-size: 16px; font-weight: bold;">
                                    📝 Tus credenciales:
                                </p>
                                <p style="margin: 0 0 5px 0; color: #666666; font-size: 15px;">
                                    <strong>Usuario:</strong> {{ $user->username }}
                                </p>
                                <p style="margin: 0; color: #666666; font-size: 15px;">
                                    <strong>URL de acceso:</strong> {{ $loginUrl }}
                                </p>
                            </div>
                            
                            <div style="margin: 25px 0; padding: 15px; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                                <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.5;">
                                    <strong>🔐 Importante:</strong> Tu supervisor te proporcionará la contraseña inicial por un canal seguro.
                                    @if($mustChangePassword)
                                    Deberás cambiarla en tu primer acceso por seguridad.
                                    @endif
                                </p>
                            </div>
                            
                            <!-- Button -->
                            <table role="presentation" style="margin: 25px auto;">
                                <tr>
                                    <td style="border-radius: 4px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <a href="{{ $loginUrl }}" 
                                           style="display: inline-block; padding: 16px 36px; font-size: 16px; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold;">
                                            Ir al panel de administración
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                                <p style="margin: 0 0 10px 0; color: #999999; font-size: 14px;">
                                    💡 <strong>Consejo de seguridad:</strong>
                                </p>
                                <ul style="margin: 0; padding-left: 20px; color: #999999; font-size: 14px; line-height: 1.8;">
                                    <li>Usa una contraseña fuerte (mínimo 8 caracteres)</li>
                                    <li>No compartas tu contraseña con nadie</li>
                                    <li>Cierra sesión al terminar de usar el sistema</li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px; text-align: center; background-color: #f8f9fa; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0 0 10px 0; color: #999999; font-size: 14px;">
                                © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
                            </p>
                            <p style="margin: 0; color: #999999; font-size: 12px;">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

