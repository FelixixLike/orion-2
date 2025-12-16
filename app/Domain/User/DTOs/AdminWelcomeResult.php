<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\User\DTOs;

class AdminWelcomeResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $email = null,
        public readonly ?string $password = null,
        public readonly ?string $loginUrl = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function hasError(): bool
    {
        return !$this->success;
    }

    public static function success(string $email, string $password, string $loginUrl): self
    {
        return new self(
            success: true,
            email: $email,
            password: $password,
            loginUrl: $loginUrl,
        );
    }

    public static function emailFailed(string $email, string $password, string $loginUrl, string $errorMessage): self
    {
        return new self(
            success: false,
            email: $email,
            password: $password,
            loginUrl: $loginUrl,
            errorMessage: $errorMessage,
        );
    }

    public static function noPasswordChangeRequired(): self
    {
        return new self(success: false);
    }
}
