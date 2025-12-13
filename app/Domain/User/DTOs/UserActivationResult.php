<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

class UserActivationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $token = null,
        public readonly ?string $activationUrl = null,
        public readonly ?string $email = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $reason = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function hasError(): bool
    {
        return !$this->success;
    }

    public static function success(string $token, string $activationUrl, string $email): self
    {
        return new self(
            success: true,
            token: $token,
            activationUrl: $activationUrl,
            email: $email,
        );
    }

    public static function emailFailed(string $token, string $activationUrl, string $email, string $errorMessage): self
    {
        return new self(
            success: false,
            token: $token,
            activationUrl: $activationUrl,
            email: $email,
            errorMessage: $errorMessage,
            reason: 'email_send_failed',
        );
    }

    public static function userNotInactive(string $reason): self
    {
        return new self(
            success: false,
            reason: $reason,
        );
    }
}
