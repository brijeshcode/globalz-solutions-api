<!DOCTYPE html>
<html dir="{{ __('invoice.direction') }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('invoice.title') }} {{ $sale->prefix }}-{{ $sale->code }}</title>
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
        .header-right { width: 30%; float: right; text-align: right; }
        .company-logo { max-height: 80px; max-width: 200px; }
        .company-name { font-size: 18pt; font-weight: bold; }
        .invoice-title { font-size: 16pt; font-weight: bold; margin-bottom: 2px; }
        .invoice-code { font-size: 12pt; font-weight: bold; }
        .ice-number { font-size: 10pt; font-weight: bold; margin-top: 6px; }
        .info-section { width: 100%; margin-bottom: 15px; margin-top: 15px; }
        .info-left { width: 65%; float: left; }
        .info-right { width: 30%; float: right; }
        .info-title {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 5px;
            border-bottom: 1px solid #000000;
            padding-bottom: 2px;
        }
        .info-table { width: 100%; font-size: 9pt; }
        .info-table td { padding: 2px 0; vertical-align: top; }
        .info-label { font-weight: bold; width: 100px; }
        .outstanding-balance { color: #CC0000; font-weight: bold; }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8pt;
        }
        .items-table th {
            background-color: #E0E0E0;
            border: 1px solid #000000;
            padding: 4px 2px;
            font-weight: bold;
            text-align: center;
        }
        .items-table td { border: 1px solid #000000; padding: 4px 2px; }
        .items-table tr:nth-child(even) { background-color: #F5F5F5; }
        .items-table thead { display: table-row-group; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .totals-row td { background-color: white !important; }
        .totals-row.first-total { border-top: 2px solid #000000; }
        .company-stamp {
            position: absolute;
            max-width: 150px;
            max-height: 150px;
            opacity: 0.7;
        }
        .signature-section { width: 100%; margin-top: 20px; margin-bottom: 15px; }
        .signature-box { width: 45%; float: left; text-align: center; margin-right: 5%; }
        .signature-line { border-top: 1px solid #000000; margin-top: 45px; padding-top: 5px; }
        .payment-note { text-align: center; font-size: 9pt; margin-top: 20px; }
        .clearfix::after { content: ""; display: table; clear: both; }
    </style>
</head>
<body>
    <div class="invoice-container">

        {{-- Template-2: bilingual title + ICE number shown below logo --}}
        <div class="header-row clearfix">
            <div class="header-left">
                @if($sale->prefix === 'INV')
                    @if(!empty($company['show_logo']) && $company['show_logo'] && !empty($company['logo']) && !empty($company['logo']['exists']))
                        <img src="{{ $company['logo']['path'] ?? '' }}"
                             alt="{{ $company['name'] ?? 'Company Logo' }}"
                             class="company-logo"
                             style="height: {{ $company['logo_height'] ?? '80' }}px; width: {{ $company['logo_width'] ?? '200' }}px;">
                    @elseif(!empty($company['name']))
                        <div class="company-name">{{ $company['name'] }}</div>
                    @endif

                    @if(!empty($company['tax_number']))
                        <div class="ice-number">{{ __('invoice.label_tax') }} {{ $company['tax_number'] }}</div>
                    @endif
                @endif
            </div>

            <div class="header-center">
                <div class="invoice-title">{{ __('invoice.title') }}</div>
                <div class="invoice-code">{{ $sale->prefix }}-{{ $sale->code }}</div>
            </div>

            <div class="header-right">
                @if(!empty($qrCodeBase64))
                    <div style="font-size: 8pt; margin-bottom: 2px;">{{ __('invoice.google_map') }}</div>
                    <img src="data:image/png;base64,{{ $qrCodeBase64 }}"
                         style="width: 70px; height: 70px;"
                         alt="Customer Location">
                @endif
            </div>
        </div>

        <div class="info-section clearfix">
            @include('pdfs.partials.customer-info')
            @include('pdfs.partials.invoice-details')
        </div>

        @include('pdfs.partials.items-table-template-2')
        @include('pdfs.partials.totals-template-2')
        @include('pdfs.partials.signature')
        @include('pdfs.partials.footer')

    </div>
</body>
</html>
