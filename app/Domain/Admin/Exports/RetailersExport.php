<?php

namespace App\Domain\Admin\Exports;

use App\Domain\Retailer\Support\BalanceService;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RetailersExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly Builder $query,
    ) {
    }

    public function collection()
    {
        return $this->query
            ->with(['stores:id', 'creator:id,first_name,last_name,id_number', 'modifier:id,first_name,last_name,id_number'])
            ->orderBy('id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Apellido',
            'Email',
            'Cédula',
            'Estado',
            'Saldo tendero (COP)',
            'Tiendas asociadas',
            'Creado',
            'Creado por',
            'Actualizado por',
        ];
    }

    /**
     * @param  User  $user
     */
    public function map($user): array
    {
        $status = UserStatus::tryFrom($user->status)?->label() ?? (string) $user->status;

        $balanceService = new BalanceService();
        $storeIds = $user->stores?->pluck('id')->all() ?? [];
        $totalBalance = 0;
        foreach ($storeIds as $storeId) {
            $totalBalance += $balanceService->getStoreBalance((int) $storeId);
        }

        return [
            $user->id,
            $user->first_name,
            $user->last_name,
            $user->email,
            $user->id_number,
            $status,
            $totalBalance,
            count($storeIds),
            optional($user->created_at)?->format('d/m/Y H:i'),
            $user->creator ? "{$user->creator->getFilamentName()} - {$user->creator->id_number}" : null,
            $user->modifier ? "{$user->modifier->getFilamentName()} - {$user->modifier->id_number}" : null,
        ];
    }
}

