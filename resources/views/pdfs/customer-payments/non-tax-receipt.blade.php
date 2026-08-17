<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Payment Receipt {{ $payment->prefix }}-{{ $payment->code }}</title>
    <style>
        body {
            font-family:  sans-serif;
            font-size: 9pt;
            color: #111110;
            margin: 0;
            padding: 0;
        }
        .clearfix::after { content: ""; display: table; clear: both; }

        /* ── Header ── */
        .header-left  { width: 50%; float: left; }
        .header-right { width: 50%; float: right; text-align: right; }
        .company-logo { max-height: 70px; max-width: 200px; }
        .company-name { font-size: 16pt; font-weight: bold; color: #000000; }

        .header-divider {
            border: none;
            border-top: 2px solid #000000;
            margin: 10px 0 14px 0;
        }
        .doc-title {
            font-size: 18pt;
            font-weight: bold;
            color: #000000;
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
            color: #000000;
            border-bottom: 1px solid #cccccc;
            padding-bottom: 3px;
            margin-bottom: 6px;
        }
        .info-row { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        .info-row td { padding: 1px 0; font-size: 8pt; }
        .info-label { color: #555555; font-weight: bold; width: 50px; white-space: nowrap; padding-right: 12px; }
        .info-value { color: #111111; }

        /* ── Payment summary panel ── */
        .summary-panel {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000000;
            margin-bottom: 18px;
        }
        .summary-head td {
            background-color: #E0E0E0;
            color: #555555;
            font-size: 8.5pt;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 6px 14px;
            margin-bottom: 5px;
        }
        .summary-currency { padding: 14px 16px; vertical-align: middle; }
        .summary-currency table { width: 100%; border-collapse: collapse; }
        .summary-currency td { vertical-align: top; padding: 0; }
        .sc-label {
            font-size: 7pt;
            color: #555555;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .sc-value { font-size: 10pt; font-weight: bold; color: #111111; }
        .summary-amount {
            width: 42%;
            padding: 12px 18px;
            text-align: right;
            vertical-align: middle;
        }
        .sa-label {
            font-size: 8pt;
            color: #000000;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .sa-value { font-size: 21pt; font-weight: bold; color: #000000; }
        .sa-usd { font-size: 8pt; color: #555555; margin-top: 4px; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* ── Signature ── */
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 60px; }
        .sig-table td { text-align: center; padding: 0 40px; }
    </style>
</head>
<body>

    @php
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
        $receivedLabel = 'Amount';
    @endphp

    {{-- ── Header ── --}}
    <div class="clearfix">
        <div class="text-center">
            Receipt
            <div class="payment-code">{{ $payment->prefix }}-{{ $payment->code }}</div>
        </div>
    </div>

    <hr class="header-divider">

    {{-- ── Customer & Payment Info Grid ── --}}
    <table class="info-grid">
        <tr>
            <td class="info-col-left">
                <table class="info-row">
                    <tr>
                        <td class="info-label">Client:</td>
                        <td class="info-value">{{ $payment->customer->name ?? '-' }}</td>
                    </tr>
                </table>
            </td>
            <td class="info-col-right">
                <table class="info-row">
                    <tr>
                        <td class="info-label">Date:</td>
                        <td class="info-value">{{ $payment->date->format('d/m/Y') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── Payment Summary Panel ── --}}
    <table class="summary-panel">
        <tr>
            <td class="summary-currency">
                <div class="sa-label">{{ $receivedLabel }}</div>
             
            </td>
            <td class="summary-amount">
                <div class="sa-value">
                    @if($isUsd)
                        {{ $formatMoney($payment->amount_usd, '$', 2) }}
                    @else
                        {{ $formatMoney($payment->amount, $currencySymbol, $currencyDecimals) }}
                    @endif
                </div>
                @if(!$isUsd)
                <div class="sa-usd">&#8776; {{ $formatMoney($payment->amount_usd, '$', 2) }} USD</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ── Signature ── --}}
    <table class="sig-table">
        
        <tr>
            <td style="height: 60px; border-bottom: 1.5px solid #555555;"></td>
        </tr>
        <tr>
            <td style="padding-top: 4px; font-size: 10pt;">Signature</td>
        </tr>
    </table>

</body>
</html>
