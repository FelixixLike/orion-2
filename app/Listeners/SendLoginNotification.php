<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewLoginAlert;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendLoginNotification
{
    public function handle(Login $event): void
    {
        // $event->user contiene el usuario autenticado
        // $event->guard contiene el guard usado (admin, web, etc)

        $user = $event->user;

        // Validar que el usuario tenga email
        if ($user && $user->email) {
            try {
                Mail::to($user->email)->send(new NewLoginAlert($user));
            } catch (\Exception $e) {
                // Manejar error silenciosamente para no bloquear el login
                // Log::error('Error enviando correo de login: ' . $e->getMessage());
            }
        }
    }
}
