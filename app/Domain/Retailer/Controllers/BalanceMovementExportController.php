<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Retailer\Controllers;

use App\Domain\Store\Models\BalanceMovement;
use Illuminate\Http\Request;

class BalanceMovementExportController
{
    public function __invoke(Request $request, BalanceMovement $movement)
    {
        $user = auth('retailer')->user();

        if (! $user) {
            abort(403);
        }

        $storeIds = $user->stores()->pluck('stores.id')->all();
        if (! in_array($movement->store_id, $storeIds, true)) {
            abort(403);
        }

        $filename = sprintf(
            'movimiento-%s-%s.pdf',
            $movement->id,
            optional($movement->movement_date)->format('Ymd') ?? 'sin-fecha'
        );

        $pdfContent = $this->buildSimplePdf($movement);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Genera un PDF muy sencillo (sin dependencias externas)
     * con los datos b&aacute;sicos del movimiento.
     */
    private function buildSimplePdf(BalanceMovement $movement): string
    {
        $lines = [
            'Comprobante de movimiento de saldo',
            '',
            'ID: ' . $movement->id,
            'Fecha: ' . optional($movement->movement_date)->format('Y-m-d'),
            'Tipo: ' . ($movement->movement_type ?? ''),
            'Operacion: ' . ($movement->operation ?? $movement->source_type ?? ''),
            'Descripcion: ' . (string) $movement->description,
            'Monto: ' . (string) $movement->amount,
            'Saldo despues del movimiento: ' . (string) $movement->balance_after,
            'Estado: ' . (string) $movement->status,
        ];

        if (! empty($movement->metadata)) {
            $lines[] = '';
            $lines[] = 'Detalle adicional:';

            foreach ($movement->metadata as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $lines[] = $key . ': ' . (string) $value;
                }
            }
        }

        $pdf = "%PDF-1.4\n";

        $objects = [];

        // 1: Catalog
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // 2: Pages
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        // 3: Page
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";

        // 4: Content stream
        // Nota: usamos Td de forma relativa; por eso fijamos
        // la posición solo en la primera línea y luego
        // movemos el cursor verticalmente.
        $contentStream = "BT\n/F1 12 Tf\n";
        $lineHeight = 18;
        $startY = 800;
        $firstLine = true;

        foreach ($lines as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);

            if ($firstLine) {
                // Posicionamos el cursor en (50, startY)
                $contentStream .= "50 {$startY} Td\n";
                $firstLine = false;
            } else {
                // Bajamos una línea manteniendo la misma X
                $contentStream .= "0 -" . $lineHeight . " Td\n";
            }

            $contentStream .= "({$escaped}) Tj\n";
        }

        $contentStream .= "ET\n";

        $objects[] = "4 0 obj\n<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "endstream\nendobj\n";

        // 5: Font
        $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $offsets = [];
        $offset = strlen($pdf);

        foreach ($objects as $object) {
            $offsets[] = $offset;
            $pdf .= $object;
            if (! str_ends_with($object, "\n")) {
                $pdf .= "\n";
            }
            $offset = strlen($pdf);
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $objectOffset) {
            $pdf .= sprintf("%010d 00000 n \n", $objectOffset);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
