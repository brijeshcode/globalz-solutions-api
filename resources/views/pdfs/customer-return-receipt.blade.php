<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Return Receipt {{ $customerReturn->prefix }}{{ $customerReturn->code }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #111111;
            margin: 0;
            padding: 0;
        }
        .clearfix::after { content: ""; display: table; clear: both; }

        /* ── Header ── */
        .header-left  { width: 50%; float: left; }
        .header-right { width: 50%; float: right; text-align: right; }
        .company-logo { max-height: 70px; max-width: 200px; }
        .company-name { font-size: 16pt; font-weight: bold; color: #1e40af; }
        .tax-number   { font-size: 8pt; font-weight: bold; }

        .header-divider {
            border: none;
            border-top: 2px solid #1e40af;
            margin: 10px 0 14px 0;
        }
        .doc-title {
            font-size: 18pt;
            font-weight: bold;
            color: #1e40af;
            margin: 0;
        }
        .return-code {
            font-size: 11pt;
            font-weight: bold;
            color: #444444;
            margin-top: 2px;
        }

        /* ── Two-column info grid ── */
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .info-grid td { width: 50%; vertical-align: top; padding: 0; }
        .info-col-left  { padding-right: 12px; }
        .info-col-right { padding-left: 12px; }

        .info-section-title {
            font-size: 9pt;
            font-weight: bold;
            color: #1e40af;
            border-bottom: 1px solid #cccccc;
            padding-bottom: 3px;
            margin-bottom: 6px;
        }
        .info-row { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        .info-row td { padding: 1px 0; font-size: 8pt; }
        .info-label { color: #555555; font-weight: bold; width: 110px; }
        .info-value { color: #111111; }

        /* ── Currency info box ── */
        .currency-box {
            border: 1px solid #bfdbfe;
            background-color: #eff6ff;
            padding: 8px 10px;
            margin-bottom: 12px;
        }
        .currency-grid { width: 100%; border-collapse: collapse; }
        .currency-grid td { width: 50%; vertical-align: top; padding: 0 6px; font-size: 8pt; }
        .currency-section-title { font-size: 8pt; font-weight: bold; color: #444444; margin-bottom: 4px; }
        .currency-row { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        .currency-row td { padding: 1px 0; font-size: 8pt; }
        .currency-label { color: #555555; }
        .currency-value { text-align: right; font-weight: bold; color: #111111; }
        .currency-total-value { font-size: 10pt; font-weight: bold; color: #111111; text-align: right; }

        /* ── Items table ── */
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #111111;
            border-bottom: 1px solid #cccccc;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .items-table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 14px; }
        .items-table thead tr th {
            background-color: #f9fafb;
            color: #555555;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 6px;
            border: 1px solid #111111;
            text-align: left;
        }
        .items-table thead tr th.text-right { text-align: right; }
        .items-table tbody tr td {
            padding: 4px 6px;
            border: 1px solid #111111;
            font-size: 8pt;
            vertical-align: top;
        }
        .items-table tfoot tr td {
            padding: 4px 6px;
            border: 1px solid #111111;
            font-size: 8pt;
            font-weight: bold;
            background-color: #f9fafb;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* ── Notes ── */
        .notes-box {
            border: 1px solid #111111;
            padding: 6px 8px;
            font-size: 8pt;
            color: #111111;
            margin-bottom: 12px;
        }

        /* ── Approval / Receipt info ── */
        .meta-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .meta-grid td { width: 50%; vertical-align: top; padding: 0; }
        .meta-section-title {
            font-size: 8pt;
            font-weight: bold;
            color: #111111;
            border-bottom: 2px solid #555555;
            padding-bottom: 2px;
            margin-bottom: 4px;
            width: 100%;
            display: block;
        }
        .meta-row { width: 100%; border-collapse: collapse; margin-bottom: 1px; }
        .meta-row td { padding: 1px 0; font-size: 7.5pt; }
        .meta-label { color: #555555; }
        .meta-value { text-align: left; font-weight: bold; color: #111111; }

        /* ── Signatures ── */
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 40px; }
        .sig-table td { width: 50%; text-align: center; padding: 0 40px; }

    </style>
</head>
<body>

    {{-- ── Header ── --}}
    <div class="clearfix">
        <div class="header-left">
            @if(!empty($company['logo']) && !empty($company['logo']['exists']))
                <img src="{{ $company['logo']['path'] }}" alt="{{ $company['name'] ?? '' }}" class="company-logo">
            @elseif(!empty($company['name']))
                <div class="company-name">{{ $company['name'] }}</div>
            @endif
            @if(!empty($company['tax_number']))
                <div class="tax-number">{{ $company['tax_number'] }}</div>
            @endif
        </div>

        <div class="header-right">
            <div class="doc-title">RETURN RECEIPT</div>
            <div class="return-code">{{ $customerReturn->prefix }}{{ $customerReturn->code }}</div>
        </div>
    </div>

    <hr class="header-divider">

    {{-- ── Customer & Return Info Grid ── --}}
    <table class="info-grid">
        <tr>
            <td class="info-col-left">
                <div class="info-section-title">Customer Information</div>
                <table class="info-row">
                    <tr>
                        <td class="info-label">Customer:</td>
                        <td class="info-value">{{ $customerReturn->customer->name ?? '-' }}</td>
                    </tr>
                    @if($customerReturn->customer->code)
                    <tr>
                        <td class="info-label">Customer Code:</td>
                        <td class="info-value">{{ $customerReturn->customer->code }}</td>
                    </tr>
                    @endif
                    @if($customerReturn->salesperson)
                    <tr>
                        <td class="info-label">Salesperson:</td>
                        <td class="info-value">{{ $customerReturn->salesperson->name }}</td>
                    </tr>
                    @endif
                </table>
            </td>
            <td class="info-col-right">
                <div class="info-section-title">Return Details</div>
                <table class="info-row">
                    <tr>
                        <td class="info-label">Return Date:</td>
                        <td class="info-value">{{ $customerReturn->date->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Print Date:</td>
                        <td class="info-value">{{ now()->format('d/m/Y') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── Currency Info (only when multi-currency) ── --}}
    @if($customerReturn->currency && $customerReturn->currency->code !== 'USD')
    <div class="currency-box">
        <table class="currency-grid">
            <tr>
                <td style="border-right: 1px solid #bfdbfe;">
                    <div class="currency-section-title">Currency Information</div>
                    <table class="currency-row">
                        <tr>
                            <td class="currency-label">Currency:</td>
                            <td class="currency-value">{{ $customerReturn->currency->name }} ({{ $customerReturn->currency->code }})</td>
                        </tr>
                        @if($customerReturn->currency_rate)
                        <tr>
                            <td class="currency-label">Currency Rate:</td>
                            <td class="currency-value">{{ number_format($customerReturn->currency_rate, 4) }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
                <td style="padding-left: 14px;">
                    <div class="currency-section-title">Return Summary</div>
                    <table class="currency-row">
                        <tr>
                            <td class="currency-label">Total Items:</td>
                            <td class="currency-value">{{ $customerReturn->items->count() }}</td>
                        </tr>
                        <tr>
                            <td class="currency-label">Total ({{ $customerReturn->currency->code }}):</td>
                            <td class="currency-total-value">{{ $customerReturn->currency->symbol }} {{ number_format($customerReturn->total, 2) }}</td>
                        </tr>
                        @if($customerReturn->total_usd)
                        <tr>
                            <td class="currency-label">Total (USD):</td>
                            <td class="currency-value">$ {{ number_format($customerReturn->total_usd, 2) }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>
    @endif

    {{-- ── Return Items Table ── --}}
    <div class="section-title">Return Items</div>
    @php $isTax = $customerReturn->prefix === 'RTN'; @endphp
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:4%;">#</th>
                <th style="width:10%;">Item Code</th>
                @if($isTax)
                <th style="width:30%;">Description</th>
                @else
                <th style="width:34%;">Description</th>
                @endif
                <th class="text-right" style="width:9%;">Qty</th>
                <th class="text-right" style="width:12%;">Price</th>
                <th class="text-right" style="width:10%;">Disc %</th>
                @if($isTax)
                <th class="text-right" style="width:10%;">Tax %</th>
                @endif
                @if($isTax)
                <th class="text-right" style="width:15%;">Total</th>
                @else
                <th class="text-right" style="width:21%;">Total</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($customerReturn->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->item->code ?? $item->item_code ?? '-' }}</td>
                <td>{{ $item->item->description ?? $item->item->short_name ?? '-' }}</td>
                <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                <td class="text-right">{{ number_format($item->price ?? $item->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($item->discount_percent ?? 0, 2) }}%</td>
                @if($isTax)
                <td class="text-right">{{ number_format($item->tax_percent ?? $item->item->taxCode->tax_percent ?? 0, 2) }}%</td>
                @endif
                <td class="text-right">{{ number_format($item->total_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="{{ $isTax ? 7 : 6 }}" class="text-right">Total:</td>
                <td class="text-right">
                    {{ $customerReturn->currency->symbol ?? '' }} {{ number_format($customerReturn->total, 2) }}
                </td>
            </tr>
            @if($customerReturn->currency && $customerReturn->currency->code !== 'USD' && $customerReturn->total_usd)
            <tr>
                <td colspan="{{ $isTax ? 7 : 6 }}" class="text-right">Total (USD):</td>
                <td class="text-right">$ {{ number_format($customerReturn->total_usd, 2) }}</td>
            </tr>
            @endif
        </tfoot>
    </table>

    {{-- ── Notes ── --}}
    @if($customerReturn->note)
    <div class="section-title">Notes</div>
    <div class="notes-box">{{ $customerReturn->note }}</div>
    @endif

    {{-- ── Approval & Receipt Info ── --}}
    @if($customerReturn->approvedBy || $customerReturn->returnReceivedBy)
    <table class="meta-grid">
        <tr>
            @if($customerReturn->approvedBy)
            <td style="padding-right: 12px;">
                <div class="meta-section-title">Approval Information</div>
                <table class="meta-row">
                    <tr>
                        <td class="meta-label">Approved By:</td>
                        <td class="meta-value">{{ $customerReturn->approvedBy->name }}</td>
                    </tr>
                    @if($customerReturn->approved_at)
                    <tr>
                        <td class="meta-label">Approved At:</td>
                        <td class="meta-value">{{ $customerReturn->approved_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @endif
                </table>
            </td>
            @endif
            @if($customerReturn->returnReceivedBy)
            <td style="padding-left: 12px;">
                <div class="meta-section-title">Receipt Information</div>
                <table class="meta-row">
                    <tr>
                        <td class="meta-label">Received By:</td>
                        <td class="meta-value">{{ $customerReturn->returnReceivedBy->name }}</td>
                    </tr>
                    @if($customerReturn->return_received_at)
                    <tr>
                        <td class="meta-label">Received At:</td>
                        <td class="meta-value">{{ \Carbon\Carbon::parse($customerReturn->return_received_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                    @endif
                </table>
            </td>
            @endif
        </tr>
    </table>
    @endif

    {{-- ── Signatures ── --}}
    <table class="sig-table">
        <tr>
            <td style="font-size: 7.5pt; color: #444444; font-weight: bold; padding-bottom: 0; padding-right: 40px;">Returned By</td>
            <td style="font-size: 7.5pt; color: #444444; font-weight: bold; padding-bottom: 0; padding-left: 40px;">Received By</td>
        </tr>
        <tr>
            <td style="height: 60px; border-bottom: 1.5px solid #555555; padding-right: 40px;"></td>
            <td style="height: 60px; border-bottom: 1.5px solid #555555; padding-left: 40px;"></td>
        </tr>
        <tr>
            <td style="padding-top: 4px; font-size: 7pt; color: #888888; padding-right: 40px;">Signature &amp; Date</td>
            <td style="padding-top: 4px; font-size: 7pt; color: #888888; padding-left: 40px;">Signature &amp; Date</td>
        </tr>
    </table>


</body>
</html>
