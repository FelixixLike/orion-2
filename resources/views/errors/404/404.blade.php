@extends('errors.layout')

@section('title', 'Página no encontrada')
@section('code', '404')
@section('message')
    <p class="text-2xl font-semibold mb-4 text-white">No encontramos esa página</p>
    <p class="mb-6 text-white/80">La URL que abriste no existe o ha cambiado.</p>

    <nav aria-label="Opciones de navegación">
        @auth('web')
            <a href="/admin/dashboard"
                class="inline-block px-6 py-2 bg-white text-primary-500 rounded-sm hover:bg-white/90 transition-colors">
                Ir al panel de administración
            </a>
        @endauth

        @auth('retailer')
            <a href="/portal/dashboard"
                class="inline-block px-6 py-2 bg-white text-primary-500 rounded-sm hover:bg-white/90 transition-colors">
                Ir al portal
            </a>
        @endauth

        @guest('web')
            @guest('retailer')
                <a href="{{ route('portal.login.show') }}"
                    class="inline-block px-6 py-2 bg-white text-primary-500 rounded-sm hover:bg-white/90 transition-colors">
                    Iniciar sesión
                </a>
            @endguest
        @endguest
    </nav>
@endsection
