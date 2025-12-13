@php
    $isAdmin = auth('admin')->check();
    $guard = $isAdmin ? 'admin' : 'retailer';
    $user = auth($guard)->user();
    $logoutRoute = $isAdmin ? route('admin.logout') : route('portal.logout');
@endphp

@extends('layouts.auth')

@section('content')
    <div class="w-full">
        <div class="relative max-w-3xl mx-auto">
            <div class="absolute -inset-5 rounded-3xl bg-white/5 blur-3xl"></div>
            <div
                class="relative rounded-2xl bg-white/10 border border-white/15 shadow-2xl backdrop-blur-xl p-8 md:p-10 space-y-8">
                <div class="flex items-start justify-between gap-4">
                    <div class="max-w-28" role="img" aria-label="Logo">

                        @if($isAdmin)

                            @include('components.svg.admin')
                        @else
                            @include('components.svg.cart')
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-[0.25em] text-white/60">Seguridad</p>
                        <p class="text-sm text-white/70">Cambio obligatorio</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="text-3xl font-montserrat-bold leading-tight">Cambio de contrasena requerido</h1>
                    <p class="text-white/80 text-sm md:text-base">Por seguridad, debes actualizar tu contrasena antes de
                        continuar.</p>
                </div>

                <div class="bg-white/5 border border-white/10 rounded-lg p-4 text-sm text-white/85">
                    <p class="flex flex-wrap items-center gap-2">
                        <span class="text-white/60">Usuario:</span>

                        <span class="font-montserrat-semibold">{{ $user?->username }}</span>

                    </p>
                </div>

                <form method="POST" action="{{ route('password.force-change.post') }}" class="space-y-5">
                    @csrf

                    <div class="grid gap-4 md:grid-cols-3">
                        <x-input-with-icon name="current_password" type="password" placeholder="CONTRASENA ACTUAL" required
                            icon="lock" label="Contrasena Actual" />

                        <x-input-with-icon name="password" type="password" placeholder="NUEVA CONTRASENA" required
                            icon="lock" label="Nueva Contrasena" />

                        <x-input-with-icon name="password_confirmation" type="password" placeholder="CONFIRMAR CONTRASENA"
                            required icon="lock" label="Confirmar Contrasena" :showError="false" />
                    </div>

                    <div
                        class="bg-blue-500/15 border border-blue-200/25 rounded-lg p-4 text-sm text-white/90 leading-relaxed">
                        <strong class="block mb-2 text-sm font-montserrat-semibold">Recuerda:</strong>
                        <ul class="space-y-1.5">
                            <li>Minimo 8 caracteres.</li>
                            <li>Diferente de la contrasena temporal.</li>
                            <li>No compartas tu nueva contrasena.</li>
                        </ul>
                    </div>

                    <button type="submit"
                        class="w-full text-base font-montserrat-semibold text-gray-950 bg-gradient-to-r from-primary-200 to-secondary-300 rounded-xl py-3 font-medium hover:from-primary-300 hover:to-secondary-200 transition-all shadow-lg shadow-primary-900/30">
                        Cambiar contrasena
                    </button>
                </form>

                <footer class="text-center text-xs text-white/70">

                    <form method="POST" action="{{ $logoutRoute }}">

                        @csrf
                        <button type="submit"
                            class="text-white/70 hover:text-primary-100 transition-colors bg-transparent border-none cursor-pointer">
                            Cerrar sesion
                        </button>
                    </form>
                </footer>
            </div>
        </div>
    </div>
@endsection