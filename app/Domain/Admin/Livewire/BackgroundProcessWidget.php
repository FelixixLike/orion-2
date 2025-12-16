<?php

namespace App\Domain\Admin\Livewire;

use App\Domain\Admin\Models\BackgroundProcess;
use Livewire\Component;
use Livewire\Attributes\On;

class BackgroundProcessWidget extends Component
{
    public $processes = [];

    public function mount()
    {
        $this->loadProcesses();
    }

    #[On('refresh-processes')]
    public function loadProcesses()
    {
        // Auto-limpiar procesos completados/fallidos de más de 5 minutos
        // Auto-cleanup disabled as per user request to manual dismiss only
        // BackgroundProcess::query()
        //     ->where('user_id', auth()->id())
        //     ->whereIn('status', ['completed', 'failed'])
        //     ->where('updated_at', '<', now()->subMinutes(5))
        //     ->delete();

        // Cargar procesos activos y recién completados
        $this->processes = BackgroundProcess::query()
            ->where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'running', 'completed', 'failed'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function dismiss($processId)
    {
        BackgroundProcess::where('id', $processId)
            ->where('user_id', auth()->id())
            // ->whereIn('status', ['completed', 'failed']) // Permitir borrar cualquiera
            ->delete();

        $this->loadProcesses();
    }

    public function render()
    {
        return view('livewire.background-process-widget');
    }
}
