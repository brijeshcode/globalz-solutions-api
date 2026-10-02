@php
    // Drop the discount column entirely when no line is discounted.
    $showDiscount = $sale->items->contains(fn ($i) => (float) $i->discount_percent > 0);
    $showHsn = $invoiceSettings['show_hsn'] ?? false;
    $descWidth = 36 - ($showDiscount ? 7 : 0) - ($showHsn ? 8 : 0);
    $colCount = 7 + ($showDiscount ? 1 : 0) + ($showHsn ? 1 : 0);
@endphp
<table class="gst-table">
    <thead>
        @if($showServices ?? false)
        <tr>
            <th colspan="{{ $colCount }}" style="text-align: left;">{{ __('invoice.items') }}</th>
        </tr>
        @endif
        <tr>
            <th style="width: 5%;">{{ __('invoice.col_num') }}</th>
            <th style="width: 10%;">{{ __('invoice.col_item_code') }}</th>
            <th style="width: {{ $descWidth }}%;">{{ __('invoice.col_description') }}</th>
            @if($showHsn)<th style="width: 8%;">{{ __('invoice.col_hsn') }}</th>@endif
            <th style="width: 9%;">{{ __('invoice.col_price') }}</th>
            @if($showDiscount)<th style="width: 7%;">{{ __('invoice.col_discount') }}</th>@endif
            <th style="width: 8%;">{{ __('invoice.col_qty') }}</th>
            <th style="width: 8%;">{{ __('invoice.col_gst') }}</th>
            <th style="width: 24%;">{{ __('invoice.col_total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sale->items as $index => $item)
        <tr>
            <td class="text-center">{{ $index + 1 }}</td>
            <td class="text-center">{{ $item->item_code ?? '' }}</td>
            <td>{{ $item->item->description ?? 'Unknown Item' }}</td>
            @if($showHsn)<td class="text-center">{{ $item->hsn }}</td>@endif
            <td class="text-center">{{ number_format($item->price, $invoiceSettings['unit_price_decimals']) }}</td>
            @if($showDiscount)<td class="text-center">{{ number_format($item->discount_percent, 2) }}%</td>@endif
            <td class="text-center">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
            <td class="text-center">{{ rtrim(rtrim(number_format($item->tax_percent, 2), '0'), '.') }}%</td>
            <td class="text-right font-bold">{{ number_format($item->total_net_sell_price, $invoiceSettings['total_decimals']) }}</td>
        </tr>
        @endforeach

        @php
            // Fill the A4 page: pad with empty rows so the table reaches the bottom-pinned
            // footer (totals + terms/stamp live in the mPDF footer). We can't measure in mPDF,
            // so $pageRows is a calibrated count of data rows that fit on A4 for a single GST
            // rate; each extra rate makes the footer ~2 rows taller, so reserve for it.
            // ponytail: fixed A4 calibration — lower $pageRows if items spill to a 2nd page,
            //           raise it if a gap remains above the footer.
            $itemsCount = count($sale->items);
            $pageRows   = 27;
            $extraRateRows = max(0, (isset($gstSummary) ? count($gstSummary) : 1) - 1) * 2;
            $targetRows = $pageRows - $extraRateRows;
            $emptyRows  = ($showServices ?? false) ? 0 : max(0, $targetRows - $itemsCount);
        @endphp

        @for($i = 0; $i < $emptyRows; $i++)
        <tr>@for($c = 0; $c < $colCount; $c++)<td>&nbsp;</td>@endfor</tr>
        @endfor
    </tbody>
</table>
