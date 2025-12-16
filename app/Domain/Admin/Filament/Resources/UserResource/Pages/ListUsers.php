<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\UserResource\Pages;

use App\Domain\Admin\Filament\Resources\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $scope = request()->get('scope');
        $label = $scope === 'retailers' ? 'Crear tendero' : 'Crear Usuario';

        return [
            CreateAction::make()
                ->label($label)
                ->url(function () use ($scope): string {
                    if ($scope) {
                        return static::getResource()::getUrl('create', ['scope' => $scope]);
                    }

                    return static::getResource()::getUrl('create');
                }),
        ];
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        $scope = request()->get('scope');

        if ($scope === 'retailers') {
            // Listado de tenderos: usuarios con rol guard retailer
            return $query->whereHas('roles', function (Builder $query) {
                $query->where('guard_name', 'retailer');
            });
        }

        // Listado de usuarios administrativos: roles guard admin
        return $query->whereHas('roles', function (Builder $query) {
            $query->where('guard_name', 'admin');
        });
    }
}
