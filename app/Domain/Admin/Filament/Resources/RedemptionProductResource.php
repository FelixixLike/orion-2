<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources;

use App\Domain\Admin\Filament\Resources\RedemptionProductResource\Pages;
use App\Domain\Store\Models\RedemptionProduct;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\Action as TableAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Filament\Forms\Components\Radio;

class RedemptionProductResource extends Resource
{
    protected static ?string $model = RedemptionProduct::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-gift';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Redenciones';
    }

    public static function getNavigationLabel(): string
    {
        return 'Productos redimibles';
    }

    public static function getNavigationSort(): ?int
    {
        return 120;
    }

    public static function getModelLabel(): string
    {
        return 'Producto redimible';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Productos redimibles';
    }

    // Visibilidad en menú basada en permiso (segura si no existe el permiso)
    public static function canViewAny(): bool
    {
        $user = auth('admin')->user();

        if (!$user) {
            return false;
        }

        $permissionModelClass = config('permission.models.permission') ?? \Spatie\Permission\Models\Permission::class;

        $permissionExists = $permissionModelClass::query()
            ->where('name', 'redemption_products.view')
            ->where('guard_name', 'admin')
            ->exists();

        if (!$permissionExists) {
            return false;
        }

        return $user->hasPermissionTo('redemption_products.view', 'admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    // Crear/editar/borrar controlado por permisos granulares o acceso al módulo
    public static function canCreate(): bool
    {
        $user = auth('admin')->user();
        if (!$user)
            return false;

        return $user->hasPermissionTo('redemption_products.create', 'admin')
            || $user->hasPermissionTo('redemptions.view', 'admin')
            || $user->hasPermissionTo('redemption_products.view', 'admin');
    }

    public static function canEdit($record): bool
    {
        $user = auth('admin')->user();
        if (!$user)
            return false;

        return $user->hasPermissionTo('redemption_products.edit', 'admin')
            || $user->hasPermissionTo('redemptions.view', 'admin')
            || $user->hasPermissionTo('redemption_products.view', 'admin');
    }

    public static function canDelete($record): bool
    {
        $user = auth('admin')->user();
        if (!$user)
            return false;

        return $user->hasPermissionTo('redemption_products.delete', 'admin')
            || $user->hasPermissionTo('redemptions.view', 'admin')
            || $user->hasPermissionTo('redemption_products.view', 'admin');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Detalles del Producto')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Select::make('type')
                        ->label('Tipo')
                        ->options([
                            'sim' => 'SIM',
                            'recharge' => 'Recarga',
                            'device' => 'Dispositivo',
                            'accessory' => 'Accesorio',
                        ])
                        ->required()
                        ->live() // Reactivo para mostrar/ocultar campos
                        ->afterStateUpdated(function ($state, callable $set) {
                            // Resetear valores irrelevantes al cambiar tipo? Opcional.
                            if ($state === 'recharge') {
                                $set('stock', 0);
                            }
                        }),



                    TextInput::make('unit_value')
                        ->label('Valor unitario')
                        ->numeric()
                        ->prefix('$')
                        ->visible(fn($get) => $get('type') !== 'recharge')
                        ->required(fn($get) => $get('type') !== 'recharge'),

                    // Lógica Condicional de Stock / Límites
                    TextInput::make('stock')
                        ->label('Stock Disponible')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->visible(fn($get) => !in_array($get('type'), ['recharge', 'sim']))
                        ->required(fn($get) => !in_array($get('type'), ['recharge', 'sim'])),

                    TextInput::make('monthly_store_limit')
                        ->label('Límite Mensual por Tienda')
                        ->helperText('Cantidad máxima que una tienda puede pedir al mes.')
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn($get) => $get('type') === 'sim')
                        ->required(fn($get) => $get('type') === 'sim'),

                    TextInput::make('max_value')
                        ->label('Valor Máximo de Recarga')
                        ->helperText('Monto máximo permitido por recarga (ej: 100000).')
                        ->numeric()
                        ->prefix('$')
                        ->visible(fn($get) => $get('type') === 'recharge')
                        ->required(fn($get) => $get('type') === 'recharge'),

                    Textarea::make('description')
                        ->label('Descripción')
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),

                    Toggle::make('is_active')
                        ->label('Activo')
                        ->default(true),
                ])
                ->columns(2),

            Section::make('Imagen')
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('view_image_display')
                        ->label('Visualización')
                        ->hiddenLabel()
                        ->content(function ($record) {
                            if (!$record) {
                                return null;
                            }

                            $src = null;
                            if ($record->image_path) {
                                $src = \Illuminate\Support\Facades\Storage::url($record->image_path);
                            } elseif ($record->image_url) {
                                $src = asset($record->image_url);
                            }

                            if ($src) {
                                return new \Illuminate\Support\HtmlString('<div class="flex justify-center"><img src="' . $src . '" style="height: 200px; border-radius: 8px; object-fit: contain;" /></div>');
                            }

                            return null;
                        })
                        ->columnSpanFull()
                        ->visible(fn(string $operation) => $operation === 'view'),


                    \Filament\Forms\Components\Radio::make('image_source')
                        ->label('Origen de la Imagen')
                        ->options([
                            'default' => 'Imagen Predeterminada',
                            'upload' => 'Subir Imagen Personalizada',
                        ])
                        ->default(fn($record) => $record?->image_path ? 'upload' : 'default')
                        ->live()
                        ->dehydrated(false), // No guardar en BD

                    Select::make('image_url')
                        ->label('Seleccionar Imagen')
                        ->options([
                            'images/store/recargas.png' => 'Recargas (Genérica)',
                            'images/store/simcard.png' => 'Simcard (Genérica)',
                            'images/store/item.png' => 'Producto (Genérico)',
                        ])
                        ->visible(fn($get) => $get('image_source') === 'default')
                        ->required(fn($get) => $get('image_source') === 'default')
                        ->afterStateUpdated(fn(callable $set) => $set('image_path', null)), // Limpiar upload si selecciona default

                    \Filament\Forms\Components\Placeholder::make('image_preview')
                        ->label('Vista Previa')
                        ->content(fn($get) => $get('image_url') ? new \Illuminate\Support\HtmlString('<img src="' . asset($get('image_url')) . '" style="height: 150px; border-radius: 8px;" />') : null)
                        ->visible(fn($get) => $get('image_source') === 'default' && $get('image_url')),

                    FileUpload::make('image_path')
                        ->label('Subir Imagen')
                        ->directory('store/items')
                        ->image()
                        ->imageEditor()
                        ->visible(fn($get) => $get('image_source') === 'upload')
                        ->required(fn($get) => $get('image_source') === 'upload')
                        ->afterStateUpdated(fn(callable $set) => $set('image_url', null)), // Limpiar url si sube imagen
                ])
                ->columns(1),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Imagen')
                    ->getStateUsing(fn($record) => $record->image_url)
                    ->square()
                    ->size(40),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->colors([
                        'primary' => 'sim',
                        'success' => 'recharge',
                        'info' => 'device',
                        'gray' => 'accessory',
                    ])
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'sim' => 'SIM',
                        'recharge' => 'Recarga',
                        'device' => 'Dispositivo',
                        'accessory' => 'Accesorio',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('unit_value')
                    ->label('Valor unitario')
                    ->formatStateUsing(fn($state) => Number::currency((float) ($state ?? 0), 'COP', precision: 0))
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('Stock')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'sim' => 'SIM',
                        'recharge' => 'Recarga',
                        'device' => 'Dispositivo',
                        'accessory' => 'Accesorio',
                    ]),
                SelectFilter::make('is_active')
                    ->label('Estado')
                    ->options([
                        1 => 'Activos',
                        0 => 'Inactivos',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                TableAction::make('delete_product')
                    ->label('Eliminar')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-trash')
                    ->action(function (RedemptionProduct $record) {
                        if ($record->hasHistory()) {
                            Notification::make()
                                ->danger()
                                ->title('No se puede eliminar')
                                ->body('El producto tiene redenciones asociadas y se mantiene para conservar el histórico.')
                                ->send();
                            return;
                        }

                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('Producto eliminado')
                            ->body("{$record->name} se eliminó correctamente.")
                            ->send();
                    }),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->action(function (Collection $records) {
                        $bloqueados = [];
                        $eliminados = 0;

                        foreach ($records as $record) {
                            if ($record->hasHistory()) {
                                $bloqueados[] = $record->name;
                                continue;
                            }
                            $record->delete();
                            $eliminados++;
                        }

                        if ($eliminados > 0) {
                            Notification::make()
                                ->success()
                                ->title('Productos eliminados')
                                ->body("Se eliminaron {$eliminados} producto(s).")
                                ->send();
                        }

                        if (!empty($bloqueados)) {
                            Notification::make()
                                ->danger()
                                ->title('No se pudieron eliminar algunos productos')
                                ->body('Los siguientes productos tienen redenciones asociadas: ' . implode(', ', $bloqueados))
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedemptionProducts::route('/'),
            'create' => Pages\CreateRedemptionProduct::route('/create'),
            'edit' => Pages\EditRedemptionProduct::route('/{record}/edit'),
        ];
    }
}
