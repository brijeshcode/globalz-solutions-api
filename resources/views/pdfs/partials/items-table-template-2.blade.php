<table class="items-table">
    <thead>
        <tr>
            <th style="width: 5%;">{{ __('invoice.col_num') }}</th>
            <th style="width: 11%;">{{ __('invoice.col_item_code') }}</th>
            <th style="width: 28%;">{{ __('invoice.col_description') }}</th>
            <th style="width: 7%;">{{ __('invoice.col_qty') }}</th>
            <th style="width: 9%;">{{ __('invoice.col_price') }}</th>
            <th style="width: 7%;">{{ __('invoice.col_discount') }}</th>
            <th style="width: 11%;">{{ __('invoice.col_net_unit_price') }}</th>
            @if($sale->prefix === 'INV')
            <th style="width: 9%;">{{ __('invoice.col_tax') }}</th>
            @endif
            <th style="width: 13%;">{{ __('invoice.col_total_excl_tax') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sale->items as $index => $item)
        <tr>
            <td class="text-center">{{ $index + 1 }}</td>
            <td>{{ $item->item_code ?? '' }}</td>
            <td>{{ $item->item->description ?? 'Unknown Item' }}</td>
            <td class="text-center">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
            <td class="text-center">{{ number_format($item->price, 2) }}</td>
            <td class="text-center">{{ number_format($item->discount_percent, 2) }}%</td>
            <td class="text-center">{{ number_format($item->net_sell_price, 2) }}</td>
            @if($sale->prefix === 'INV')
            <td class="text-center">{{ number_format($item->tax_percent, 0) }}%</td>
            @endif
            <td class="text-right font-bold">{{ number_format($item->total_net_sell_price, 2) }}</td>
        </tr>
        @endforeach

        @php
            $itemsCount = count($sale->items);
            $minRows = 15;
            $emptyRows = $itemsCount < $minRows ? $minRows - $itemsCount : 0;
        @endphp

        @for($i = 0; $i < $emptyRows; $i++)
        <tr>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            @if($sale->prefix === 'INV')
            <td>&nbsp;</td>
            @endif
            <td>&nbsp;</td>
        </tr>
        @endfor
    </tbody>
</table>
