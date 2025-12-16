<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\DTOs\AdminWelcomeResult;
use App\Domain\User\DTOs\UserActivationResult;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Models\User;
use App\Mail\AccountActivationMail;
use App\Mail\LoginNotificationMail;
use App\Mail\WelcomeAdminMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserEmailService
{
    /**
     * Envía el email de activación para retailers.
     */
    public function sendRetailerActivationEmail(User $user): UserActivationResult
    {
        if (!$this->isUserInactive($user)) {
            return UserActivationResult::userNotInactive('user_not_inactive');
        }

        $token = $this->generateActivationToken();
        $activationUrl = $this->buildActivationUrl($token);

        $this->saveActivationToken($user, $token);

        return $this->sendActivationEmail($user, $token, $activationUrl);
    }

    /**
     * Envía el email de bienvenida para administradores.
     */
    public function sendAdminWelcomeEmail(User $user, ?string $plainPassword = null): AdminWelcomeResult
    {
        if (!$user->must_change_password) {
            return AdminWelcomeResult::noPasswordChangeRequired();
        }

        if (!$plainPassword) {
            return AdminWelcomeResult::noPasswordChangeRequired();
        }

        $loginUrl = $this->buildLoginUrl($user);

        return $this->sendWelcomeEmail($user, $plainPassword, $loginUrl);
    }

    /**
     * Envía un correo sencillo cada vez que el usuario inicia sesión.
     * No interrumpe el flujo de login si el envío falla.
     */
    public function sendLoginNotification(User $user, string $ipAddress, string $channelLabel): void
    {
        if (empty($user->email)) {
            return;
        }

        try {
            Mail::to($user->email)->send(
                new LoginNotificationMail($user, $ipAddress, $channelLabel, now())
            );
        } catch (\Throwable $e) {
            Log::warning('Login notification email failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isUserInactive(User $user): bool
    {
        return $user->status === UserStatus::INACTIVE->value;
    }

    private function generateActivationToken(): string
    {
        return Str::random(64);
    }

    private function buildActivationUrl(string $token): string
    {
        return url("/portal/activate/{$token}");
    }

    private function saveActivationToken(User $user, string $token): void
    {
        $user->update([
            'activation_token' => hash('sha256', $token),
            'activation_token_expires_at' => now()->addHours(48),
            'activation_token_plain' => $token,
        ]);
    }

    private function sendActivationEmail(User $user, string $token, string $activationUrl): UserActivationResult
    {
        try {
            Mail::to($user->email)->send(new AccountActivationMail($user, $token));

            return UserActivationResult::success($token, $activationUrl, $user->email);
        } catch (\Exception $e) {
            $this->markEmailAsVerified($user);

            return UserActivationResult::emailFailed(
                $token,
                $activationUrl,
                $user->email,
                $e->getMessage()
            );
        }
    }

    private function buildLoginUrl(User $user): string
    {
        $guard = $user->hasRole('retailer', 'retailer') 
            ? \App\Domain\Auth\Enums\GuardName::RETAILER 
            : \App\Domain\Auth\Enums\GuardName::ADMIN;

        return $guard->loginUrl();
    }

    private function sendWelcomeEmail(User $user, string $password, string $loginUrl): AdminWelcomeResult
    {
        try {
            Mail::to($user->email)->send(new WelcomeAdminMail($user, true));

            return AdminWelcomeResult::success($user->email, $password, $loginUrl);
        } catch (\Exception $e) {
            $this->markEmailAsVerified($user);

            return AdminWelcomeResult::emailFailed(
                $user->email,
                $password,
                $loginUrl,
                $e->getMessage()
            );
        }
    }

    private function markEmailAsVerified(User $user): void
    {
        $user->update(['email_verified_at' => now()]);
    }
}
