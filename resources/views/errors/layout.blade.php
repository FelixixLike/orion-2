<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | Tender Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-background-primary">
    <main
        class="min-h-screen font-montserrat flex flex-col gap-y-10 items-center justify-center p-4 relative overflow-hidden"
        role="main">
        <article class="flex flex-col gap-y-2 items-center justify-center text-center">
            <h1 class="text-6xl font-bold text-white" aria-label="Código de error @yield('code')">@yield('code')
            </h1>
            @yield('message')
        </article>
    </main>
</body>

</html>
