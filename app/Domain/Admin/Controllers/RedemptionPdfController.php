<?php

namespace App\Domain\Admin\Controllers;

use App\Domain\Store\Enums\Municipality;
use App\Domain\Store\Models\Redemption;
use Illuminate\Http\Request;

class RedemptionPdfController
{
    public function __invoke(Request $request, Redemption $redemption)
    {
        $filename = sprintf(
            'redencion-%s-%s.pdf',
            $redemption->id,
            optional($redemption->requested_at)->format('Ymd') ?? 'sin-fecha'
        );

        $pdfContent = $this->buildSimplePdf($redemption);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function formatMunicipality($municipality): string
    {
        if ($municipality instanceof Municipality) {
            return $municipality->label();
        }

        if (is_string($municipality) && $enum = Municipality::tryFrom($municipality)) {
            return $enum->label();
        }

        return (string) ($municipality ?? 'N/A');
    }

    private function buildSimplePdf(Redemption $redemption): string
    {
        $lines = [
            'Detalle de Redencion',
            '',
            'ID: ' . $redemption->id,
            'Fecha Solicitud: ' . optional($redemption->requested_at)->format('Y-m-d H:i'),
            'Estado: ' . ($redemption->status ?? 'N/A'),
            '',
            '-- Producto --',
            'Nombre: ' . ($redemption->redemptionProduct->name ?? 'N/A'),
            'Cantidad: ' . $redemption->quantity,
            'Valor Total: ' . '$ ' . number_format($redemption->total_value, 0, ',', '.'),
            '',
            '-- Tienda --',
            'Nombre: ' . ($redemption->store->name ?? 'N/A'),
            'IDPOS: ' . ($redemption->store->idpos ?? 'N/A'),
            'Municipio: ' . ($this->formatMunicipality($redemption->store->municipality) ?? 'N/A'),
            'Solicitado por: ' . ($redemption->store->tenderer?->getFilamentName() ?? 'N/D'),
            '',
            '-- Notas --',
            (string) $redemption->notes,
        ];

        return $this->generatePdfFromLines($lines);
    }

    private function generatePdfFromLines(array $lines): string
    {
        $pdf = "%PDF-1.4\n";

        $objects = [];

        // 1: Catalog
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // 2: Pages
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        // 3: Page
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";

        // 4: Content stream
        $contentStream = "BT\n/F1 12 Tf\n";
        $lineHeight = 18;
        $startY = 800;
        $firstLine = true;

        foreach ($lines as $line) {
            // Basic escaping of PDF special characters
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
            if (!str_ends_with($object, "\n")) {
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
