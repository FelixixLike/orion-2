<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

class AdminPanelAuthenticate extends FilamentAuthenticate
{
    /**
     * Redirect unauthenticated admin panel requests to the custom login page.
     */
    protected function redirectTo($request): ?string
    {
        return route('admin.login.show');
    }
}
