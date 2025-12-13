<?php

namespace App\Domain\Admin\Controllers;

use App\Domain\Store\Models\Liquidation;
use App\Domain\Store\Models\LiquidationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiquidationPdfController
{
    public function __invoke(Request $request, $period, $storeId = null)
    {
        if (!$period) {
            abort(404, 'Periodo requerido');
        }

        $query = LiquidationItem::with(['liquidation.store'])
            ->where('period', $period);

        if ($storeId) {
            $query->whereHas('liquidation', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            return response('No hay datos para liquidar en este periodo.', 404);
        }

        $filename = "liquidacion-{$period}.pdf";
        $pdfContent = $this->buildPdf($items, $period);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildPdf($items, $period): string
    {
        $grouped = $items->groupBy(fn($item) => $item->liquidation->store->name ?? 'Desconocida');
        $lines = [];

        $lines[] = "REPORTE DE LIQUIDACION";
        $lines[] = "Periodo: $period";
        $lines[] = "Generado: " . now()->format('Y-m-d H:i');
        $lines[] = str_repeat('-', 80);
        $lines[] = "";

        $grandTotalCommission = 0;
        $grandTotalBase = 0;

        foreach ($grouped as $storeName => $storeItems) {
            $lines[] = "TIENDA: $storeName";
            $storeCommission = 0;
            $storeBase = 0;

            foreach ($storeItems as $item) {
                $concept = $item->concept ?? 'Comision';
                $comm = number_format($item->total_commission, 0, ',', '.');
                $base = number_format($item->base_liquidation_final, 0, ',', '.');

                $lines[] = "  - $concept | Com: $$comm | Total: $$base";

                $storeCommission += $item->total_commission;
                $storeBase += $item->base_liquidation_final;
            }

            $lines[] = "  SUBTOTAL TIENDA: Com: $" . number_format($storeCommission, 0, ',', '.') . " | Total: $" . number_format($storeBase, 0, ',', '.');
            $lines[] = str_repeat('-', 40);

            $grandTotalCommission += $storeCommission;
            $grandTotalBase += $storeBase;
        }

        $lines[] = "";
        $lines[] = str_repeat('=', 80);
        $lines[] = "GRAN TOTAL";
        $lines[] = "Comisiones: $" . number_format($grandTotalCommission, 0, ',', '.');
        $lines[] = "Total a Pagar: $" . number_format($grandTotalBase, 0, ',', '.');

        return $this->generatePdfFromLines($lines);
    }

    private function generatePdfFromLines(array $lines): string
    {
        $pdf = "%PDF-1.4\n";
        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        // Aumentamos el tamaño de página para que quepan más líneas (hack rápido)
        // MediaBox [0 0 595 1842] (doble alto de A4 aprox para listas largas)
        // Lo ideal sería paginar, pero en raw PDF es complejo.
        $pageHeight = 3000;
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 {$pageHeight}] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";

        $contentStream = "BT\n/F1 10 Tf\n";
        $lineHeight = 12;
        $startY = $pageHeight - 50;
        $firstLine = true;

        foreach ($lines as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            if ($firstLine) {
                $contentStream .= "50 {$startY} Td\n";
                $firstLine = false;
            } else {
                $contentStream .= "0 -" . $lineHeight . " Td\n";
            }
            $contentStream .= "({$escaped}) Tj\n";
        }

        $contentStream .= "ET\n";
        $objects[] = "4 0 obj\n<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "endstream\nendobj\n";
        $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n";

        $offsets = [];
        $offset = strlen($pdf);
        foreach ($objects as $object) {
            $offsets[] = $offset;
            $pdf .= $object;
            if (!str_ends_with($object, "\n"))
                $pdf .= "\n";
            $offset = strlen($pdf);
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        foreach ($offsets as $objectOffset) {
            $pdf .= sprintf("%010d 00000 n \n", $objectOffset);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
