<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        // 📝 Si la petición espera JSON, no redirigimos (APIs).
        if ($request->expectsJson()) {
            return null;
        }

        // 🧭 Si la ruta es del admin (/admin o /admin/*) → login de admin
        if (Str::startsWith($request->path(), 'admin')) {
            return route('admin.login.show');
        }

        // 🧭 Si la ruta es del portal (/portal o /portal/*) → login de portal
        if (Str::startsWith($request->path(), 'portal')) {
            return route('portal.login.show');
        }

        // 🎯 Fallback: si no coincide nada, manda al portal
        return route('portal.login.show');
    }
}
