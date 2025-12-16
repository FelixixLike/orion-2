<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\User\Services\UserEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RetailerAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', ['context' => 'retailer']);
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'El usuario es obligatorio',
            'password.required' => 'La contraseña es obligatoria',
        ]);

        // Buscar usuario por username
        $user = \App\Domain\User\Models\User::where('username', $request->username)->first();

        // Validaciones de seguridad UNIFICADAS (previene enumeración de usuarios)
        if (
            !$user
            || !Hash::check($request->password, $user->password_hash)
            || !$user->hasRole('retailer', 'retailer')
            || $user->status !== 'active'
        ) {
            // Loggear intento fallido con razón específica (solo para administradores/logs)
            Log::warning('Failed retailer login attempt', [
                'username' => $request->username,
                'ip' => $request->ip(),
                'reason' => !$user ? 'user_not_found' :
                    (!Hash::check($request->password, $user->password_hash) ? 'wrong_password' :
                        (!$user->hasRole('retailer', 'retailer') ? 'wrong_role' : 'inactive_account'))
            ]);

            // Mensaje GENÉRICO para el usuario
            return back()
                ->withInput($request->only('username'))
                ->withErrors([
                    'username' => 'Credenciales incorrectas o cuenta inactiva',
                ]);
        }

        // Login exitoso
        Auth::guard('retailer')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Actualizar último login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        Log::info('Retailer logged in', ['user_id' => $user->id, 'username' => $user->username]);

        // Notificar por correo el nuevo inicio de sesion (no bloqueante)
        app(UserEmailService::class)->sendLoginNotification(
            $user,
            $request->ip(),
            'Portal de tenderos'
        );

        return redirect()->intended(route('tendero.dashboard'));
    }

    /**
     * Logout del portal de tiendas (guard: retailer)
     */
    public function logout(Request $request)
    {
        Log::info('Logging out retailer', ['user_id' => Auth::guard('retailer')->id()]);

        Auth::guard('retailer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login.show');
    }
}
