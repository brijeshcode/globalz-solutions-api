<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Customer Statement - {{ $customer->code }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #000000;
        }
        .container { width: 100%; }
        .header-row {
            width: 100%;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #000000;
        }
        .header-left { width: 50%; float: left; }
        .header-right { width: 50%; float: right; }
        .company-logo { max-height: 80px; max-width: 200px; }
        .company-name { font-size: 18pt; font-weight: bold; }
        .statement-title { font-size: 16pt; font-weight: bold; margin-bottom: 5px; }
        .customer-code-heading { font-size: 12pt; font-weight: bold; }
        .info-section { width: 100%; margin-bottom: 15px; margin-top: 15px; }
        .info-left { width: 60%; float: left; }
        .info-right { width: 35%; float: right; }
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
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8pt;
        }
        .transactions-table th {
            background-color: #E0E0E0;
            border: 1px solid #000000;
            padding: 4px 4px;
            font-weight: bold;
            text-align: center;
        }
        .transactions-table td {
            border: 1px solid #000000;
            padding: 4px 4px;
        }
        .transactions-table tr:nth-child(even) { background-color: #F5F5F5; }
        .transactions-table thead { display: table-row-group; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .balance-negative { color: #CC0000; font-weight: bold; }
        .debit-value { color: #CC0000; }
        .credit-value { color: #16a34a; }
        .total-row td { background-color: #E0E0E0 !important; font-weight: bold; border-top: 2px solid #000000; }
        .signature-section { width: 100%; margin-top: 30px; margin-bottom: 15px; }
        .signature-box { width: 45%; float: left; text-align: center; margin-right: 5%; }
        .signature-line { border-top: 1px solid #000000; margin-top: 45px; padding-top: 5px; }
        .clearfix::after { content: ""; display: table; clear: both; }
    </style>
</head>
<body>
<div class="container">

    @if($type === 'tax')

    <div class="header-row clearfix">
        <div class="header-left">
            @if($showCompanyHeader)
                @if(!empty($company['logo']) && !empty($company['logo']['exists']))
                    <img src="{{ $company['logo']['path'] }}"
                         alt="{{ $company['name'] ?? '' }}"
                         class="company-logo">
                @elseif(!empty($company['name']))
                    <div class="company-name">{{ $company['name'] }}</div>
                @endif
                @if(!empty($company['tax_number']))
                    <div style="margin-top: 5px; font-weight: bold;">{{ $company['tax_number'] }}</div>
                @endif
            @endif
        </div>
        <div class="header-right" style="text-align: right;">
            <div class="statement-title">CUSTOMER STATEMENT</div>
            <div class="customer-code-heading">{{ $customer->code }}</div>
        </div>
    </div>

    <div class="info-section clearfix">
        <div class="info-left">
            <div class="info-title">Customer Information</div>
            <table class="info-table">
                <tr>
                    <td class="info-label">Customer:</td>
                    <td>{{ $customer->code }}, {{ $customer->name }}</td>
                </tr>
                @if(!empty($customer->address) || !empty($customer->city))
                <tr>
                    <td class="info-label">Address:</td>
                    <td>{{ collect([$customer->address, $customer->city])->filter()->implode(', ') }}</td>
                </tr>
                @endif
                @if(!empty($customer->mobile))
                <tr>
                    <td class="info-label">Phone:</td>
                    <td>{{ $customer->mobile }}</td>
                </tr>
                @endif
                @if(!empty($customer->mof_tax_number))
                <tr>
                    <td class="info-label">Tax #:</td>
                    <td>{{ $customer->mof_tax_number }}</td>
                </tr>
                @endif
            </table>
        </div>
        <div class="info-right">
            <div class="info-title">Statement Period</div>
            <table class="info-table">
                @if(!empty($fromDate))
                <tr>
                    <td class="info-label">From Date:</td>
                    <td>{{ \Carbon\Carbon::parse($fromDate)->format('d/m/Y') }}</td>
                </tr>
                @endif
                <tr>
                    <td class="info-label">To Date:</td>
                    <td>{{ !empty($toDate) ? \Carbon\Carbon::parse($toDate)->format('d/m/Y') : 'Current' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Print Date:</td>
                    <td>{{ date('d/m/Y') }}</td>
                </tr>
                @if($customer->salesperson)
                <tr>
                    <td class="info-label">Sales Pers.:</td>
                    <td>{{ $customer->salesperson->name }}</td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    @else

    <div class="header-row" style="text-align: center;">
        <div class="statement-title">SOA</div>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <td class="info-label">Client:</td>
                <td>{{ $customer->name }}</td>
            </tr>
            @if(!empty($customer->address) || !empty($customer->city))
            <tr>
                <td class="info-label">Address:</td>
                <td>{{ collect([$customer->address, $customer->city])->filter()->implode(', ') }}</td>
            </tr>
            @endif
            @if(!empty($customer->mobile))
            <tr>
                <td class="info-label">Tel:</td>
                <td>{{ $customer->mobile }}</td>
            </tr>
            @endif
        </table>
    </div>

    @endif

    <table class="transactions-table">
        <thead>
            @if($type === 'tax')
            <tr>
                <th class="text-left" style="width: 12%;">Date</th>
                <th class="text-left" style="width: 12%;">Type</th>
                <th class="text-left" style="width: 13%;">Transaction Id</th>
                <th class="text-left" style="width: 20%;">Note</th>
                <th class="text-right" style="width: 14%;">Debit</th>
                <th class="text-right" style="width: 14%;">Credit</th>
                <th class="text-right" style="width: 15%;">Balance</th>
            </tr>
            @else
            <tr>
                <th class="text-left" style="width: 12%;">Date</th>
                <th class="text-left" style="width: 12%;">TRANS</th>
                <th class="text-left" style="width: 13%;">REF</th>
                <th class="text-left" style="width: 20%;">INFO</th>
                <th class="text-right" style="width: 14%;">Debit</th>
                <th class="text-right" style="width: 14%;">Credit</th>
                <th class="text-right" style="width: 15%;">Balance</th>
            </tr>
            @endif
        </thead>
        <tbody>
            @foreach($transactions as $transaction)
            <tr>
                <td style="white-space: nowrap;">{{ \Carbon\Carbon::parse($transaction['date'])->format('d/m/Y') }}</td>
                <td style="white-space: nowrap;">{{ $transaction['type'] }}</td>
                <td style="white-space: nowrap;">{{ $transaction['code'] }}</td>
                <td style="word-wrap: break-word; max-width: 160px;">{{ $transaction['note'] ?? '-' }}</td>
                <td class="text-right {{ $transaction['debit'] > 0 ? 'debit-value' : '' }}" style="white-space: nowrap;">{{ $transaction['debit'] > 0 ? number_format($transaction['debit'], 2) : '-' }}</td>
                <td class="text-right {{ $transaction['credit'] > 0 ? 'credit-value' : '' }}" style="white-space: nowrap;">{{ $transaction['credit'] > 0 ? number_format($transaction['credit'], 2) : '-' }}</td>
                <td class="text-right {{ ($transaction['balance'] ?? 0) < 0 ? 'balance-negative' : '' }}" style="white-space: nowrap;">
                    {{ number_format($transaction['balance'] ?? 0, 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
        @if($type === 'tax')
        <tfoot>
            <tr class="total-row">
                <td colspan="4" class="text-left">Total</td>
                <td class="text-right">{{ number_format($stats['total_debit'], 2) }}</td>
                <td class="text-right">{{ number_format($stats['total_credit'], 2) }}</td>
                <td class="text-right {{ $stats['balance'] < 0 ? 'balance-negative' : '' }}">
                    {{ number_format($stats['balance'], 2) }}
                </td>
            </tr>
        </tfoot>
        @endif
    </table>

    @if($type === 'tax')
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
    @endif

</div>
</body>
</html>
