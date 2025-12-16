<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Resources\UserResource\Pages;

use App\Domain\Admin\Filament\Resources\UserResource;
use App\Domain\User\Enums\UserRole;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Services\UserEmailService;
use App\Support\Filament\UserFormValidator;
use App\Support\Mail\MailConfigValidator;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private ?string $roleName = null;
    private array $permissions = [];
    private ?UserEmailService $emailService = null;
    private ?string $plainPassword = null; // Contraseña en texto plano antes de hashear

    public function mount(): void
    {
        parent::mount();
        $this->emailService = app(UserEmailService::class);
    }

    private function getEmailService(): UserEmailService
    {
        if ($this->emailService === null) {
            $this->emailService = app(UserEmailService::class);
        }

        return $this->emailService;
    }

    /**
     * Personaliza los botones del formulario.
     * Solo muestra "Crear" y "Cancelar", eliminando "Crear y crear otro".
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    /**
     * Hook ejecutado antes de crear el registro.
     * Validamos la configuración de correo antes de permitir la creación.
     */
    protected function beforeCreate(): void
    {
        MailConfigValidator::validateOrFail();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $currentUser = auth('admin')->user();

        // Capturar contraseña en texto plano antes de hashearla
        if (isset($data['password']) && !empty($data['password'])) {
            // Verificar si ya está hasheada (no debería estarlo ahora que removimos dehydrateStateUsing)
            if (!str_starts_with($data['password'], '$2y$')) {
                $this->plainPassword = $data['password'];
                // Hashear la contraseña antes de pasarla al validador
                $data['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
            }
        }

        // Validar y procesar contraseña (ahora espera password hasheada)
        $data = UserFormValidator::validatePassword($data, $currentUser, null);

        // Validar y procesar rol
        $this->roleName = UserFormValidator::validateRole($data, $currentUser, null);

        // Validación defensiva: Si es retailer, forzar estado "inactive"
        if ($this->roleName === UserRole::RETAILER->value && isset($data['status']) && $data['status'] !== UserStatus::INACTIVE->value) {
            // Forzar estado inactive para retailers (Opción 1)
            $data['status'] = UserStatus::INACTIVE->value;

            \Filament\Notifications\Notification::make()
                ->info()
                ->title('Estado ajustado')
                ->body('Los retailers siempre se crean como Inactivos. El estado ha sido ajustado automáticamente.')
                ->send();
        }

        // Validar y procesar permisos
        $this->permissions = UserFormValidator::validatePermissions($data, $currentUser, null);

        // Eliminar campos de formulario que no son de la tabla users
        unset($data['role'], $data['permissions']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var \App\Domain\User\Models\User $user */
        $user = $this->record;

        // Asignar rol y permisos
        $this->assignRoleAndPermissions($user);

        // Enviar emails según el tipo de usuario
        $this->sendUserEmails($user);
    }

    /**
     * Asigna el rol y permisos al usuario.
     *
     * @param \App\Domain\User\Models\User $user
     * @return void
     */
    private function assignRoleAndPermissions(\App\Domain\User\Models\User $user): void
    {
        if ($this->roleName) {
            $guard = $this->roleName === UserRole::RETAILER->value ? 'retailer' : 'admin';
            $role = \Spatie\Permission\Models\Role::findByName($this->roleName, $guard);
            $user->assignRole($role);
        }

        if (!empty($this->permissions)) {
            $user->givePermissionTo($this->permissions);
        }
    }

    /**
     * Envía los emails correspondientes según el tipo de usuario.
     *
     * @param \App\Domain\User\Models\User $user
     * @return void
     */
    private function sendUserEmails(\App\Domain\User\Models\User $user): void
    {
        $role = $user->roles()->first();
        /** @var \Spatie\Permission\Models\Role|null $role */
        $isRetailer = $role && $role->guard_name === 'retailer';

        $emailService = $this->getEmailService();

        if ($isRetailer) {
            $result = $emailService->sendRetailerActivationEmail($user);
            $notification = \App\Domain\User\Support\UserNotificationBuilder::fromActivationResult($result);
        } else {
            $result = $emailService->sendAdminWelcomeEmail($user, $this->plainPassword);
            $notification = \App\Domain\User\Support\UserNotificationBuilder::fromAdminWelcomeResult($result);
        }

        if ($notification) {
            $notification->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        $scope = request()->get('scope');

        if ($scope) {
            return $this->getResource()::getUrl('index', ['scope' => $scope]);
        }

        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        // Notificación personalizada se envía en afterCreate según el tipo de usuario
        return null;
    }
}
