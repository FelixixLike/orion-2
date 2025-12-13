@extends('layouts.auth')

@section('content')
    <div class="w-full">
        <div class="relative max-w-3xl mx-auto">
            <div class="absolute -inset-6 rounded-3xl bg-white/5 blur-3xl"></div>
            <div class="relative rounded-2xl bg-white/10 border border-white/15 shadow-2xl backdrop-blur-xl p-8 md:p-10 space-y-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div class="max-w-28" role="img" aria-label="Logo Portal">
                        @include('components.svg.cart')
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-[0.25em] text-white/60">Activacion de cuenta</p>
                        <p class="text-sm text-white/70">Portal de tenderos</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="text-3xl font-montserrat-bold leading-tight">Bienvenido, {{ $user->first_name }}!</h1>
                    <p class="text-white/80 text-sm md:text-base">Crea tu contrasena para activar tu cuenta y empezar a usar el portal.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 text-sm text-white/85">
                    <div class="rounded-lg bg-white/5 border border-white/10 px-4 py-3">
                        <p class="font-montserrat-semibold mb-1">Paso 1</p>
                        <p>Define una contrasena segura.</p>
                    </div>
                    <div class="rounded-lg bg-white/5 border border-white/10 px-4 py-3">
                        <p class="font-montserrat-semibold mb-1">Paso 2</p>
                        <p>Confirma la contrasena.</p>
                    </div>
                    <div class="rounded-lg bg-white/5 border border-white/10 px-4 py-3">
                        <p class="font-montserrat-semibold mb-1">Paso 3</p>
                        <p>Activa y accede al portal.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('portal.activate.post') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="space-y-4">
                        <div>
                            <x-input-with-icon name="password" type="password" placeholder="NUEVA CONTRASENA" required icon="lock"
                                label="Nueva Contrasena" />
                            <x-password-validation-message />
                        </div>

                        <div>
                            <x-input-with-icon name="password_confirmation" type="password" placeholder="CONFIRMAR CONTRASENA" required
                                icon="lock" label="Confirmar Contrasena" :showError="false" />
                            <x-password-match-indicator />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="bg-blue-500/15 border border-blue-200/25 rounded-lg p-4 text-sm leading-relaxed text-white/90">
                            <strong class="flex items-center gap-2 mb-2 text-blue-100">
                                @include('components.svg.lightbulb')
                                Requisitos de contrasena
                            </strong>
                            <ul class="space-y-1.5">
                                <li>Minimo 8 caracteres.</li>
                                <li>Incluye mayusculas, minusculas, numero y simbolo.</li>
                                <li>Evita usar datos obvios.</li>
                            </ul>
                        </div>

                        @if ($errors->has('token'))
                            <div class="bg-red-500/15 border border-red-300/40 rounded-lg p-4 text-sm text-white/90">
                                <p class="flex items-center gap-2 font-semibold text-red-100">
                                    @include('components.svg.x-circle')
                                    {{ $errors->first('token') }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <button type="submit"
                        class="w-full text-base font-montserrat-semibold text-gray-950 bg-gradient-to-r from-primary-200 to-secondary-300 rounded-xl py-3 font-medium hover:from-primary-300 hover:to-secondary-200 transition-all shadow-lg shadow-primary-900/30">
                        Activar mi cuenta
                    </button>
                </form>

                <footer class="text-center text-xs text-white/70">
                    Al activar tu cuenta, aceptas nuestros terminos y condiciones.
                </footer>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.PasswordValidator) {
                new window.PasswordValidator('password', 'password_confirmation');
            }
        });
    </script>
@endsection
