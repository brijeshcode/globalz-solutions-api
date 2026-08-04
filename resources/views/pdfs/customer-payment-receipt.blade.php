<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Payment Receipt {{ $payment->prefix }}-{{ $payment->code }}</title>
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
        .payment-code {
            font-size: 11pt;
            font-weight: bold;
            color: #444444;
            margin-top: 2px;
        }

        /* ── Two-column info grid ── */
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .info-grid > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }
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
        .info-label { color: #555555; font-weight: bold; white-space: nowrap; padding-right: 12px; }
        .info-value { color: #111111; text-align: right; }

        /* ── Currency / amount box ── */
        .amount-box {
            border: 1px solid #cbd5c8;
            background-color: #e8ede6;
            padding: 10px 12px;
            margin-bottom: 12px;
        }
        .amount-grid { width: 100%; border-collapse: collapse; }
        .amount-grid > tbody > tr > td { width: 50%; vertical-align: top; padding: 0 6px; }
        .amount-section-title { font-size: 8pt; font-weight: bold; color: #444444; margin-bottom: 6px; }
        .amount-row { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        .amount-row td { padding: 1px 0; font-size: 8pt; }
        .amount-label { color: #555555; white-space: nowrap; }
        .amount-value { font-weight: bold; color: #111111; text-align: right; }
        .amount-total-value { font-size: 14pt; font-weight: bold; color: #111111; text-align: right; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* ── Signature ── */
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 60px; }
        .sig-table td { text-align: center; padding: 0 40px; }
    </style>
</head>
<body>

    @php
        $isTax = $payment->prefix === 'RCT';
        $currency = $payment->currency;
        $isUsd = !$currency || $currency->code === 'USD';

        $formatMoney = function ($amount, $sym = null, $decimals = 2) {
            $value = number_format((float) $amount, $decimals);
            if ($sym === null || $sym === '') {
                return $value;
            }
            return $sym . ' ' . $value;
        };

        $currencySymbol = $currency->symbol ?? '';
        $currencyDecimals = $currency->decimal_places ?? 2;
    @endphp

    {{-- ── Header ── --}}
    <div class="clearfix">
        <div class="header-left">
            @if(!empty($company['logo']) && !empty($company['logo']['exists']))
                <img src="{{ $company['logo']['path'] }}" alt="{{ $company['name'] ?? '' }}" class="company-logo">
            @elseif(!empty($company['name']))
                <div class="company-name">{{ $company['name'] }}</div>
            @endif
            @if(!empty($company['tax_number']) && $isTax)
                <div class="tax-number">{{ $company['tax_number'] }}</div>
            @endif
        </div>

        <div class="header-right">
            <div class="doc-title">PAYMENT RECEIPT</div>
            <div class="payment-code">{{ $payment->prefix }}-{{ $payment->code }}</div>
        </div>
    </div>

    <hr class="header-divider">

    {{-- ── Customer & Payment Info Grid ── --}}
    <table class="info-grid">
        <tr>
            <td class="info-col-left">
                <div class="info-section-title">Customer Information</div>
                <table class="info-row">
                    <tr>
                        <td class="info-label">Customer:</td>
                        <td class="info-value">{{ $payment->customer->name ?? '-' }}</td>
                    </tr>
                    @if($payment->customer->code ?? null)
                    <tr>
                        <td class="info-label">Customer Code:</td>
                        <td class="info-value">{{ $payment->customer->code }}</td>
                    </tr>
                    @endif
                    @if($isTax && $payment->salesperson)
                    <tr>
                        <td class="info-label">Salesperson:</td>
                        <td class="info-value">{{ $payment->salesperson->name }}</td>
                    </tr>
                    @endif
                </table>
            </td>
            <td class="info-col-right">
                <div class="info-section-title">Payment Details</div>
                <table class="info-row">
                    <tr>
                        <td class="info-label">Payment Date:</td>
                        <td class="info-value">{{ $payment->date->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">RCT Book Number:</td>
                        <td class="info-value">{{ $payment->rtc_book_number ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Print Date:</td>
                        <td class="info-value">{{ now()->format('d/m/Y') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── Currency & Amount Box ── --}}
    <div class="amount-box">
        <table class="amount-grid">
            <tr>
                <td>
                    <div class="amount-section-title">Received Currency Information</div>
                    <table class="amount-row">
                        <tr>
                            <td class="amount-label">Currency:</td>
                            <td class="amount-value">{{ $currency ? $currency->name . ' (' . $currency->code . ')' : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="amount-label">Currency Rate:</td>
                            <td class="amount-value">{{ number_format($payment->currency_rate, 4) }}</td>
                        </tr>
                    </table>
                </td>
                <td>
                    <div class="amount-section-title">Payment Amount</div>
                    <table class="amount-row">
                        <tr>
                            <td class="amount-label" style="vertical-align: middle;">
                                Received Amount@if(!$isUsd) ({{ $currency->code }})@else (USD)@endif:
                            </td>
                            <td class="amount-total-value">
                                @if($isUsd)
                                    {{ $formatMoney($payment->amount_usd, '$', 2) }}
                                @else
                                    {{ $formatMoney($payment->amount, $currencySymbol, $currencyDecimals) }}
                                @endif
                            </td>
                        </tr>
                        @if(!$isUsd)
                        <tr>
                            <td class="amount-label">Received Amount (USD):</td>
                            <td class="amount-value">{{ $formatMoney($payment->amount_usd, '$', 2) }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Signature ── --}}
    <table class="sig-table">
        <tr>
            <td style="font-size: 8pt; color: #444444; font-weight: bold;">Received By</td>
        </tr>
        <tr>
            <td style="height: 60px; border-bottom: 1.5px solid #555555;"></td>
        </tr>
        <tr>
            <td style="padding-top: 4px; font-size: 7pt; color: #888888;">Signature &amp; Date</td>
        </tr>
    </table>

</body>
</html>
