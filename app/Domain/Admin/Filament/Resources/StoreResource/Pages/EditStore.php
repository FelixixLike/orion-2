<?php

namespace App\Domain\Admin\Filament\Resources\StoreResource\Pages;

use App\Domain\Admin\Filament\Resources\StoreResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    /**
     * Muestra acciones de formulario incluyendo borrar.
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Borrar Tienda')
                ->modalDescription(function () {
                    $store = $this->record;
                    $tendero = $store?->tenderer?->getFilamentName() ?? 'Sin asignar';

                    return "Vas a borrar la tienda {$store->name} (ID_PDV: {$store->idpos}). Tendero: {$tendero}. ¿Confirmas?";
                })
                ->successNotificationTitle('Tienda eliminada'),
            $this->getCancelFormAction(),
        ];
    }

    /**
     * Prefill del tendero desde pivot si user_id est╠ü vac╠ªo.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (empty($data['user_id'])) {
            $data['user_id'] = $this->record?->users()->value('users.id');
        }

        return $data;
    }

    /**
     * Mantiene sincronizada la tabla pivot store_user con el tendero seleccionado.
     */
    protected function afterSave(): void
    {
        if ($this->record) {
            $userId = $this->record->user_id;
            $this->record->users()->sync($userId ? [$userId] : []);
        }
    }
}
