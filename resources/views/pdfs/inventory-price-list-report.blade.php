<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Price List Inventory Report</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #000000;
        }
        .header-row {
            width: 100%;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #000000;
        }
        .clearfix::after { content: ""; display: table; clear: both; }
        .header-left { width: 60%; float: left; }
        .header-right { width: 40%; float: right; text-align: right; }
        .report-title { font-size: 13pt; font-weight: bold; margin-bottom: 3px; }
        .report-meta { font-size: 8pt; color: #444; }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 8pt;
        }
        .items-table th {
            background-color: #E0E0E0;
            border: 1px solid #000000;
            padding: 4px 3px;
            font-weight: bold;
            text-align: center;
            white-space: nowrap;
        }
        .items-table td {
            border: 1px solid #000000;
            padding: 3px 3px;
            vertical-align: top;
        }
        .items-table tr:nth-child(even) td { background-color: #F8F8F8; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row td {
            background-color: #E0E0E0 !important;
            font-weight: bold;
            border-top: 2px solid #000000;
        }
        .no-price { color: #999; font-style: italic; }
    </style>
</head>
<body>

<div class="header-row clearfix">
    <div class="header-left">
        <div class="report-title">Price List Inventory Report</div>
        @if($defaultPriceList)
            <div class="report-meta">Price List: {{ $defaultPriceList->code }}@if($defaultPriceList->description) — {{ $defaultPriceList->description }}@endif</div>
        @endif
    </div>
    <div class="header-right">
        <div class="report-meta">Generated: {{ $generatedAt }}</div>
        <div class="report-meta">Total Items: {{ $rows->count() }}</div>
    </div>
</div>

<table class="items-table">
    <thead>
        <tr>
            <th style="width: 3%;">#</th>
            <th style="width: 8%;">Code</th>
            <th style="width: 14%;">Short Name</th>
            <th>Description</th>
            <th style="width: 9%;">Sell Price</th>
            @foreach($warehouses as $warehouse)
                <th style="width: 7%;">{{ $warehouse->name }}</th>
            @endforeach
            <th style="width: 7%;">Total Qty</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $row)
        <tr>
            <td class="text-center">{{ $i + 1 }}</td>
            <td>{{ $row['code'] }}</td>
            <td>{{ $row['short_name'] }}</td>
            <td>{{ $row['description'] }}</td>
            <td class="text-right">
                @if($row['sell_price'] !== null)
                    {{ number_format($row['sell_price'], 2) }}
                @else
                    <span class="no-price">—</span>
                @endif
            </td>
            @foreach($row['warehouse_quantities'] as $wq)
                <td class="text-right">{{ number_format($wq['quantity'], 2) }}</td>
            @endforeach
            <td class="text-right"><strong>{{ number_format($row['total_quantity'], 2) }}</strong></td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="5" class="text-right">Total</td>
            @foreach($warehouses as $wh)
                @php $whTotal = $rows->sum(fn($r) => collect($r['warehouse_quantities'])->firstWhere('warehouse_id', $wh->id)['quantity'] ?? 0); @endphp
                <td class="text-right">{{ number_format($whTotal, 2) }}</td>
            @endforeach
            <td class="text-right">{{ number_format($rows->sum('total_quantity'), 2) }}</td>
        </tr>
    </tfoot>
</table>

</body>
</html>
