<table class="items-table">
    <thead>
        @if($sale->prefix === 'INV')
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 11%;">Item Code</th>
            <th style="width: 28%;">Description</th>
            <th style="width: 7%;">Qty.</th>
            <th style="width: 9%;">Price</th>
            <th style="width: 7%;">Disc.%</th>
            {{-- <th style="width: 11%;">Net Unit Price</th> --}}
            <th style="width: 22%;">Total</th>
        </tr>
        @else
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 11%;">REF</th>
            <th style="width: 28%;">Item Details</th>
            <th style="width: 7%;">Quantity</th>
            <th style="width: 9%;">Price</th>
            <th style="width: 7%;">Disc</th>
            {{-- <th style="width: 11%;">Net Unit Price</th> --}}
            <th style="width: 22%;">Total</th>
        </tr>
        @endif
    </thead>
    <tbody>
        @foreach($sale->items as $index => $item)
        <tr>
            <td class="text-center">{{ $index + 1 }}</td>
            <td class="text-center">{{ $item->item_code ?? '' }}</td>
            <td>{{ $item->item->description ?? 'Unknown Item' }}</td>
            <td class="text-center">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
            <td class="text-center">{{ number_format($item->price, 2) }}</td>
            <td class="text-center">{{ number_format($item->discount_percent, 2) }}%</td>
            {{-- <td class="text-center">{{ number_format($item->net_sell_price, 2) }}</td> --}}
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
            {{-- <td>&nbsp;</td> --}}
            <td>&nbsp;</td>
        </tr>
        @endfor
    </tbody>
</table>
