<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Purchase {{ $purchase->prefix }}-{{ $purchase->code }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #000000;
        }
        .invoice-container { width: 100%; }
        .header-row {
            width: 100%;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #000000;
        }
        .header-left { width: 40%; float: left; }
        .header-center { width: 30%; float: left; text-align: center; }
        .header-right { width: 30%; float: right; }
        .company-logo { max-height: 80px; max-width: 200px; }
        .company-name { font-size: 18pt; font-weight: bold; }
        .invoice-title { font-size: 16pt; font-weight: bold; margin-bottom: 5px; }
        .invoice-code { font-size: 12pt; font-weight: bold; }
        .info-section { width: 100%; margin-bottom: 15px; margin-top: 15px; }
        .info-table { width: 50%; font-size: 9pt; border-collapse: collapse; }
        .info-table td { padding: 3px 8px; vertical-align: top; }
        .info-label { font-weight: bold; width: 120px; white-space: nowrap; }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9pt;
        }
        .items-table th {
            background-color: #E0E0E0;
            border: 1px solid #000000;
            padding: 5px 4px;
            font-weight: bold;
            text-align: center;
        }
        .items-table td { border: 1px solid #000000; padding: 4px; }
        .items-table tr:nth-child(even) { background-color: #F5F5F5; }
        .items-table thead { display: table-row-group; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .clearfix::after { content: ""; display: table; clear: both; }
    </style>
</head>
<body>
<div class="invoice-container">

    {{-- Header --}}
    <div class="header-row clearfix">
        <div class="header-left">
            @if(!empty($company['show_logo']) && $company['show_logo'] && !empty($company['logo']) && !empty($company['logo']['exists']))
                <img src="{{ $company['logo']['path'] ?? '' }}"
                     alt="{{ $company['name'] ?? 'Company Logo' }}"
                     class="company-logo"
                     style="height: {{ $company['logo_height'] ?? '80' }}px; width: {{ $company['logo_width'] ?? '200' }}px;">
            @elseif(!empty($company['name']))
                <div class="company-name">{{ $company['name'] }}</div>
            @endif
        </div>

        <div class="header-center">
            <div class="invoice-title">Purchase Order</div>
            <div class="invoice-code">{{ $purchase->prefix }}-{{ $purchase->code }}</div>
        </div>

        <div class="header-right"></div>
    </div>

    {{-- Purchase Details --}}
    <div class="info-section">
        <table class="info-table">
            <tr>
                <td class="info-label">Purchase Code:</td>
                <td>{{ $purchase->prefix }}-{{ $purchase->code }}</td>
            </tr>
            <tr>
                <td class="info-label">Date:</td>
                <td>{{ $purchase->date->format('Y-m-d') }}</td>
            </tr>
        </table>
    </div>

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th style="width: 20%;">Item Code</th>
                <th style="width: 54%; text-align: left; padding-left: 6px;">Description</th>
                <th style="width: 20%;">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $item->item_code ?? '' }}</td>
                <td style="padding-left: 6px;">{{ $item->item->description ?? '' }}</td>
                <td class="text-center">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

</div>
</body>
</html>
