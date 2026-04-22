<div class="info-right">
    <div class="info-title">{{ __('invoice.invoice_details') }}</div>
    <table class="info-table">
        <tr>
            <td class="info-label">{{ __('invoice.label_date') }}:</td>
            <td>{{ $sale->date->format('Y-m-d') }}</td>
        </tr>
        @if($sale->value_date)
        <tr>
            <td class="info-label">{{ __('invoice.label_value_date') }}:</td>
            <td>{{ $sale->value_date->format('Y-m-d') }}</td>
        </tr>
        @endif
        @if($invoiceSettings['is_multi_currency'])
        <tr>
            <td class="info-label">{{ __('invoice.label_currency') }}:</td>
            <td>{{ $sale->currency->code ?? 'N/A' }}</td>
        </tr>
        @endif
        @if($sale->salesperson)
        <tr>
            <td class="info-label">{{ __('invoice.label_salesperson') }}:</td>
            <td>{{ $sale->salesperson->name ?? 'Not assigned' }}</td>
        </tr>
        @endif
    </table>
</div>
