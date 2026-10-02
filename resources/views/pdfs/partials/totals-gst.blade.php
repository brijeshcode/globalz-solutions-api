@php
    $localRate = $sale->local_curreny_rate > 0 ? $sale->local_curreny_rate : 1;
    $showLocalTax = $invoiceSettings['show_local_currency_tax'];
    $dec = $invoiceSettings['total_decimals'];
    $pct = fn ($p) => rtrim(rtrim(number_format($p, 2), '0'), '.');
@endphp
<table class="items-table" style="margin-top: -1px;">
    <tbody>
        <tr class="totals-row first-total">
            {{-- Left: volume/weight (items only) + amount in words, opposite the Net Total --}}
            <td style="width: 72%; vertical-align: bottom; border: none; padding-right: 8px;">
                @if(count($sale->items) > 0)
                <div style="font-size: 8pt;">
                    <strong>{{ __('invoice.volume_cbm') }}:</strong> {{ number_format($totalVolume, 2) }}
                </div>
                <div style="font-size: 8pt;">
                    <strong>{{ __('invoice.weight_kg') }}:</strong> {{ number_format($totalWeight, 2) }}
                </div>
                @endif
                <div style="font-size: 9pt;">
                    <strong>{{ __('invoice.amount_in_words') }} :</strong>
                    {{ $amountInWords ?? '' }} {{ __('invoice.only') }}
                </div>
            </td>

            {{-- Right: financial totals --}}
            <td style="width: 28%; border: none; padding: 0; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse;">
                    {{-- Sub Total (without GST) --}}
                    <tr>
                        <td colspan="2" style="border: none; width: 50%;">&nbsp;</td>
                        <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap; width: 28%;">{{ __('invoice.sub_total') }}</td>
                        <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; width: 22%;">{{ number_format($sale->sub_total, $dec) }}</td>
                    </tr>

                    @if($sale->discount_amount > 0)
                    <tr>
                        <td colspan="2" style="border: none; width: 50%;">&nbsp;</td>
                        <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap; width: 28%;">{{ __('invoice.amount_discount') }}</td>
                        <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; width: 22%;">{{ number_format($sale->discount_amount, $dec) }}</td>
                    </tr>
                    @endif

                    {{-- CGST / SGST split, one pair per distinct GST rate (lowest first) --}}
                    @foreach($gstSummary as $g)
                        @foreach([['label' => __('invoice.cgst'), 'v' => $g['half']], ['label' => __('invoice.sgst'), 'v' => $g['half']]] as $row)
                        <tr>
                            @if($showLocalTax)
                            <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap; width: 28%;">{{ $row['label'] }} {{ $pct($g['half_percent']) }}% {{ $invoiceSettings['local_currency_symbol'] }}</td>
                            <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; width: 22%;">{{ number_format(($g['half_usd'] * $localRate), $dec) }}</td>
                            @else
                            <td colspan="2" style="border: none; width: 50%;">&nbsp;</td>
                            @endif
                            <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap; width: 28%;">{{ $row['label'] }} {{ $pct($g['half_percent']) }}%</td>
                            <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; width: 22%;">{{ number_format($row['v'], $dec) }}</td>
                        </tr>
                        @endforeach
                    @endforeach

                    {{-- Total (including GST) --}}
                    @if($invoiceSettings['show_local_currency_total'])
                    <tr>
                        <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap;">{{ __('invoice.net_total') }} {{ $invoiceSettings['local_currency_symbol'] }}</td>
                        <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px;">{{ number_format($sale->total_usd * $localRate, 2) }}</td>
                        <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap;">{{ __('invoice.net_total') }}</td>
                        <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; font-size: 10pt;">{{ number_format($sale->total, $dec) }}</td>
                    </tr>
                    @else
                    <tr>
                        <td colspan="2" style="border: none; width: 50%;">&nbsp;</td>
                        <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap; width: 28%;">{{ __('invoice.net_total') }}</td>
                        <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; font-size: 10pt; width: 22%;">{{ number_format($sale->total, $dec) }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </tbody>
</table>
