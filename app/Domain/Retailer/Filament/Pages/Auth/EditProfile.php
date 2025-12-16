<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Retailer\Filament\Pages\Auth;

use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Domain\Auth\Services\PasswordStrengthCalculator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class EditProfile extends Page implements HasForms
{
    use InteractsWithForms;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-key';
    }

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.edit-profile';

    public ?array $data = [];

    public function getTitle(): string
    {
        return 'Contraseña';
    }

    public static function getLabel(): string
    {
        return 'Contraseña';
    }

    public function mount(): void
    {
        $this->form->fill([
            'name' => Auth::user()->name,
            'email' => Auth::user()->email,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('Cambiar Contraseña')
                    ->description('Para actualizar tu contraseña, por favor ingresa tu contraseña actual y la nueva.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Contraseña Actual')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule('current_password')
                            ->dehydrated(false)
                            ->validationAttribute('contraseña actual')
                            ->validationMessages([
                                'required' => 'La contraseña actual es obligatoria.',
                                'current_password' => 'La contraseña ingresada no es correcta.',
                            ]),

                        TextInput::make('password')
                            ->label('Nueva Contraseña')
                            ->password()
                            ->revealable()
                            ->rules(PasswordStrengthCalculator::getLaravelRules())
                            ->validationMessages([
                                'required' => 'La nueva contraseña es obligatoria.',
                                'min' => 'La contraseña debe tener al menos 8 caracteres.',
                                'regex' => 'La contraseña debe contener mayúsculas, minúsculas, números y símbolos.',
                            ])
                            ->autocomplete('new-password')
                            ->dehydrated(fn($state): bool => filled($state))
                            ->dehydrateStateUsing(fn($state): string => Hash::make($state))
                            ->live(debounce: 500)
                            ->same('passwordConfirmation')
                            ->helperText('Debe contener 8+ caracteres, mayúsculas, minúsculas, números y símbolos.')
                            ->required(),

                        TextInput::make('passwordConfirmation')
                            ->label('Confirmar Nueva Contraseña')
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false),
                    ])->columns(1),
            ]);
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $user = Auth::user();

        if (!empty($data['password'])) {
            $user->password = $data['password'];
            $user->save();

            Notification::make()
                ->title('Contraseña actualizada correctamente')
                ->success()
                ->send();
        }
    }
}