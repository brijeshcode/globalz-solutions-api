<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Payslip {{ $salary->prefix }}{{ $salary->code }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #222222;
            line-height: 1.4;
        }
        .clearfix::after { content: ""; display: table; clear: both; }

        /* ── Header ── */
        .header-row {
            width: 100%;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .header-left  { width: 50%; float: left; }
        .header-right { width: 50%; float: right; text-align: right; }
        .header-title-row { text-align: center; padding-top: 4px; margin-bottom: 12px; }
        .company-logo { max-height: 70px; max-width: 180px; }
        .doc-title { font-size: 13pt; font-weight: normal; color: #1a1a1a; letter-spacing: 0.5px; }
        .doc-id-label { font-size: 7.5pt; color: #888888; margin-top: 4px; text-transform: uppercase; }
        .doc-id-value { font-size: 10.5pt; font-weight: bold; color: #1e3a5f; }

        /* ── Info table ── */
        .info-table { width: 80%; border-collapse: collapse; margin: 0 auto 14px auto; font-size: 8.5pt; }
        .info-table td { padding: 3px 6px; }
        .info-label { font-weight: normal; color: #444444; width: 110px; text-align: right;}
        .info-value { font-weight: bold; color: #111111; }
        .info-border { border: none; }

        /* ── Section title ── */
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            text-align: center;
            color: #1a1a1a;
            padding: 5px 0;
            margin-bottom: 0;
        }

        /* ── Breakdown table ── */
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        .breakdown-table td {
            padding: 3px 4px;
            vertical-align: top;
        }
        .col-date    { width: 20%; }
        .col-code    { width: 20%; }
        .col-desc    { width: 21%; }
        .col-amount  { width: 14%; text-align: right; color: #222222; }
        .col-subtotal{ width: 14%; text-align: right; font-weight: bold; color: #111111; }

        .section-header td {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #f4f4f4;
            color: #222222;
            padding: 4px 6px;
            margin-bottom: 25px;
        }
        .sub-header td {
            font-weight: bold;
            color: #1a1a1a;
            padding-left: 35px;
            padding-top: 5px;
            padding-bottom: 2px;
        }
        .item-row td {
            padding-left: 20px;
            color: #333333;
        }

        .item-row td.col-date {
            padding-left: 45px;
        }
        .net-row td {
            font-size: 10pt;
            font-weight: bold;
            color: #111111;
            border-top: 2px solid #222222;
            padding: 6px 4px;
        }
        .text-right { text-align: right; }
        .text-bold  { font-weight: bold; }
    </style>
</head>
<body>

    {{-- ── Header ── --}}
    <div class="header-row clearfix">
        <div class="header-left">
            @if(!empty($company['logo']) && !empty($company['logo']['exists']))
                <img src="{{ $company['logo']['path'] }}"
                     alt="{{ $company['name'] ?? '' }}"
                     class="company-logo">
            @elseif(!empty($company['name']))
                <div style="font-size: 14pt; font-weight: bold; color: #1a1a1a;">{{ $company['name'] }}</div>
            @endif
        </div>

        <div class="header-right">
            <div class="doc-id-label">Payslip ID</div>
            <div class="doc-id-value">{{ $salary->prefix }}{{ $salary->code }}</div>
        </div>
    </div>
    <div class="header-title-row">
        <div class="doc-title">Employee Pay Summary</div>
        <div class="doc-id-value">ID#{{ $salary->prefix }}{{ $salary->code }}</div>
    </div>

    {{-- ── Employee Info ── --}}
    <table class="info-table info-border">
        <tr>
            <td class="info-label">Name:</td>
            <td class="info-value">{{ $salary->employee->name }}</td>
            <td class="info-label">Employee ID:</td>
            <td class="info-value">{{ $salary->employee->code }}</td>
        </tr>
        <tr>
            <td class="info-label">Pay Period:</td>
            <td class="info-value">{{ $monthName }}</td>
            <td class="info-label">Generation Day:</td>
            <td class="info-value">{{ now()->format('d-M-Y') }}</td>
        </tr>
        <tr>
            <td class="info-label">Net Amount:</td>
            <td class="info-value">{{ number_format($salary->final_total, 2) }}</td>
            <td class="info-label">Comm Plan:</td>
            <td class="info-value">{{ $commissionPlanName ?? '—' }}</td>
        </tr>
    </table>

    {{-- ── Amount Breakdown ── --}}
    <div class="section-title">Amount Breakdown</div>

    <table class="breakdown-table">
        <tbody>

            {{-- ══ EARNINGS ══ --}}
            <tr class="section-header">
                <td colspan="5">Earnings</td>
            </tr>

            {{-- Base Salary --}}
            <tr class="sub-header">
                <td class="col-date">Base Salary</td>
                <td class="col-code"></td>
                <td class="col-desc"></td>
                <td class="col-amount"></td>
                <td class="col-subtotal">{{ number_format($salary->base_salary, 2) }}</td>
            </tr>

            {{-- Commission --}}
            @php $visibleCommissionItems = $commissionItems->filter(fn($item) => !empty($item->label)); @endphp
            @if($visibleCommissionItems->isNotEmpty())
                <tr class="sub-header">
                    <td colspan="5">Commission</td>
                </tr>
                @foreach($visibleCommissionItems as $item)
                    <tr class="item-row">
                        <td class="col-date"></td>
                        <td class="col-code">{{ $item->label }}</td>
                        <td class="col-desc"></td>
                        <td class="col-amount">{{ number_format($item->value, 2) }}</td>
                        <td class="col-subtotal">
                            @if($loop->last)
                                {{ number_format($salary->sub_total, 2) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endif


            {{-- Others (positive → earning) --}}
            @if(!empty($salary->others) && $salary->others > 0)
                <tr class="sub-header">
                    <td class="col-date">Other</td>
                    <td class="col-code"></td>
                    <td class="col-desc">{{ $salary->others_note }}</td>
                    <td class="col-amount"></td>
                    <td class="col-subtotal">{{ number_format($salary->others, 2) }}</td>
                </tr>
            @endif

            <tr>
                <td colspan="5" style="height: 10px;"></td>
            </tr>
            <tr>
                <td colspan="5" style="height: 10px;"></td>
            </tr>
            {{-- ══ DEDUCTIONS ══ --}}
            <tr class="section-header">
                <td colspan="5">Deductions</td>
            </tr>

            {{-- Advance Loan --}}
            {{-- @if($advanceLoans->isNotEmpty())
                <tr class="sub-header">
                    <td colspan="5">Advance Loan</td>
                </tr>
                @foreach($advanceLoans as $loan)
                    <tr class="item-row">
                        <td class="col-date">{{ $loan->date->format('d/m/Y') }}</td>
                        <td class="col-code">{{ $loan->advance_loan_code }}</td>
                        <td class="col-desc"></td>
                        <td class="col-amount">{{ number_format(-$loan->amount, 2) }}</td>
                        <td class="col-subtotal">
                            @if($loop->last)
                                {{ number_format(-$advanceLoans->sum('amount'), 2) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endif --}}
            @if($salary->advance_payment != 0)
                <tr class="sub-header">
                    <td class="col-date">Advance Loan Payment</td>
                    <td class="col-code"></td>
                    <td class="col-desc"></td>
                    <td class="col-amount"></td>
                    <td class="col-subtotal">{{ number_format(-$salary->advance_payment, 2) }}</td>
                </tr>
            @endif

            {{-- Others (negative → deduction) --}}
            @if(!empty($salary->others) && $salary->others < 0)
                <tr class="sub-header">
                    <td class="col-date">Other</td>
                    <td class="col-code"></td>
                    <td class="col-desc">{{ $salary->others_note }}</td>
                    <td class="col-amount"></td>
                    <td class="col-subtotal">{{ number_format($salary->others, 2) }}</td>
                </tr>
            @endif


            {{-- ══ NET AMOUNT ══ --}}
            <tr class="net-row">
                <td class="col-date">Net Amount</td>
                <td class="col-code"></td>
                <td class="col-desc"></td>
                <td class="col-amount"></td>
                <td class="col-subtotal" style="font-size: 11pt;">
                    {{ number_format($salary->final_total, 2) }}
                </td>
            </tr>

        </tbody>
    </table>

</body>
</html>
