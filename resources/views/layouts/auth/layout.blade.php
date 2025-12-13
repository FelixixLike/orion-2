<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $context === 'admin' ? 'Iniciar sesión - Administrador' : 'Iniciar sesión - Portal de Tenderos' }} |
        Tender Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen font-montserrat bg-gradient-to-br from-primary-900 via-primary-700 to-gray-950 text-white relative overflow-hidden">
    <div class="absolute inset-0 opacity-50 mix-blend-screen pointer-events-none" aria-hidden="true">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(255,255,255,0.12),transparent_35%),radial-gradient(circle_at_80%_0%,rgba(255,255,255,0.08),transparent_25%)]"></div>
        @include('components.svg.background')
    </div>

    <main class="relative z-10 w-full max-w-5xl mx-auto min-h-screen flex items-center px-5 py-12">
        @yield('content')
    </main>
</body>

</html>
