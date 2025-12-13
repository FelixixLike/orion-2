<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 5px;
        }

        .header {
            background-color: #9d0b1d;
            color: white;
            padding: 10px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }

        .footer {
            font-size: 12px;
            color: #777;
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2>Nuevo Inicio de Sesión Detectado</h2>
        </div>
        <p>Hola <strong>{{ $user->name }}</strong>,</p>
        <p>Hemos detectado un nuevo inicio de sesión en tu cuenta de <strong>Orion</strong>.</p>

        <p><strong>Detalles:</strong></p>
        <ul>
            <li><strong>Fecha y Hora:</strong> {{ $time }}</li>
        </ul>

        <p>Si fuiste tú, puedes ignorar este mensaje. Si no reconoces esta actividad, por favor contacta al
            administrador inmediatamente.</p>

        <div class="footer">
            <p>Este es un mensaje automático de seguridad de la plataforma Orion.</p>
        </div>
    </div>
</body>

</html>