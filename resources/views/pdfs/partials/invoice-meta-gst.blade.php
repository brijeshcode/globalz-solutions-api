<table style="width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 9pt;">
    <tr>
        {{-- Buyer --}}
        <td style="width: 55%; border: 1px solid #000; padding: 6px; vertical-align: top;">
            <div><strong>M/s {{ $sale->customer->name ?? '' }}</strong></div>
            @if(!empty($sale->customer->address))
                <div>{{ $sale->customer->address }}</div>
            @endif
            @if(!empty($sale->customer->city))
                <div>{{ $sale->customer->city }}</div>
            @endif
            <div>{{ __('invoice.label_phone') }} : {{ $sale->customer->mobile ?? '' }}</div>
            @if(!empty($sale->customer->mof_tax_number))
                <div>{{ __('invoice.label_gstin') }} : {{ $sale->customer->mof_tax_number }}</div>
            @endif
        </td>

        {{-- Invoice meta --}}
        <td style="width: 45%; border: 1px solid #000; padding: 6px; vertical-align: top;">
            <table style="width: 100%; font-size: 9pt;">
                <tr>
                    <td>{{ __('invoice.meta_invoice_no') }} : <strong>{{ $sale->prefix }}{{ $sale->code }}</strong></td>
                    <td>{{ __('invoice.label_date') }} : <strong>{{ $sale->date->format('d/m/Y') }}</strong></td>
                </tr>
                <tr><td colspan="2">{{ __('invoice.meta_order_no') }} : {{ $sale->client_po_number }}</td></tr>
                <!-- <tr><td colspan="2">{{ __('invoice.meta_lr_no') }} :</td></tr> -->
                <!-- <tr><td colspan="2">{{ __('invoice.meta_cases') }} :</td></tr> -->
                <!-- <tr><td colspan="2">{{ __('invoice.meta_transport') }} :</td></tr> -->
                <tr><td colspan="2">{{ __('invoice.label_value_date') }} : {{ $sale->value_date ? $sale->value_date->format('d/m/Y') : '' }}</td></tr>
            </table>
        </td>
    </tr>
</table>
