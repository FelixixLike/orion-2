@php
    // Normaliza el contexto: solo admin o retailer
    $context = $context === 'admin' ? 'admin' : 'retailer';
@endphp

@extends('layouts.auth')

@section('content')
    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6">
        <div class="grid items-center lg:items-center gap-10 lg:gap-16 xl:gap-20 lg:grid-cols-2">
            <section class="order-2 lg:order-1 space-y-8 xl:space-y-10 text-center lg:text-left w-full max-w-2xl lg:max-w-none mx-auto lg:mx-0">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white/90">
                    <span class="h-2 w-2 rounded-full bg-emerald-300/90"></span>
                    Acceso seguro {{ $context === 'admin' ? 'administrador' : 'tendero' }}
                </div>

                <div class="space-y-5 xl:space-y-6">
                    <div class="w-28 sm:w-32 lg:w-36 mx-auto lg:mx-0" style="max-width: 100%;" role="img" aria-label="Logo Tender Portal">
                        @if ($context === 'admin')
                            @include('components.svg.admin')
                        @else
                            @include('components.svg.cart')
                        @endif
                    </div>

                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl lg:text-5xl font-montserrat-bold leading-tight">
                            {{ $context === 'admin' ? 'Panel de administracion' : 'Portal de tenderos' }}
                        </h1>
                        <p class="text-white/80 max-w-xl mx-auto lg:mx-0 text-sm md:text-base lg:text-lg">
                            Controla tus operaciones con una interfaz rapida y sencilla. Inicia sesion para continuar.
                        </p>
                    </div>
                </div>

                <ul class="grid grid-cols-1 gap-3 text-white/85 text-sm md:text-base sm:grid-cols-2">
                    <li class="flex items-start gap-3 rounded-lg bg-white/5 border border-white/10 px-4 py-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-primary-300"></span>
                        <span>Autenticacion protegida y sesion segura.</span>
                    </li>
                    <li class="flex items-start gap-3 rounded-lg bg-white/5 border border-white/10 px-4 py-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-primary-300"></span>
                        <span>Experiencia rapida gracias a la navegacion SPA.</span>
                    </li>
                    <li class="flex items-start gap-3 rounded-lg bg-white/5 border border-white/10 px-4 py-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-primary-300"></span>
                        <span>Diseno accesible y optimizado para movil.</span>
                    </li>
                    <li class="flex items-start gap-3 rounded-lg bg-white/5 border border-white/10 px-4 py-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-primary-300"></span>
                        <span>Soporte inmediato con tu equipo de operaciones.</span>
                    </li>
                </ul>
            </section>

            <section class="relative order-1 lg:order-2 w-full max-w-xl lg:max-w-lg xl:max-w-xl mx-auto lg:mx-0">
                <div class="absolute -inset-4 rounded-3xl bg-white/5 blur-3xl"></div>
                <div class="relative rounded-2xl bg-white/10 border border-white/15 shadow-2xl backdrop-blur-xl p-6 sm:p-8 md:p-10 lg:p-12 xl:p-14">
                    <div class="mb-6 space-y-2 text-center">
                        <p class="text-xs uppercase tracking-[0.25em] text-white/60">{{ $context === 'admin' ? 'Administrador' : 'Portal tenderos' }}</p>
                        <h2 class="text-2xl md:text-3xl font-montserrat-semibold">Inicia sesion</h2>
                        <p class="text-sm md:text-base text-white/70">Usa tu usuario y contrasena asignados.</p>
                    </div>

                    <form method="POST" action="{{ $context === 'admin' ? route('admin.login.post') : route('portal.login.post') }}" class="space-y-6" aria-label="Formulario de inicio de sesion" data-turbo="false">
                        @csrf

                        <fieldset class="space-y-4" aria-label="Credenciales de acceso">
                            <legend class="sr-only">Ingresa tus credenciales</legend>

                            <x-input-with-icon
                                name="username"
                                type="text"
                                placeholder="USUARIO"
                                value="{{ old('username') }}"
                                required
                                icon="user"
                                label="Usuario"
                            />

                            <x-input-with-icon
                                name="password"
                                type="password"
                                placeholder="CONTRASENA"
                                required
                                icon="lock"
                                label="Contrasena"
                            />
                        </fieldset>

                        <div class="flex flex-col gap-3 text-sm md:text-base text-white/70">
                            @if ($context === 'retailer')
                                <a href="#" class="self-end font-montserrat-semibold text-white hover:text-primary-100 transition-colors">Olvidaste tu contrasena?</a>
                            @endif
                        </div>

                        <button type="submit"
                            class="w-full text-base md:text-lg font-montserrat-semibold text-gray-950 bg-gradient-to-r from-primary-200 to-secondary-300 rounded-xl py-3.5 md:py-4 font-medium hover:from-primary-300 hover:to-secondary-200 transition-all shadow-lg shadow-primary-900/30">
                            Ingresar
                        </button>
                    </form>

                    <div class="mt-6 text-center text-xs md:text-sm text-white/70">
                        <a href="{{ $context === 'admin' ? route('portal.login.show') : route('admin.login.show') }}" class="font-montserrat-semibold hover:text-primary-100 transition-colors">
                            {{ $context === 'admin' ? 'Eres tendero? Ve al portal' : 'Eres administrador? Ve al panel de administracion' }}
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
