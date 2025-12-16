<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\RetailerResource\Pages;

use App\Domain\Admin\Filament\Resources\RetailerResource;
use App\Domain\Admin\Filament\Resources\UserResource\Pages\CreateUser;
use App\Domain\User\Enums\UserRole;
use App\Domain\Admin\Filament\Pages\RetailersPage;

class CreateRetailer extends CreateUser
{
    protected static string $resource = RetailerResource::class;

    protected string $view = 'filament.admin.retailer.retailer-create';

    public function getModuleUrl(): string
    {
        return RetailersPage::getUrl(panel: 'admin');
    }

    public function getRetailerListUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Forzamos el rol Tendero y reutilizamos la logica de CreateUser.
        $data['role'] = UserRole::RETAILER->value;

        return parent::mutateFormDataBeforeCreate($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
