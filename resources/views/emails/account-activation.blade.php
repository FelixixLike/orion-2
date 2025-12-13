<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activa tu cuenta</title>
</head>

<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation"
                    style="width: 600px; border-collapse: collapse; background-color: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td
                            style="padding: 40px 30px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
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
                                Se ha creado una cuenta para ti en <strong>{{ config('app.name') }}</strong>.
                            </p>

                            <p style="margin: 0 0 15px 0; color: #666666; font-size: 16px; line-height: 1.5;">
                                Tu nombre de usuario es: <strong>{{ $user->username }}</strong>
                            </p>

                            <p style="margin: 0 0 25px 0; color: #666666; font-size: 16px; line-height: 1.5;">
                                Para activar tu cuenta y crear tu contraseña, haz clic en el siguiente botón:
                            </p>

                            <!-- Button -->
                            <table role="presentation" style="margin: 0 auto;">
                                <tr>
                                    <td
                                        style="border-radius: 4px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <a href="{{ $activationUrl }}"
                                            style="display: inline-block; padding: 16px 36px; font-size: 16px; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold;">
                                            Activar mi cuenta
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 25px 0 15px 0; color: #999999; font-size: 14px; line-height: 1.5;">
                                O copia y pega este enlace en tu navegador:
                            </p>

                            <p
                                style="margin: 0 0 25px 0; padding: 12px; background-color: #f8f9fa; border-radius: 4px; color: #667eea; font-size: 14px; word-break: break-all;">
                                {{ $activationUrl }}
                            </p>

                            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                                <p style="margin: 0 0 10px 0; color: #999999; font-size: 14px;">
                                    ⏰ Este enlace expirará en <strong>{{ $expiresInHours }} horas</strong>.
                                </p>
                                <p style="margin: 0; color: #999999; font-size: 14px;">
                                    Si no solicitaste esta cuenta, puedes ignorar este email.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            style="padding: 30px; text-align: center; background-color: #f8f9fa; border-top: 1px solid #e0e0e0;">
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
