<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PasswordChangeController extends Controller
{
    /**
     * Mostrar formulario de cambio forzado de contraseña
     */
    public function showForceChangeForm()
    {
        $user = auth()->user();
        
        if (!$user || !$user->must_change_password) {
            // Si no necesita cambiar contraseña, redirigir al dashboard
            $guard = auth('admin')->check() ? 'admin' : 'retailer';
            $redirectUrl = $guard === 'admin' ? '/admin/dashboard' : '/portal/dashboard';
            return redirect($redirectUrl);
        }

        $context = auth('admin')->check() ? 'admin' : 'retailer';
        
        return view('auth.force-password-change', [
            'context' => $context,
        ]);
    }

    /**
     * Procesar cambio forzado de contraseña
     */
    public function forceChange(Request $request)
    {
        $passwordRules = \App\Domain\Auth\Services\PasswordStrengthCalculator::getLaravelRules();
        $passwordRules[] = 'confirmed';
        $passwordRules[] = 'different:current_password';
        
        $request->validate([
            'current_password' => 'required',
            'password' => $passwordRules,
        ], array_merge(
            \App\Domain\Auth\Services\PasswordStrengthCalculator::getValidationMessages(),
            [
                'current_password.required' => 'La contraseña actual es obligatoria',
                'password.confirmed' => 'Las contraseñas no coinciden',
                'password.different' => 'La nueva contraseña debe ser diferente de la actual',
            ]
        ));

        $user = auth()->user();

        // Verificar contraseña actual
        if (!Hash::check($request->current_password, $user->password_hash)) {
            return back()->withErrors([
                'current_password' => 'La contraseña actual es incorrecta',
            ]);
        }

        // Actualizar contraseña
        $user->update([
            'password_hash' => Hash::make($request->password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        // Redirect según el guard
        $guard = auth('admin')->check() ? 'admin' : 'retailer';
        $redirectUrl = $guard === 'admin' ? '/admin/dashboard' : '/portal/dashboard';

        return redirect($redirectUrl)
            ->with('success', 'Contraseña actualizada exitosamente');
    }
}
