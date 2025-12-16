<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Pages;

use App\Domain\Store\Models\LiquidationItem;
use App\Domain\Store\Services\LiquidationCalculationService;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class PeriodLinesDetailPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $title = 'Detalle por líneas';

    protected static ?string $navigationLabel = null;

    protected static ?string $slug = 'crossings/period-lines';

    protected string $view = 'filament.admin.crosses.pages.period-lines-detail';

    public ?string $period = null;
    public ?int $storeId = null;
    public bool $usingPreview = false;

    private LiquidationCalculationService $calculationService;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = Auth::guard('admin')->user();
        return $user?->hasAnyRole(['super_admin', 'administrator'], 'admin') ?? false;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return static::$slug ?? 'crossings/period-lines';
    }

    public function mount(): void
    {
        $this->calculationService = app(LiquidationCalculationService::class);
        $this->period = request()->query('period');
        $this->storeId = request()->query('store') ? (int) request()->query('store') : null;
    }

    protected function getViewData(): array
    {
        $linesData = $this->buildLinesPaginator();

        $this->usingPreview = $linesData['usingPreview'];

        return [
            'linesPaginator' => $linesData['paginator'],
            'usingPreview' => $this->usingPreview,
            'period' => $this->period,
            'storeId' => $this->storeId,
        ];
    }

    /**
     * @return array{paginator: LengthAwarePaginator, usingPreview: bool}
     */
    private function buildLinesPaginator(): array
    {
        $perPage = 50;
        $request = request();
        $page = $request->integer('page', 1);

        if (!$this->period) {
            return [
                'paginator' => new LengthAwarePaginator([], 0, $perPage, $page, [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]),
                'usingPreview' => false,
            ];
        }

        $query = $this->baseLinesQuery();
        $count = (clone $query)->count();

        if ($count > 0) {
            $items = $query
                ->orderBy('phone_number')
                ->forPage($page, $perPage)
                ->get()
                ->map(fn(LiquidationItem $item) => $this->mapItem($item));

            return [
                'paginator' => new LengthAwarePaginator(
                    $items,
                    $count,
                    $perPage,
                    $page,
                    ['path' => $request->url(), 'query' => $request->query()]
                ),
                'usingPreview' => false,
            ];
        }

        $lines = $this->previewLines();
        $total = $lines->count();
        $paged = $lines
            ->values()
            ->slice(($page - 1) * $perPage, $perPage)
            ->map(fn($line) => $this->mapPreviewLine($line));

        return [
            'paginator' => new LengthAwarePaginator(
                $paged,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            ),
            'usingPreview' => true,
        ];
    }

    private function baseLinesQuery(): Builder
    {
        $query = LiquidationItem::query()
            ->with(['liquidation.store'])
            ->where('period', $this->period);

        if ($this->storeId) {
            $query->whereHas('liquidation', fn($q) => $q->where('store_id', $this->storeId));
        }

        return $query;
    }

    private function previewLines(): Collection
    {
        $lines = collect();

        if (!$this->period) {
            return $lines;
        }

        $preview = $this->calculationService->calculateForPeriod($this->period);
        $lines = collect($preview['lines'] ?? []);

        if ($this->storeId) {
            $lines = $lines->filter(fn($line) => ($line['store_id'] ?? null) === $this->storeId);
        }

        return $lines;
    }

    public function export()
    {
        $source = null;

        if ($this->usingPreview && $this->period) {
            $source = $this->previewLines()
                ->map(fn($line) => $this->mapPreviewLine($line))
                ->all();
        } else {
            // Optimización: Pasamos el QUERY BUILDER DIRECTAMENTE
            // Esto permite que Laravel Excel use cursores y no cargue todo en RAM.
            $source = $this->baseLinesQuery();
        }

        $filename = 'detalle-periodo-' . ($this->period ?? 'sin-periodo') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Domain\Admin\Exports\PeriodLinesExport($source), $filename);
    }

    private function mapItem(LiquidationItem $item): array
    {
        $store = $item->liquidation?->store;

        return [
            'phone_number' => $item->phone_number,
            'iccid' => $item->iccid,
            'idpos' => $item->idpos,
            'total_commission' => (float) $item->total_commission,
            'operator_total_recharge' => (float) $item->operator_total_recharge,
            'movilco_recharge_amount' => (float) $item->movilco_recharge_amount,
            'base_liquidation_final' => (float) $item->base_liquidation_final,
            'residual_percentage' => $item->residual_percentage,
            'transfer_percentage' => $item->transfer_percentage ?? 100,
            'residual_payment' => (float) $item->residual_payment,
            'commission_status' => $item->commission_status,
            'activation_date' => optional($item->activation_date)->format('Y-m-d'),
            'cutoff_date' => optional($item->cutoff_date)->format('Y-m-d'),
            'custcode' => $item->custcode,
            'period' => $item->period,
            'store' => $store?->name,
            'store_idpos' => $store?->idpos,
        ];
    }

    private function mapPreviewLine(array $line): array
    {
        return [
            'phone_number' => $line['phone_number'] ?? null,
            'iccid' => $line['iccid'] ?? null,
            'idpos' => $line['idpos'] ?? null,
            'total_commission' => (float) ($line['total_commission'] ?? 0),
            'operator_total_recharge' => (float) ($line['operator_total_recharge'] ?? $line['operator_amount'] ?? 0),
            'movilco_recharge_amount' => (float) ($line['movilco_recharge_amount'] ?? $line['recharge_amount'] ?? 0),
            'base_liquidation_final' => (float) ($line['base_liquidation_final'] ?? $line['base_liquidation'] ?? 0),
            'residual_percentage' => $line['residual_percentage'] ?? 0,
            'transfer_percentage' => $line['traslado_percentage'] ?? ($line['transfer_percentage'] ?? 100),
            'residual_payment' => (float) ($line['pago_residual'] ?? 0),
            'commission_status' => $line['commission_status'] ?? null,
            'activation_date' => isset($line['activation_date']) && $line['activation_date'] instanceof \DateTimeInterface
                ? $line['activation_date']->format('Y-m-d')
                : ($line['activation_date'] ?? null),
            'cutoff_date' => isset($line['cutoff_date']) && $line['cutoff_date'] instanceof \DateTimeInterface
                ? $line['cutoff_date']->format('Y-m-d')
                : ($line['cutoff_date'] ?? null),
            'custcode' => $line['custcode'] ?? null,
            'period' => $line['period'] ?? $this->period,
            'store' => $line['store_name'] ?? null,
            'store_idpos' => $line['idpos'] ?? null,
        ];
    }
}
