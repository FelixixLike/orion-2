<?php

declare(strict_types=1);

namespace App\Domain\Import\Support;

class OperatorReportSchema
{
    public const HEADERS = [
        'REGION',
        'FUERZADEVENTA',
        'DEALERPADRE',
        'SUBFUERZADEVENTA',
        'DEALERHIJO',
        'COID',
        'NUMERODETELEFONO',
        'TIPODEPLAN',
        'CODIGODELPLAN',
        'DESPLANSERVADICIONALPAQ',
        'CONCEPTODECOMISION80',
        'COMISIONPAGADAPOR80',
        'CONCEPTODECOMISION20',
        'COMISIONPAGADAPOR20',
        'ESTATUSDECOMISION',
        'MOTIVODELRECHAZO',
        'NIT',
        'ESTADODELALINEA',
        'FECHADEACTIVACION',
        'FECHADERECEPCION',
        'FECHADELEGALIZACION',
        'FECHADECORTE',
        'FECHADEDESACTIVACION',
        'FECHADEDEVOLUCION',
        'FECHADESOLUCION',
        'LLAMADA1SI0NO',
        'DUPLA',
        'NOMBREDELCLIENTE',
        'CEDULADECIUDADANIACLIENTE',
        'CUSTCODE',
        'ICCID',
        'MARCA',
        'MODELO',
        'IMEI',
        'CAUSALDEACTIVACION',
        'CODIGODEREGLAQUEPAGOLACOM',
        'ORIGEN',
        'DIASDECONSUMO',
        'CANTIDADDEMINDECONSUMO',
        'FECHADELPRIEVEFACTU',
        'VALORTOTCARGPORPERIODO',
        'PERIODODELACARGA',
        'PERIODSEQ',
        'PERIODSTARTDATE',
        'PERIODENDDATE',
        'BUSINESSU',
        'FECHADEACTUALIZACION',
        'USERID',
        'CANALDEVENTA',
        'TIPODESERVICIOADICIONAL',
        'RECLAMACIONGENERAL',
        'RECLAMACIONDETALLADA',
        'D_VALUE',
        'TRANSACTIONSEQ',
        'MOTIVODELRECHAZOGA1',
        'MOTIVODELRECHAZOGA2',
        'MOTIVODELRECHAZOGA3',
        'MOTIVODELRECHAZOGA4',
        'MOTIVODELRECHAZOGA5',
        'MOTIVODELRECHAZOGA6',
        'GENERICATTRIBUTE1',
        'GENERICATTRIBUTE2',
        'GENERICATTRIBUTE3',
        'GENERICATTRIBUTE4',
        'GENERICATTRIBUTE5',
        'GENERICATTRIBUTE6',
        'CS_PAYMENT_80',
        'CS_PAYMENT_20',
        'SM',
        'DEPOSITRULENAME',
        'RULE_ID',
        'STATUS',
        'FLAG_LIQUIDACION',
        'COMISIONPAGADAPOR80_AUX',
        'COMISIONPAGADAPOR20_AUX',
        'MONTO_CARGA',
        'USER_ADJUSTMENT',
        'MOTIVE_ADJUSTMENT',
        'TGA30',
        'CR_PF_MONTODECOMISION',
        'CODIGOPADRE',
        'CODIGOHIJO',
        'FRENTE',
        'PORCENTAJE_PAGO',
        'VALOR_EQUIPO',
        'VALOR_SIMCARD',
    ];

    /**
     * Devuelve las columnas con su llave normalizada.
     *
     * @return array<int,array{key:string,label:string}>
     */
    public static function columns(): array
    {
        return array_map(
            fn(string $header) => [
                'key' => self::normalizeHeader($header),
                'label' => $header,
            ],
            self::HEADERS
        );
    }

    public static function normalizeHeader(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = str_replace([' ', '-', '.', "\t"], '_', $normalized);
        $normalized = preg_replace('/_+/', '_', $normalized);

        return $normalized ?? '';
    }

    /**
     * Normaliza un registro completo.
     *
     * @param array<int|string,mixed> $row
     * @return array<string,mixed>
     */
    public static function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $header => $value) {
            if ($header === null || $header === '') {
                continue;
            }

            $normalized[self::normalizeHeader((string) $header)] = $value;
        }

        return $normalized;
    }

    public static function isOperatorSheet(array $headers): bool
    {
        if (empty($headers)) {
            return false;
        }

        $normalized = array_map(
            fn($header) => self::normalizeHeader((string) $header),
            $headers
        );

        $required = [
            self::normalizeHeader('COID'),
            self::normalizeHeader('NUMERODETELEFONO'),
            self::normalizeHeader('MONTO_CARGA'),
            self::normalizeHeader('PORCENTAJE_PAGO'),
            self::normalizeHeader('REGION'),
        ];

        $matches = 0;
        foreach ($required as $column) {
            if (in_array($column, $normalized, true)) {
                $matches++;
            }
        }

        return $matches >= 4;
    }
}
