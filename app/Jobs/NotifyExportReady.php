<?php

namespace App\Jobs;

use App\Domain\User\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyExportReady implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $userId,
        protected string $fileName
    ) {
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user)
            return;

        Notification::make()
            ->title('Reporte Listo')
            ->body('Tu reporte de liquidación detallada está listo para descargar.')
            ->success()
            ->actions([
                Action::make('Descargar')
                    ->button()
                    ->url(asset('storage/exports/' . $this->fileName), shouldOpenInNewTab: true),
            ])
            ->sendToDatabase($user);
    }
}
