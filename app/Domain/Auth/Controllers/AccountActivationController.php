<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Auth\Controllers;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AccountActivationController extends Controller
{
    /**
     * Mostrar formulario de activación
     */
    public function showActivationForm(string $token)
    {
        // Buscar usuario por token
        $user = User::where('activation_token', hash('sha256', $token))
            ->whereNotNull('activation_token')
            ->where('activation_token_expires_at', '>', now())
            ->first();

        if (!$user) {
            return redirect()
                ->route('portal.login.show')
                ->withErrors([
                    'username' => 'El enlace de activación ha expirado o ya fue utilizado. Contacta al administrador.',
                ]);
        }
        
        // Verificar que el usuario esté en estado INACTIVE
        if ($user->status !== 'inactive') {
            $statusLabel = match($user->status) {
                'active' => 'activo',
                'suspended' => 'suspendido',
                default => $user->status,
            };
            
            return redirect()
                ->route('portal.login.show')
                ->withErrors([
                    'username' => "El enlace de activación no es válido. El usuario está en estado '{$statusLabel}'. Contacta al administrador.",
                ]);
        }

        return view('auth.account-activation', [
            'token' => $token,
            'user' => $user,
            'context' => 'retailer', // Para el layout
        ]);
    }

    /**
     * Procesar activación de cuenta
     */
    public function activate(Request $request)
    {
        $passwordRules = \App\Domain\Auth\Services\PasswordStrengthCalculator::getLaravelRules();
        $passwordRules[] = 'confirmed';
        
        $request->validate([
            'token' => 'required|string',
            'password' => $passwordRules,
        ], array_merge(
            \App\Domain\Auth\Services\PasswordStrengthCalculator::getValidationMessages(),
            ['password.confirmed' => 'Las contraseñas no coinciden']
        ));

        // Buscar usuario por token
        $user = User::where('activation_token', hash('sha256', $request->token))
            ->whereNotNull('activation_token')
            ->where('activation_token_expires_at', '>', now())
            ->first();

        if (!$user) {
            return redirect()
                ->route('portal.login.show')
                ->withErrors([
                    'username' => 'El enlace de activación ha expirado o ya fue utilizado. Contacta al administrador.',
                ]);
        }
        
        // Verificar que el usuario esté en estado INACTIVE
        if ($user->status !== 'inactive') {
            $statusLabel = match($user->status) {
                'active' => 'activo',
                'suspended' => 'suspendido',
                default => $user->status,
            };
            
            return redirect()
                ->route('portal.login.show')
                ->withErrors([
                    'username' => "El enlace de activación no es válido. El usuario está en estado '{$statusLabel}'. Contacta al administrador.",
                ]);
        }

        // Actualizar contraseña y activar cuenta
        $user->update([
            'password_hash' => Hash::make($request->password),
            'status' => 'active', // Cambiar de inactive a active
            'email_verified_at' => now(),
            'password_changed_at' => now(),
            'activation_token' => null,
            'activation_token_expires_at' => null,
            'activation_token_plain' => null, // Limpiar token en texto plano
        ]);

        Log::info('Account activated', ['user_id' => $user->id, 'email' => $user->email]);

        // Auto-login del usuario
        $guard = $user->hasRole('retailer', 'retailer') ? 'retailer' : 'admin';
        Auth::guard($guard)->login($user);
        $request->session()->regenerate();

        // Redirect según el guard
        $redirectUrl = $guard === 'retailer' ? '/portal/dashboard' : '/admin/dashboard';

        return redirect($redirectUrl)
            ->with('success', '¡Cuenta activada exitosamente! Bienvenido a ' . config('app.name'));
    }
}
