<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\RedemptionProductResource\Pages;

use App\Domain\Admin\Filament\Resources\RedemptionProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRedemptionProduct extends CreateRecord
{
    protected static string $resource = RedemptionProductResource::class;
    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['type'] ?? '') === 'recharge' && !isset($data['unit_value'])) {
            $data['unit_value'] = 0;
        }

        return $data;
    }
}
