<div class="info-left">
    <div class="info-title">{{ __('invoice.customer_info') }}</div>
    <table class="info-table">
        <tr>
            <td class="info-label">{{ __('invoice.label_customer') }}:</td>
            <td>{{ $sale->customer->code ?? '' }}, {{ $sale->customer->name ?? 'Unknown Customer' }}</td>
        </tr>
        <tr>
            <td class="info-label">{{ __('invoice.label_address') }}:</td>
            <td>{{ $sale->customer->address ?? '' }}, {{ $sale->customer->city ?? '' }}</td>
        </tr>
        <tr>
            <td class="info-label">{{ __('invoice.label_phone') }}:</td>
            <td>{{ $sale->customer->mobile ?? '' }}</td>
        </tr>
        <tr>
            <td class="info-label">{{ __('invoice.label_tax') }}:</td>
            <td>{{ $sale->customer->mof_tax_number ?? '' }}</td>
        </tr>
        @if(!empty($sale->outStanding_balance) && $sale->outStanding_balance > 0)
        <tr>
            <td class="info-label">{{ __('invoice.label_outstanding') }}:</td>
            <td class="outstanding-balance">{{ number_format($sale->outStanding_balance, 2) }}</td>
        </tr>
        @endif
    </table>
</div>
