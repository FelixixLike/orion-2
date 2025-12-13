<x-filament-panels::page>
    {{ $this->infolist }}

    <div style="margin-top: 1.5rem;">
        @foreach ($this->getRelationManagers() as $index => $relationManagerClass)
            <div style="margin-top: {{ $index > 0 ? '1.5rem' : '0' }};">
                @livewire(
                    $relationManagerClass,
                    [
                        'ownerRecord' => $this->getRecord(),
                        'pageClass' => static::class,
                    ],
                    key($relationManagerClass . '-' . $this->getRecord()->id)
                )
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
