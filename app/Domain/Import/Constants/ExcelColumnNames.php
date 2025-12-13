<?php

declare(strict_types=1);

namespace App\Domain\Import\Constants;

/**
 * Constantes con los nombres de columnas del Excel para cada tipo de importación.
 * 
 * Estos nombres deben coincidir exactamente con los encabezados de las columnas
 * en los archivos Excel después de la normalización (minúsculas, sin espacios).
 */
class ExcelColumnNames
{
    /**
     * Columnas para Reporte del Operador
     * Nombres reales del Excel: NUMERODETELEFONO, CODIGOHIJO, ESTATUSDECOMISION, etc.
     */
    public const OPERATOR_REPORT = [
        'phone_number' => [
            'numerodetelefono', // NOMBRE REAL
            'numero de telefono',
            'numero_telefono',
            'phone_number',
            'telefono',
            'numero',
        ],
        'city_code' => [
            'codigohijo', // NOMBRE REAL
            'codigo hijo',
            'codigo_hijo',
            'city_code',
            'codigo_ciudad',
        ],
        'commission_status' => [
            'estatusdecomision', // NOMBRE REAL (ESTATUSDECOMISION)
            'statusdecomision',
            'status de comision',
            'status_comision',
            'commission_status',
            'estado_comision',
        ],
        'activation_date' => [
            'fechadeactivacion', // NOMBRE REAL
            'fecha de activacion',
            'fecha_activacion',
            'activation_date',
        ],
        'cutoff_date' => [
            'fechadecorte', // NOMBRE REAL
            'fecha de corte',
            'fecha_corte',
            'cutoff_date',
        ],
        'commission_paid_80' => [
            'conceptodecomision80', // NOMBRE REAL
            'concepto de comision 80',
            'comision_pagada_por_80',
            'comisionpagadapor80',
            'commission_paid_80',
        ],
        'commission_paid_20' => [
            'comisionpagadapor20', // NOMBRE REAL
            'comision pagada por 20',
            'comision_pagada_por_20',
            'conceptodecomision20',
            'commission_paid_20',
        ],
        'recharge_amount' => [
            'monto_carga', // NOMBRE REAL DEL EXCEL
            'monto carga',
            'valorrecarga',
            'valor recarga',
            'recharge_amount',
            'load_amount', // compatibilidad hacia atras
        ],
        'recharge_period' => [
            'periododelacarga', // NOMBRE REAL DEL EXCEL
            'periodo de la carga',
            'recharge_period',
            'load_period', // compatibilidad hacia atras
        ],
        'custcode' => [
            'custcode', // NOMBRE REAL
            'cust_code',
        ],
        'total_recharge_per_period' => [
            'valortotcargporperiodo', // NOMBRE REAL DEL EXCEL
            'valor tot carg por periodo',
            'valor total carga por periodo',
            'valortotcargaporperiodo',
            'total_recharge_per_period',
            'total_load_per_period', // compatibilidad hacia atras
        ],
        'coid' => [
            'coid', // NOMBRE REAL
            'co_id',
            'co id',
        ],
        'payment_percentage' => [
            'porcentaje_pago', // NOMBRE REAL DEL EXCEL
            'porcentajepago',
            'porcentaje pago',
        ],
        // ICCID opcional (no está en las columnas reales mencionadas)
        'iccid' => [
            'iccid',
            'iccid_raw',
            'iccid crudo',
        ],
    ];

    /**
     * Columnas para Recargas
     * Nombres reales del Excel: NUMERO, VALOR RECARGA, MES (Variables.xlsx)
     */
    public const RECHARGE = [
        'phone_number' => [
            'numero', // NOMBRE REAL
            'numero de telefono',
            'numero_telefono',
            'phone_number',
            'telefono',
            'numerodetelefono',
        ],
        'recharge_amount' => [
            'valor recarga', // NOMBRE REAL (con espacio)
            'valor_recarga',
            'recharge_amount',
        ],
        'period_date' => [
            'mes', // NOMBRE REAL (viene como texto: ENERO, FEBRERO, etc - usar now())
            'period_date',
            'fecha',
            'fecha_recarga',
        ],
        'iccid' => [
            'iccid',
            'iccid_normalizado',
        ],
    ];

    /**
     * Columnas para Condiciones de Venta
     * Nombres reales del Excel: TELEFONOS, IDPOS, VALOR, RESIDUAL, MES (Ventas_Simcard.xlsx)
     */
    public const SALES_CONDITION = [
        'phone_number' => [
            'telefonos', // NOMBRE REAL
            'numero de telefono',
            'numero_telefono',
            'phone_number',
            'telefono',
            'numerodetelefono',
        ],
        'idpos' => [
            'idpos', // NOMBRE REAL
        ],
        'sale_price' => [
            'valor', // NOMBRE REAL
        ],
        'commission_percentage' => [
            'residual', // NOMBRE REAL (es la comisión)
            'porcentaje_comision',
            'porcentaje de comision',
            'commission_percentage',
            'porcentaje',
            'comisiona',
        ],
        'period_date' => [
            'mes', // NOMBRE REAL
            'period_date',
            'fecha',
            'fecha_venta',
            'fecha venta',
        ],
        'population' => [
            'poblacion',
            'population',
        ],
        // ICCID opcional (no está en las columnas reales mencionadas)
        'iccid' => [
            'iccid',
            'iccid_normalizado',
        ],

        // Tiendas
        'store' => [
            'id_pdv',
            'nro_documento',
            'nombre_punto',
            'nombre_cliente',
            'ruta',
            'celular',
            'municipio',
            'barrio',
            'direccion',
            'correo_electronico',
            'tenderos_usernames',
        ],
    ];
}
