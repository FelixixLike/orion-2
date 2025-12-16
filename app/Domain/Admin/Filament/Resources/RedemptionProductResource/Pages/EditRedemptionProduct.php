<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\RedemptionProductResource\Pages;

use App\Domain\Admin\Filament\Resources\RedemptionProductResource;
use Filament\Resources\Pages\EditRecord;

class EditRedemptionProduct extends EditRecord
{
    protected static string $resource = RedemptionProductResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['type'] ?? '') === 'recharge' && !isset($data['unit_value'])) {
            $data['unit_value'] = 0;
        }

        return $data;
    }
}
