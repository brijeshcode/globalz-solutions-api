<div class="info-left">
    @if($sale->prefix !== 'INX')
    <div class="info-title">{{ __('invoice.customer_info') }}</div>
    @endif
    <table class="info-table">
        <tr>
            <td class="info-label">{{ $sale->prefix === 'INX' ? 'Client' : __('invoice.label_customer') }}:</td>
            <td>{{ $sale->prefix === 'INX' ? ($sale->customer->name ?? 'Unknown Customer') : (($sale->customer->code ?? '') . ', ' . ($sale->customer->name ?? 'Unknown Customer')) }}</td>
        </tr>
        <tr>
            <td class="info-label">{{ __('invoice.label_address') }}:</td>
            <td>{{ $sale->customer->address ?? '' }}, {{ $sale->customer->city ?? '' }}</td>
        </tr>
        <tr>
            <td class="info-label">{{ $sale->prefix === 'INX' ? __('invoice.label_tel') : __('invoice.label_phone') }}:</td>
            <td>{{ $sale->customer->mobile ?? '' }}</td>
        </tr>
        @if($sale->prefix !== 'INX')
        <tr>
            <td class="info-label"></td>
            <td>{{ $sale->customer->mof_tax_number ?? '' }}</td>
        </tr>
        @endif
        @if(!empty($sale->outStanding_balance) && $sale->outStanding_balance > 0)
        <tr>
            <td class="info-label">{{ __('invoice.label_outstanding') }}:</td>
            <td class="outstanding-balance">{{ number_format($sale->outStanding_balance, 2) }}</td>
        </tr>
        @endif
    </table>
</div>
