<?php

namespace App\Domain\Admin\Filament\Resources\MailSettingResource\Pages;

use App\Domain\Admin\Filament\Pages\Settings;
use App\Domain\Admin\Filament\Resources\MailSettingResource;
use App\Domain\Mail\Models\MailSetting;
use App\Domain\Mail\Services\MailConfigService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMailSetting extends EditRecord
{
    protected static string $resource = MailSettingResource::class;

    public function mount(int | string | null $record = null): void
    {
        // Si no hay record, cargar el registro único
        if ($record === null) {
            $record = MailSetting::getInstance()->id;
        }
        
        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            // No acciones adicionales necesarias
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var \App\Domain\Mail\Models\MailSetting $record */
        $record = $this->record;
        
        // Si la contraseña no está presente o está vacía, mantener la actual
        if (!isset($data['password']) || empty($data['password'])) {
            $current = $record->password;
            if ($current) {
                $data['password'] = $current;
            }
        }
        
        // Forzar mailer a SMTP
        $data['mailer'] = 'smtp';
        
        return $data;
    }

    protected function afterSave(): void
    {
        /** @var \App\Domain\Mail\Models\MailSetting $record */
        $record = $this->record;
        
        // Actualizar configuración en .env y limpiar cache
        $service = app(MailConfigService::class);
        
        // Obtener todos los atributos incluyendo password (que está en $hidden)
        $data = $record->getAttributes();
        $service->updateMailConfig($data);
        
        Notification::make()
            ->success()
            ->title('Configuración guardada')
            ->body('La configuración de correo ha sido actualizada exitosamente. El archivo .env ha sido actualizado.')
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return Settings::getUrl();
    }

    public function getBreadcrumbs(): array
    {
        return [
            Settings::getUrl() => Settings::getNavigationLabel(),
            $this->getResource()::getUrl('edit') => $this->getResource()::getPluralModelLabel(),
        ];
    }
}

