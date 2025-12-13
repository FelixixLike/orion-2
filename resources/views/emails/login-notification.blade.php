@php
    /** @var \App\Domain\User\Models\User $user */
    /** @var \Illuminate\Support\Carbon $loggedAt */
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo inicio de sesión</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f5f5f5; margin:0; padding:0;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding: 24px 0;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color:#ffffff; border-radius:8px; padding:24px;">
                <tr>
                    <td style="font-size:16px; color:#111827;">
                        <p style="margin:0 0 16px 0;">Hola {{ $user->getFilamentName() }},</p>

                        <p style="margin:0 0 12px 0;">
                            Registramos un nuevo inicio de sesión en el
                            <strong>{{ $channelLabel }}</strong> de {{ config('app.name') }}.
                        </p>

                        <p style="margin:0 0 8px 0; font-size:14px; color:#374151;">
                            <strong>Fecha y hora:</strong>
                            {{ $loggedAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                        </p>
                        <p style="margin:0 0 8px 0; font-size:14px; color:#374151;">
                            <strong>Dirección IP:</strong> {{ $ipAddress }}
                        </p>

                        <p style="margin:16px 0 0 0; font-size:14px; color:#374151;">
                            Si fuiste tú, no necesitas hacer nada más.
                            Si no reconoces este acceso, te recomendamos cambiar tu contraseña
                            y contactar al equipo de soporte de inmediato.
                        </p>

                        <p style="margin:24px 0 0 0; font-size:13px; color:#6b7280;">
                            Este mensaje se envía automáticamente cada vez que inicias sesión
                            con tu usuario registrado.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

