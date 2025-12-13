<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Reporte de Liquidación {{ $period }}</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .store-header {
            background-color: #e9e9e9;
            font-weight: bold;
        }

        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Reporte de Liquidación</h2>
        <p>Periodo: {{ $period }}</p>
        <p>Fecha de generación: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tienda</th>
                <th>Concepto</th>
                <th>Valor Venta</th>
                <th>Comisión</th>
                <th>Total a Pagar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groupedItems as $storeName => $items)
                <tr class="store-header">
                    <td colspan="5">{{ $storeName }}</td>
                </tr>
                @foreach($items as $item)
                    <tr>
                        <td></td>
                        <td>{{ $item->concept ?? 'Comisión' }}</td>
                        <td>$ {{ number_format($item->sales_amount, 0, ',', '.') }}</td>
                        <td>$ {{ number_format($item->total_commission, 0, ',', '.') }}</td>
                        <td>$ {{ number_format($item->base_liquidation_final, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">Total {{ $storeName }}:</td>
                    <td>$ {{ number_format($items->sum('total_commission'), 0, ',', '.') }}</td>
                    <td>$ {{ number_format($items->sum('base_liquidation_final'), 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row" style="background-color: #333; color: white;">
                <td colspan="3" style="text-align: right;">GRAN TOTAL:</td>
                <td>$ {{ number_format($totalCommission, 0, ',', '.') }}</td>
                <td>$ {{ number_format($totalBase, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</body>

</html>