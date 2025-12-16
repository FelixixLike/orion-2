<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Auth\Controllers;

use App\Domain\User\Models\User;
use App\Domain\User\Services\UserEmailService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', ['context' => 'admin']);
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

        Log::info('Admin login attempt', ['username' => $request->username]);

        // Buscar usuario por username
        $user = User::where('username', $request->username)->first();
        $hash = $user?->password_hash ?: $user?->password;

        // Validaciones de seguridad (sin revelar información específica)
        if (!$user 
            || empty($hash)
            || !Hash::check($request->password, $hash)
            || !$user->hasRole(['super_admin','administrator','tat_direction'], 'admin')
            || $user->status !== 'active') {
            
            // Loggear intento fallido (para auditoría interna)
            Log::warning('Failed admin login attempt', [
                'username' => $request->username,
                'ip' => $request->ip(),
                'reason' => !$user ? 'user_not_found' : 
                           (empty($hash) || !Hash::check($request->password, $hash) ? 'wrong_password' :
                           (!$user->hasRole(['super_admin','administrator','tat_direction'], 'admin') ? 'wrong_role' : 'inactive_account'))
            ]);
            
            // SIEMPRE el mismo mensaje genérico (previene enumeración de usuarios)
            return back()
                ->withInput($request->only('username'))
                ->withErrors([
                    'username' => 'Credenciales incorrectas',
                ]);
        }

        // Login exitoso
        Auth::guard('admin')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Actualizar último login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        Log::info('Admin logged in', ['user_id' => $user->id, 'username' => $user->username]);

        // Notificar por correo el nuevo inicio de sesion (no bloqueante)
        app(UserEmailService::class)->sendLoginNotification(
            $user,
            $request->ip(),
            'Panel de administracion'
        );

        return redirect()->intended('/admin/dashboard');
    }

    /**
     * Logout del panel de administración (guard: admin)
     */
    public function logout(Request $request)
    {
        Log::info('Logging out admin', ['user_id' => Auth::guard('admin')->id()]);

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login.show');
    }
}
