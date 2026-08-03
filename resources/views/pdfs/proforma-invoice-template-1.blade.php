<!DOCTYPE html>
<html dir="{{ __('invoice.direction') }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('invoice.proforma_title') }} {{ $proforma->prefix }}-{{ $proforma->code }}</title>
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
        .invoice-title { font-size: 16pt; font-weight: bold; margin-bottom: 5px; }
        .invoice-code { font-size: 12pt; font-weight: bold; }
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
        .signature-section { width: 100%; margin-top: 20px; margin-bottom: 15px; }
        .signature-box { width: 45%; float: left; text-align: center; margin-right: 5%; }
        .signature-line { border-top: 1px solid #000000; margin-top: 45px; padding-top: 5px; }
        .payment-note { text-align: center; font-size: 9pt; margin-top: 20px; }
        .clearfix::after { content: ""; display: table; clear: both; }
    </style>
</head>
<body>
<div class="invoice-container">

    {{-- Header --}}
    <div class="header-row clearfix">
        <div class="header-left">
            @if($proforma->prefix === 'PINV')
                @if(!empty($company['show_logo']) && $company['show_logo'] && !empty($company['logo']) && !empty($company['logo']['exists']))
                    <img src="{{ $company['logo']['path'] ?? '' }}"
                         alt="{{ $company['name'] ?? 'Company Logo' }}"
                         class="company-logo"
                         style="height: {{ $company['logo_height'] ?? '80' }}px; width: {{ $company['logo_width'] ?? '200' }}px;">
                @elseif(!empty($company['name']))
                    <div class="company-name">{{ $company['name'] }}</div>
                @endif
                @if(!empty($company['tax_number']))
                    <div style="margin-top: 5px;"><strong>{{ $company['tax_number'] }}</strong></div>
                @endif
            @endif
        </div>

        <div class="header-center">
            <div class="invoice-title">{{ __('invoice.proforma_title') }}</div>
            <div class="invoice-code">{{ $proforma->prefix }}-{{ $proforma->code }}</div>
        </div>

        <div class="header-right"></div>
    </div>

    {{-- Customer + Invoice Details --}}
    <div class="info-section clearfix">
        <div class="info-left">
            <div class="info-title">{{ __('invoice.customer_info') }}</div>
            <table class="info-table">
                <tr>
                    <td class="info-label">{{ __('invoice.label_customer') }}:</td>
                    <td>{{ ($proforma->customer->code ?? '') . ', ' . ($proforma->customer->name ?? '') }}</td>
                </tr>
                <tr>
                    <td class="info-label">{{ __('invoice.label_address') }}:</td>
                    <td>{{ $proforma->customer->address ?? '' }}, {{ $proforma->customer->city ?? '' }}</td>
                </tr>
                <tr>
                    <td class="info-label">{{ __('invoice.label_phone') }}:</td>
                    <td>{{ $proforma->customer->mobile ?? '' }}</td>
                </tr>
                @if(!empty($proforma->customer->mof_tax_number))
                <tr>
                    <td class="info-label">{{ __('invoice.label_tax') }}</td>
                    <td>{{ $proforma->customer->mof_tax_number }}</td>
                </tr>
                @endif
            </table>
        </div>

        <div class="info-right">
            <div class="info-title">{{ __('invoice.invoice_details') }}</div>
            <table class="info-table">
                <tr>
                    <td class="info-label">{{ __('invoice.label_date') }}:</td>
                    <td>{{ $proforma->date->format('Y-m-d') }}</td>
                </tr>
                @if($proforma->value_date)
                <tr>
                    <td class="info-label">{{ __('invoice.label_value_date') }}:</td>
                    <td>{{ $proforma->value_date->format('Y-m-d') }}</td>
                </tr>
                @endif
                <tr>
                    <td class="info-label">{{ __('invoice.label_currency') }}:</td>
                    <td>{{ $proforma->currency->code ?? 'N/A' }}</td>
                </tr>
                @if($proforma->salesperson)
                <tr>
                    <td class="info-label">{{ __('invoice.label_salesperson') }}:</td>
                    <td>{{ $proforma->salesperson->name ?? '' }}</td>
                </tr>
                @endif
                @if($proforma->client_po_number)
                <tr>
                    <td class="info-label">{{ __('invoice.label_po_number') }}:</td>
                    <td>{{ $proforma->client_po_number }}</td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">{{ __('invoice.col_num') }}</th>
                <th style="width: 11%;">{{ __('invoice.col_item_code') }}</th>
                <th style="width: 32%;">{{ __('invoice.col_description') }}</th>
                <th style="width: 9%;">{{ __('invoice.col_price') }}</th>
                <th style="width: 7%;">{{ __('invoice.col_discount') }}</th>
                <th style="width: 9%;">{{ __('invoice.col_qty') }}</th>
                <th style="width: 12%;">{{ __('invoice.col_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($proforma->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $item->item_code ?? '' }}</td>
                <td>{{ $item->item->description ?? 'Unknown Item' }}</td>
                <td class="text-center">{{ number_format($item->price, $invoiceSettings['unit_price_decimals'] ?? 2) }}</td>
                <td class="text-center">{{ number_format($item->discount_percent, 2) }}%</td>
                <td class="text-center">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                <td class="text-right font-bold">{{ number_format($item->total_net_sell_price, $invoiceSettings['total_decimals'] ?? 2) }}</td>
            </tr>
            @endforeach

            @php
                $itemsCount = count($proforma->items);
                $minRows    = 15;
                $emptyRows  = $itemsCount < $minRows ? $minRows - $itemsCount : 0;
            @endphp

            @for($i = 0; $i < $emptyRows; $i++)
            <tr>
                <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
            </tr>
            @endfor
        </tbody>
    </table>

    {{-- Totals --}}
    <table class="items-table" style="margin-top: -1px;">
        <tbody>
            <tr class="totals-row first-total">
                <td style="width: 45%; vertical-align: top; border: none;">
                    <div style="font-size: 8pt;">
                        <strong>{{ __('invoice.volume_cbm') }}:</strong> {{ number_format($totalVolume, 2) }}
                    </div>
                    <div style="font-size: 8pt;">
                        <strong>{{ __('invoice.weight_kg') }}:</strong> {{ number_format($totalWeight, 2) }}
                    </div>
                </td>

                <td style="width: 27%; text-align: center; vertical-align: middle; border: none;">
                    @if($proforma->prefix === 'PINV' && !empty($company['show_stamp']) && $company['show_stamp'] && !empty($company['stamp']) && !empty($company['stamp']['exists']))
                        @php $stampW = $company['stamp_width'] ?? 150; $stampH = $company['stamp_height'] ?? 150; @endphp
                        <img src="{{ $company['stamp']['path'] ?? '' }}"
                             alt="{{ $company['name'] ?? 'Company Stamp' }}"
                             style="height: {{ $stampH }}px; width: {{ $stampW }}px; opacity: 0.7;">
                    @endif
                </td>

                <td style="width: 28%; border: none; padding: 0; vertical-align: top;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td colspan="2" style="border: none; width: 50%;">&nbsp;</td>
                            <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap; width: 28%;">{{ __('invoice.sub_total') }}</td>
                            <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; width: 22%;">{{ number_format($proforma->sub_total, $invoiceSettings['total_decimals'] ?? 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="border: none; width: 50%;">&nbsp;</td>
                            <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap; width: 28%;">{{ __('invoice.amount_discount') }}</td>
                            <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; width: 22%;">{{ number_format($proforma->discount_amount, $invoiceSettings['total_decimals'] ?? 2) }}</td>
                        </tr>
                        @if($proforma->prefix === 'PINV' && $proforma->total_tax_amount > 0)
                        <tr>
                            <td colspan="2" style="border: none; width: 50%;">&nbsp;</td>
                            <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap; width: 28%;">{{ $proforma->invoice_tax_label }}</td>
                            <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; width: 22%;">{{ number_format($proforma->total_tax_amount, $invoiceSettings['total_decimals'] ?? 2) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td colspan="2" style="border: none; width: 50%;">&nbsp;</td>
                            <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap; width: 28%;">{{ __('invoice.net_total') }}</td>
                            <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; font-size: 10pt; width: 22%;">{{ number_format($proforma->total, $invoiceSettings['total_decimals'] ?? 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Signature --}}
    <div class="signature-section clearfix" style="display: flex;">
        <div class="signature-box">
            <div><strong>{{ __('invoice.received_by') }}</strong></div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-box">
            <div><strong>{{ __('invoice.signature') }}</strong></div>
            <div class="signature-line"></div>
        </div>
    </div>

    {{-- Footer Notes --}}
    <div class="payment-note">
        @if(!empty($proforma->invoice_nb1) && ($invoiceSettings['show_note_1'] ?? true))
            {{ $proforma->invoice_nb1 }}<br><br>
        @endif
        @if(!empty($proforma->invoice_nb2) && ($invoiceSettings['show_note_2'] ?? true))
            {{ $proforma->invoice_nb2 }}
        @endif
    </div>

</div>
</body>
</html>
