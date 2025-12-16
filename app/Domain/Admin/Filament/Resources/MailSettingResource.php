<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources;

use App\Domain\Admin\Filament\Resources\MailSettingResource\Pages;
use App\Domain\Mail\Models\MailSetting;
use App\Domain\Mail\Services\MailConfigService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MailSettingResource extends Resource
{
    protected static ?string $model = MailSetting::class;

    public static function shouldRegisterNavigation(): bool
    {
        // No mostrar en el menú lateral, se accede desde Settings
        return false;
    }

    protected static ?string $modelLabel = 'Configuración de Correo';

    protected static ?string $pluralModelLabel = 'Configuraciones de Correo';

    public static function canViewAny(): bool
    {
        $user = auth('admin')->user();
        return $user?->hasRole('super_admin', 'admin') ?? false;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        // Solo permitir editar el registro único
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuración General')
                    ->description('Configuración básica para el envío de correos electrónicos')
                    ->schema([
                        TextInput::make('from_address')
                            ->label('Dirección de Correo Electrónico')
                            ->email()
                            ->required()
                            ->helperText('Dirección de correo desde la cual se enviarán los emails y para autenticación SMTP'),
                        
                        TextInput::make('from_name')
                            ->label('Nombre del Remitente')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Nombre que aparecerá como remitente'),
                    ]),
                
                Section::make('Configuración SMTP')
                    ->description('Configuración para servidores SMTP (Gmail, Zoho, Outlook, etc.)')
                    ->schema([
                        TextInput::make('host')
                            ->label('Servidor SMTP')
                            ->placeholder('smtp.gmail.com')
                            ->required()
                            ->helperText('Dirección del servidor SMTP'),
                        
                        TextInput::make('port')
                            ->label('Puerto')
                            ->numeric()
                            ->default(587)
                            ->required()
                            ->helperText('Puerto del servidor (587 para TLS, 465 para SSL)'),
                        
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Dejar vacío para mantener la contraseña actual. Ingrese nueva contraseña para cambiarla.'),
                        
                        Select::make('encryption')
                            ->label('Encriptación')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                            ])
                            ->required()
                            ->helperText('Método de encriptación (TLS para puerto 587, SSL para puerto 465)'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mailer')
                    ->label('Método')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (): string => 'SMTP'),
                TextColumn::make('from_address')
                    ->label('Remitente'),
                TextColumn::make('from_name')
                    ->label('Nombre'),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'edit' => Pages\EditMailSetting::route('/'),
        ];
    }

    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return static::getUrl('edit', $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
    }
}
