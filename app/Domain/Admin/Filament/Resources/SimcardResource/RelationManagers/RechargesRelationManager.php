<?php

namespace App\Domain\Admin\Filament\Resources\SimcardResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;

class RechargesRelationManager extends RelationManager
{
    protected static string $relationship = 'recharges';

    protected static ?string $title = 'Recargas';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('phone_number')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('recharge_amount')
                    ->label('Monto Recarga')
                    ->money('COP')
                    ->sortable(),

                TextColumn::make('period_date')
                    ->label('Fecha Período')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->paginated(false)
            ->searchable(false);
    }
}
