<?php

namespace App\Domain\Admin\Filament\Resources\OperatorReportResource\Pages;

use App\Domain\Admin\Filament\Resources\OperatorReportResource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\EditRecord;

class EditOperatorReport extends EditRecord
{
    protected static string $resource = OperatorReportResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('coid')
                    ->label('COID')
                    ->required(),

                Select::make('commission_status')
                    ->label('Estado Comisión')
                    ->options([
                        'PAGADA' => 'PAGADA',
                        'PENDIENTE' => 'PENDIENTE',
                    ])
                    ->required(),

                DatePicker::make('activation_date')
                    ->label('Fecha Activación')
                    ->required(),

                DatePicker::make('cutoff_date')
                    ->label('Fecha Corte')
                    ->required(),

                TextInput::make('commission_paid_80')
                    ->label('Comisión 80%')
                    ->numeric()
                    ->prefix('$'),

                TextInput::make('commission_paid_20')
                    ->label('Comisión 20%')
                    ->numeric()
                    ->prefix('$'),

                TextInput::make('recharge_amount')
                    ->label('Monto Recarga')
                    ->numeric()
                    ->prefix('$'),

                TextInput::make('recharge_period')
                    ->label('Período')
                    ->numeric(),

                TextInput::make('total_recharge_per_period')
                    ->label('Total Recarga por Período')
                    ->numeric()
                    ->prefix('$'),

                TextInput::make('custcode')
                    ->label('Código Cliente'),

                TextInput::make('city_code')
                    ->label('Código Ciudad'),
            ]);
    }

    public function getBreadcrumbs(): array
    {
        $record = $this->getRecord();
        $simcard = $record->simcard;
        $period = $record->recharge_period;

        return [
            \App\Domain\Admin\Filament\Resources\SimcardResource::getUrl('index') => 'Cruces',
            \App\Domain\Admin\Filament\Resources\SimcardResource::getUrl('view', ['record' => $simcard]) => 'Ver',
            \App\Domain\Admin\Filament\Resources\SimcardResource::getUrl('view-period-reports', ['record' => $simcard, 'period' => $period]) => "Periodo {$period}",
            '#' => 'Editar',
        ];
    }
}
