<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue; // Opcional si quieres colas
use App\Domain\User\Models\User;

class NewLoginAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $time;

    public function __construct($user)
    {
        $this->user = $user;
        $this->time = now()->format('Y-m-d H:i:s');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Alerta de Seguridad: Nuevo Inicio de Sesión',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.security.new-login',
        );
    }
}
