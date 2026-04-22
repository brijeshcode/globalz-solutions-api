<table class="items-table" style="margin-top: -1px;">
    <tbody>
        <tr class="totals-row first-total">
            <td colspan="4" style="width: 45%; border: none;">
                <div style="font-size: 8pt;">
                    <strong>{{ __('invoice.volume_cbm') }}:</strong> {{ number_format($totalVolume, 2) }}
                </div>
            </td>
            <td colspan="2" style="width: 20%; border: none;"></td>
            <td class="font-bold" style="width: {{ $sale->prefix === 'INV' ? 13 : 18 }}%; white-space: nowrap;">{{ __('invoice.sub_total') }}</td>
            <td class="text-right font-bold" style="width: 15%;">{{ number_format($sale->sub_total, 2) }}</td>
        </tr>

        <tr class="totals-row">
            <td colspan="6" style="width: 73%; position: relative; border: none;">
                <div style="font-size: 8pt;">
                    <strong>{{ __('invoice.weight_kg') }}:</strong> {{ number_format($totalWeight, 2) }}
                </div>

                @if($sale->prefix === 'INV' && !empty($company['show_stamp']) && $company['show_stamp'] && !empty($company['stamp']) && !empty($company['stamp']['exists']))
                    <img src="{{ $company['stamp']['path'] ?? '' }}"
                         alt="{{ $company['name'] ?? 'Company Stamp' }}"
                         class="company-stamp"
                         style="height: {{ $company['stamp_height'] ?? '150' }}px; width: {{ $company['stamp_width'] ?? '150' }}px; position: absolute; left: 35%; top: -50%;">
                @endif
            </td>
            <td class="font-bold" style="width: {{ $sale->prefix === 'INV' ? 13 : 18 }}%; white-space: nowrap;">{{ __('invoice.amount_discount') }}</td>
            <td class="text-right font-bold" style="width: 15%;">{{ number_format($sale->discount_amount, 2) }}</td>
        </tr>

        @if($sale->prefix === 'INV')
        <tr class="totals-row">
            <td colspan="{{ $invoiceSettings['show_local_currency_tax'] ? 4 : 6 }}" style="width: 43%; border: none;">&nbsp;</td>
            @if($invoiceSettings['show_local_currency_tax'])
            <td class="font-bold" style="width: 12%; white-space: nowrap;">{{ $sale->invoice_tax_label }} {{ $invoiceSettings['local_currency_symbol'] }}</td>
            <td class="text-right font-bold" style="width: 15%;">{{ number_format($sale->total_tax_amount_usd * ($sale->local_curreny_rate > 0 ? $sale->local_curreny_rate : 1), 2) }}</td>
            @endif
            <td class="font-bold" style="width: 13%; white-space: nowrap;">{{ $sale->invoice_tax_label }}</td>
            <td class="text-right font-bold" style="width: 15%;">{{ number_format($sale->total_tax_amount, 2) }}</td>
        </tr>
        @endif

        <tr class="totals-row">
            <td colspan="{{ $invoiceSettings['show_local_currency_total'] ? 4 : 6 }}" style="width: 43%; border: none;"></td>
            @if($invoiceSettings['show_local_currency_total'])
            <td class="font-bold" style="width:12%; white-space: nowrap;">{{ __('invoice.net_total') }} {{ $invoiceSettings['local_currency_symbol'] }}</td>
            <td class="text-right font-bold" style="width: 15%;">{{ number_format($sale->total_usd * ($sale->local_curreny_rate > 0 ? $sale->local_curreny_rate : 1), 2) }}</td>
            @endif
            <td class="font-bold" style="width: {{ $sale->prefix === 'INV' ? 13 : 18 }}%; white-space: nowrap;">{{ __('invoice.net_total') }}</td>
            <td class="text-right font-bold" style="width: 15%; font-size: 10pt;">{{ number_format($sale->total, 2) }}</td>
        </tr>
    </tbody>
</table>
