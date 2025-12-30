<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Sales Invoice {{ $sale->prefix }}-{{ $sale->code }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #000000;
        }

        .invoice-container {
            width: 100%;
        }

        /* Header Styles */
        .header-row {
            width: 100%;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #000000;
        }

        .header-left {
            width: 50%;
            float: left;
        }

        .header-right {
            width: 50%;
            float: right;
            text-align: right;
        }

        .company-logo {
            max-height: 80px;
            max-width: 200px;
        }

        .company-name {
            font-size: 18pt;
            font-weight: bold;
        }

        .invoice-title {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .invoice-code {
            font-size: 12pt;
            font-weight: bold;
        }

        /* Info Section */
        .info-section {
            width: 100%;
            margin-bottom: 15px;
            margin-top: 15px;
        }

        .info-left {
            width: 65%;
            float: left;
        }

        .info-right {
            width: 30%;
            float: right;
        }

        .info-title {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 5px;
            border-bottom: 1px solid #000000;
            padding-bottom: 2px;
        }

        .info-table {
            width: 100%;
            font-size: 9pt;
        }

        .info-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .info-label {
            font-weight: bold;
            width: 100px;
        }

        .outstanding-balance {
            color: #CC0000;
            font-weight: bold;
        }

        /* Items Table */
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

        .items-table td {
            border: 1px solid #000000;
            padding: 4px 2px;
        }

        .items-table tr:nth-child(even) {
            background-color: #F5F5F5;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .font-bold {
            font-weight: bold;
        }

        /* Totals */
        .totals-row td {
            background-color: white !important;
        }

        .totals-row.first-total {
            border-top: 2px solid #000000;
        }

        .company-stamp {
            position: absolute;
            max-width: 150px;
            max-height: 150px;
            opacity: 0.7;
        }

        /* Signature Section */
        .signature-section {
            width: 100%;
            margin-top: 20px;
            margin-bottom: 15px;
        }

        .signature-box {
            width: 45%;
            float: left;
            text-align: center;
            margin-right: 5%;
        }

        .signature-line {
            border-top: 1px solid #000000;
            margin-top: 30px;
            padding-top: 5px;
        }

        /* Footer */
        .footer {
            width: 100%;
            border-top: 2px solid #000000;
            padding-top: 5px;
            margin-top: 30px;
            font-size: 8pt;
        }

        .footer-info {
            text-align: center;
        }

        .payment-note {
            text-align: center;
            font-size: 9pt;
            margin-top: 20px;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header Section -->
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
                        <div style="margin-top: 5px;">
                            <strong>Tax Registration #: {{ $company['tax_number'] }}</strong>
                        </div>
                    @endif
                @endif
            </div>

            <div class="header-right">
                <div class="invoice-title">SALES INVOICE</div>
                <div class="invoice-code">{{ $sale->prefix }}-{{ $sale->code }}</div>
            </div>
        </div>

        <!-- Customer and Invoice Info -->
        <div class="info-section clearfix">
            <div class="info-left">
                <div class="info-title">Customer Information</div>
                <table class="info-table">
                    <tr>
                        <td class="info-label">Customer:</td>
                        <td>{{ $sale->customer->code ?? '' }}, {{ $sale->customer->name ?? 'Unknown Customer' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Address:</td>
                        <td>{{ $sale->customer->address ?? '' }}, {{ $sale->customer->city ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Phone:</td>
                        <td>{{ $sale->customer->mobile ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Tax #:</td>
                        <td>{{ $sale->customer->mof_tax_number ?? '' }}</td>
                    </tr>
                    @if(!empty($sale->outStanding_balance) && $sale->outStanding_balance > 0)
                    <tr>
                        <td class="info-label">Outstanding Balance:</td>
                        <td class="outstanding-balance">{{ number_format($sale->outStanding_balance, 2) }}</td>
                    </tr>
                    @endif
                </table>
            </div>

            <div class="info-right">
                <div class="info-title">Invoice Details</div>
                <table class="info-table">
                    <tr>
                        <td class="info-label">Date:</td>
                        <td>{{ $sale->date->format('Y-m-d') }}</td>
                    </tr>
                    @if($sale->value_date)
                    <tr>
                        <td class="info-label">Value Date:</td>
                        <td>{{ $sale->value_date->format('Y-m-d') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="info-label">Currency:</td>
                        <td>{{ $sale->currency->code ?? 'N/A' }}</td>
                    </tr>
                    @if($sale->salesperson)
                    <tr>
                        <td class="info-label">Salesperson:</td>
                        <td>{{ $sale->salesperson->name ?? 'Not assigned' }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 11%;">Item Code</th>
                    <th style="width: 25%;">Description</th>
                    <th style="width: 9%;">Price</th>
                    <th style="width: 7%;">Dis. %</th>
                    <th style="width: 14%;">QTY</th>
                    @if($sale->prefix === 'INV')
                    <th style="width: 8%;">Tax</th>
                    @endif
                    <th style="width: 12%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <!-- Actual Items -->
                @foreach($sale->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->item_code ?? '' }}</td>
                    <td>{{ $item->item->description ?? 'Unknown Item' }}</td>
                    <td class="text-center">{{ number_format($item->price, 2) }}</td>
                    <td class="text-center">{{ number_format($item->discount_percent, 2) }}%</td>
                    <td class="text-center">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    @if($sale->prefix === 'INV')
                    <td class="text-center">{{ $item->tax_label ?? '' }}</td>
                    @endif
                    <td class="text-right font-bold">{{ number_format($item->total_net_sell_price, 2) }}</td>
                </tr>
                @endforeach

                <!-- Empty Rows -->
                @php
                    $itemsCount = count($sale->items);
                    $minRows = 20;
                    $emptyRows = $itemsCount < $minRows ? $minRows - $itemsCount : 0;
                @endphp

                @for($i = 0; $i < $emptyRows; $i++)
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    @if($sale->prefix === 'INV')
                    <td>&nbsp;</td>
                    @endif
                    <td>&nbsp;</td>
                </tr>
                @endfor

                <!-- Totals Section -->
                <tr class="totals-row first-total">
                    <td colspan="{{ $sale->prefix === 'INV' ? 4 : 3 }}" style="border: none;">
                        <div style="font-size: 8pt;">
                            <strong>Volume CBM:</strong> {{ number_format($totalVolume, 2) }}
                        </div>
                    </td>
                    <td colspan="2" style="border: none;"></td>
                    <td class="font-bold">Sub Total</td>
                    <td class="text-right font-bold">{{ number_format($calculatedSubTotal, 2) }}</td>
                </tr>

                <tr class="totals-row">
                    <td colspan="{{ $sale->prefix === 'INV' ? 6 : 5 }}" style="position: relative; border: none;">
                        <div style="font-size: 8pt;">
                            <strong>Weight KG:</strong> {{ number_format($totalWeight, 2) }}
                        </div>

                        @if($sale->prefix === 'INV' && !empty($company['show_stamp']) && $company['show_stamp'] && !empty($company['stamp']) && !empty($company['stamp']['exists']))
                            <img src="{{ $company['stamp']['path'] ?? '' }}"
                                 alt="{{ $company['name'] ?? 'Company Stamp' }}"
                                 class="company-stamp"
                                 style="height: {{ $company['stamp_height'] ?? '150' }}px; width: {{ $company['stamp_width'] ?? '150' }}px; position: absolute; left: 35%; top: -50%;">
                        @endif
                    </td>
                    <td class="font-bold">Add. Discount</td>
                    <td class="text-right font-bold">{{ number_format($sale->discount_amount, 2) }}</td>
                </tr>

                @if($sale->prefix === 'INV')
                <tr class="totals-row">
                    <td colspan="3" style="border: none;"></td>
                    <td colspan="2" class="font-bold">TVA 11% LL</td>
                    <td class="text-right font-bold">{{ number_format($sale->total_tax_amount_usd * ($sale->local_curreny_rate > 0 ? $sale->local_curreny_rate : 1), 2) }}</td>
                    <td class="font-bold">{{ $sale->invoice_tax_label }}</td>
                    <td class="text-right font-bold">{{ number_format($sale->total_tax_amount, 2) }}</td>
                </tr>
                @endif

                <tr class="totals-row">
                    <td colspan="3" style="border: none;"></td>
                    <td colspan="{{ $sale->prefix === 'INV' ? 2 : 1 }}" class="font-bold">Net Total LL</td>
                    <td class="text-right font-bold">{{ number_format($sale->total_usd * ($sale->local_curreny_rate > 0 ? $sale->local_curreny_rate : 1), 2) }}</td>
                    <td class="font-bold">Net Total</td>
                    <td class="text-right font-bold" style="font-size: 10pt;">{{ number_format($sale->total, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Signature Section -->
        <div class="signature-section clearfix">
            <div class="signature-box">
                <div><strong>Received By</strong></div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-box">
                <div><strong>Signature</strong></div>
                <div class="signature-line"></div>
            </div>
        </div>

        <!-- Payment Note -->
        <div class="payment-note">
            @if(!empty($sale->invoice_nb1))
                {{ $sale->invoice_nb1 }}<br><br>
            @endif
            @if(!empty($sale->invoice_nb2))
                {{ $sale->invoice_nb2 }}
            @endif
        </div>

        <!-- Footer -->
        @if($sale->prefix === 'INV')
        <div class="footer">
            <div class="footer-info">
                @if(!empty($company['address']))
                    {{ $company['address'] }} |
                @endif
                @if(!empty($company['phone']))
                    {{ $company['phone'] }} |
                @endif
                @if(!empty($company['email']))
                    {{ $company['email'] }} |
                @endif
                @if(!empty($company['website']))
                    {{ $company['website'] }}
                @endif
            </div>
        </div>
        @endif
    </div>
</body>
</html>
