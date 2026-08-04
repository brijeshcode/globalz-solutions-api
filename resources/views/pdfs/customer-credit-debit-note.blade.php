<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $note->isCredit() ? 'Credit' : 'Debit' }} Note {{ $note->prefix }}-{{ $note->code }}</title>
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
        .company-name { font-size: 16pt; font-weight: bold; color: #000000; }
        .tax-number   { font-size: 8pt; font-weight: bold; }

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
        .note-code {
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
        .info-label { color: #555555; font-weight: bold; width: 110px; white-space: nowrap; padding-right: 12px; }
        .info-value { color: #111111; }

        /* ── Amount summary panel ── */
        .summary-panel {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000000;
            margin-bottom: 18px;
        }
        .summary-head td {
            background-color: #000000;
            color: #ffffff;
            font-size: 8.5pt;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 6px 14px;
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
            background-color: #f2f2f2;
            border-left: 1px solid #000000;
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

        /* ── Notes ── */
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #111111;
            border-bottom: 1px solid #cccccc;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .notes-box {
            border: 1px solid #111111;
            padding: 6px 8px;
            font-size: 8pt;
            color: #111111;
            margin-bottom: 12px;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* ── Signature ── */
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 60px; }
        .sig-table td { text-align: center; padding: 0 40px; }
    </style>
</head>
<body>

    @php
        $isTax = in_array($note->prefix, ['CRN', 'DBN'], true);
        $currency = $note->currency;
        $isUsd = !$currency || $currency->code === 'USD';
        $docTitle = $note->isCredit() ? 'CREDIT NOTE' : 'DEBIT NOTE';

        $formatMoney = function ($amount, $sym = null, $decimals = 2) {
            $value = number_format((float) $amount, $decimals);
            if ($sym === null || $sym === '') {
                return $value;
            }
            return $sym . ' ' . $value;
        };

        $currencySymbol = $currency->symbol ?? '';
        $currencyDecimals = $currency->decimal_places ?? 2;
        $amountLabel = 'Amount (' . ($isUsd ? 'USD' : $currency->code) . ')';
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
            <div class="doc-title">{{ $docTitle }}</div>
            <div class="note-code">{{ $note->prefix }}-{{ $note->code }}</div>
        </div>
    </div>

    <hr class="header-divider">

    {{-- ── Customer & Note Info Grid ── --}}
    <table class="info-grid">
        <tr>
            <td class="info-col-left">
                <div class="info-section-title">Customer Information</div>
                <table class="info-row">
                    <tr>
                        <td class="info-label">Customer:</td>
                        <td class="info-value">{{ $note->customer->name ?? '-' }}</td>
                    </tr>
                    @if($note->customer->code ?? null)
                    <tr>
                        <td class="info-label">Customer Code:</td>
                        <td class="info-value">{{ $note->customer->code }}</td>
                    </tr>
                    @endif
                </table>
            </td>
            <td class="info-col-right">
                <div class="info-section-title">{{ $note->isCredit() ? 'Credit' : 'Debit' }} Note Details</div>
                <table class="info-row">
                    <tr>
                        <td class="info-label">Note Date:</td>
                        <td class="info-value">{{ $note->date->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Type:</td>
                        <td class="info-value">{{ ucfirst($note->type) }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Print Date:</td>
                        <td class="info-value">{{ now()->format('d/m/Y') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── Amount Summary Panel ── --}}
    <table class="summary-panel">
        <tr class="summary-head">
            <td colspan="2">{{ $docTitle }} Summary</td>
        </tr>
        <tr>
            <td class="summary-currency">
                <table>
                    <tr>
                        <td style="padding-right: 16px;">
                            <div class="sc-label">Currency</div>
                            <div class="sc-value">{{ $currency ? $currency->name . ' (' . $currency->code . ')' : '-' }}</div>
                        </td>
                        <td>
                            <div class="sc-label">Exchange Rate</div>
                            <div class="sc-value">{{ number_format($note->currency_rate, 4) }}</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="summary-amount">
                <div class="sa-label">{{ $amountLabel }}</div>
                <div class="sa-value">
                    @if($isUsd)
                        {{ $formatMoney($note->amount_usd, '$', 2) }}
                    @else
                        {{ $formatMoney($note->amount, $currencySymbol, $currencyDecimals) }}
                    @endif
                </div>
                @if(!$isUsd)
                <div class="sa-usd">&#8776; {{ $formatMoney($note->amount_usd, '$', 2) }} USD</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ── Signature ── --}}
    <table class="sig-table">
        <tr>
            <td style="font-size: 8pt; color: #444444; font-weight: bold;">Authorized By</td>
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
