<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
declare(strict_types=1);

namespace App\Domain\Admin\Filament\Resources\ImportResource\Forms;

use App\Domain\Import\Enums\ImportType;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cargar Archivo Excel')
                    ->schema([
                        Select::make('type')
                            ->label('¿Qué archivo vas a subir?')
                            ->options([
                                ImportType::OPERATOR_REPORT->value => 'Pagos Claro (Reporte del operador)',
                                ImportType::RECHARGE->value => 'Recargas / Variables',
                            ])
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->helperText('Solo Pagos Claro o Recargas se cargan desde este módulo. Las tiendas y condiciones se administran desde sus recursos dedicados.'),

                        FileUpload::make('files')
                            ->label('Archivos Excel (.xlsx)')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->required()
                            ->multiple()
                            ->maxFiles(50)
                            ->maxSize(512000) // 500MB
                            ->directory('imports')
                            ->visibility('private')
                            ->helperText('Puedes subir archivos grandes en partes. Los registros se agregarán sin sobrescribir datos existentes.'),

                        Actions::make([
                            Action::make('download_template')
                                ->label('Descargar plantilla')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->action(function (callable $get) {
                                    $type = $get('type');

                                    if (!$type) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Selecciona un tipo de importación')
                                            ->body('Define el tipo que vas a cargar y luego descarga la plantilla.')
                                            ->send();
                                        return null;
                                    }

                                    $fileMap = [
                                        ImportType::OPERATOR_REPORT->value => 'template/Pagos_Claro.xlsx',
                                        ImportType::RECHARGE->value => 'template/Variables.xlsx',
                                    ];

                                    if (!isset($fileMap[$type])) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Tipo de plantilla no soportado')
                                            ->body('Selecciona uno de los tipos compatibles.')
                                            ->send();
                                        return null;
                                    }

                                    $path = Storage::disk('public')->path($fileMap[$type]);

                                    return response()->download($path);
                                }),
                        ])->columns(1),
                    ])
                    ->columns(1),

                Section::make('Periodo y corte')
                    ->schema([
                        TextInput::make('period')
                            ->label('Periodo (YYYY-MM)')
                            ->helperText('Ejemplo: 2025-11. Puedes subir el mismo período en múltiples partes.')
                            ->required()
                            ->rules([
                                'regex:/^[0-9]{4}-(0[1-9]|1[0-2])$/',
                            ]),
                        Hidden::make('cutoff_number')
                            ->default(null)
                            ->dehydrated(),
                    ])
                    ->columns(2),

                Section::make('Descripción')
                    ->schema([
                        Textarea::make('description')
                            ->label('Descripción (Opcional)')
                            ->placeholder('Agrega una descripción para esta importación...')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Descripción opcional para identificar o documentar esta importación.'),
                    ])
                    ->columns(1),
            ])
            ->columns(2);
    }

}
