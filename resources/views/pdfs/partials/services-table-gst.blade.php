@php
    // Drop the discount column entirely when no service line is discounted.
    $showDiscount = $sale->saleServices->contains(fn ($s) => (float) $s->discount_percent > 0);
    $showHsn = $invoiceSettings['show_hsn'] ?? false;
    $detailWidth = 50 - ($showDiscount ? 7 : 0) - ($showHsn ? 8 : 0);
    $colCount = 6 + ($showDiscount ? 1 : 0) + ($showHsn ? 1 : 0);
@endphp
<table class="gst-table" style="margin-top: 6px;">
    <thead>
        <tr>
            <th colspan="{{ $colCount }}" style="text-align: left;">{{ __('invoice.services') }}</th>
        </tr>
        <tr>
            <th style="width: 5%;">{{ __('invoice.col_num') }}</th>
            <th style="width: {{ $detailWidth }}%;">{{ __('invoice.col_service_details') }}</th>
            @if($showHsn)<th style="width: 8%;">{{ __('invoice.col_hsn') }}</th>@endif
            <th style="width: 9%;">{{ __('invoice.col_price') }}</th>
            @if($showDiscount)<th style="width: 7%;">{{ __('invoice.col_discount') }}</th>@endif
            <th style="width: 9%;">{{ __('invoice.col_qty') }}</th>
            <th style="width: 8%;">{{ __('invoice.col_gst') }}</th>
            <th style="width: 19%;">{{ __('invoice.col_total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sale->saleServices as $index => $service)
        <tr>
            <td class="text-center">{{ $index + 1 }}</td>
            <td>{{ $service->service->name ?? 'Service' }}</td>
            @if($showHsn)<td class="text-center">{{ $service->hsn }}</td>@endif
            <td class="text-center">{{ number_format($service->unit_price, $invoiceSettings['unit_price_decimals']) }}</td>
            @if($showDiscount)<td class="text-center">{{ number_format($service->discount_percent, 2) }}%</td>@endif
            <td class="text-center">{{ rtrim(rtrim(number_format($service->quantity, 2), '0'), '.') }}</td>
            <td class="text-center">{{ rtrim(rtrim(number_format($service->tax_percent, 2), '0'), '.') }}%</td>
            <td class="text-right font-bold">{{ number_format($service->total_net_sell_price, $invoiceSettings['total_decimals']) }}</td>
        </tr>
        @endforeach

        @php
            // Fill the A4 page: the services table is the last block before the bottom-pinned
            // footer, so it absorbs the remaining space. Same calibration as the items table
            // ($pageRows data rows fit on A4 for a single GST rate), minus the rows the items
            // table already used and its extra header (~2 rows) when items are present.
            // ponytail: fixed A4 calibration — keep $pageRows in sync with items-table-gst.
            $servicesCount = count($sale->saleServices);
            $itemsCount    = count($sale->items);
            $pageRows      = 27;
            $extraRateRows = max(0, (isset($gstSummary) ? count($gstSummary) : 1) - 1) * 2;
            $consumed      = $itemsCount + ($itemsCount > 0 ? 2 : 0);
            $targetRows    = $pageRows - $extraRateRows - $consumed;
            $emptyRows     = max(0, $targetRows - $servicesCount);
        @endphp

        @for($i = 0; $i < $emptyRows; $i++)
        <tr>@for($c = 0; $c < $colCount; $c++)<td>&nbsp;</td>@endfor</tr>
        @endfor
    </tbody>
</table>
