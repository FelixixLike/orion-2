<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Admin\Filament\Pages\Models\SettingsRecord;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Settings extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.admin.pages.settings';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationLabel(): string
    {
        return 'Configuraciones';
    }

    public function getTitle(): string
    {
        return 'Configuraciones';
    }

    public static function getNavigationSort(): ?int
    {
        return 70;
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    /**
     * Solo super_admin / administrator pueden ver Configuraciones
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasPermissionTo('settings.view', 'admin') ?? false;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('label')
                    ->label('Configuracion')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descripcion')
                    ->limit(50)
                    ->wrap()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('url')
                    ->label('Accion')
                    ->html()
                    ->formatStateUsing(fn (?string $state, SettingsRecord $record): string =>
                        '<a href="' . ($state ?? $record->url) . '" class="text-primary-600 dark:text-primary-400 hover:underline">' .
                        'Configurar' .
                        '</a>'
                    ),
            ])
            ->paginated(false);
    }

    protected function getTableQuery(): Builder
    {
        // Sincronizar registros desde los DTOs
        $this->ensureSettingsRecordsExist();

        return SettingsRecord::query();
    }

    protected function ensureSettingsRecordsExist(): void
    {
        foreach (\App\Domain\Admin\Filament\Pages\Data\SettingsCategory::all() as $category) {
            foreach ($category->items as $item) {
                $id = md5($category->name . $item->label);

                SettingsRecord::updateOrCreate(
                    ['id' => $id],
                    [
                        'category' => $category->name,
                        'label' => $item->label,
                        'description' => $item->description,
                        'url' => $item->url,
                    ]
                );
            }
        }
    }
}
