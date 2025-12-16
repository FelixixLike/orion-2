<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\RetailerResource\Pages;

use App\Domain\Admin\Filament\Resources\RetailerResource;
use App\Domain\Admin\Filament\Resources\UserResource\Pages\EditUser;

class EditRetailer extends EditUser
{
    protected static string $resource = RetailerResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}

