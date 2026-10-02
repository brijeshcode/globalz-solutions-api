<table class="items-table" style="margin-top: 6px;">
    <thead>
        <tr>
            <th colspan="6" style="text-align: left;">{{ __('invoice.services') }}</th>
        </tr>
        <tr>
            <th style="width: 5%;">{{ __('invoice.col_num') }}</th>
            <th style="width: 48%;">{{ __('invoice.col_service_details') }}</th>
            <th style="width: 9%;">{{ __('invoice.col_price') }}</th>
            <th style="width: 7%;">{{ __('invoice.col_discount') }}</th>
            <th style="width: 9%;">{{ __('invoice.col_qty') }}</th>
            <th style="width: 22%;">{{ __('invoice.col_total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sale->saleServices as $index => $service)
        <tr>
            <td class="text-center">{{ $index + 1 }}</td>
            <td>{{ $service->service->name ?? 'Service' }}</td>
            <td class="text-center">{{ number_format($service->unit_price, $invoiceSettings['unit_price_decimals']) }}</td>
            <td class="text-center">{{ number_format($service->discount_percent, 2) }}%</td>
            <td class="text-center">{{ rtrim(rtrim(number_format($service->quantity, 2), '0'), '.') }}</td>
            <td class="text-right font-bold">{{ number_format($service->total_net_sell_price, $invoiceSettings['total_decimals']) }}</td>
        </tr>
        @endforeach

        @php
            // Pad the page only when there are no items to share the space.
            $servicesCount = count($sale->saleServices);
            $minRows = 15;
            $emptyRows = count($sale->items) === 0 && $servicesCount < $minRows ? $minRows - $servicesCount : 0;
        @endphp

        @for($i = 0; $i < $emptyRows; $i++)
        <tr>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
        @endfor
    </tbody>
</table>
