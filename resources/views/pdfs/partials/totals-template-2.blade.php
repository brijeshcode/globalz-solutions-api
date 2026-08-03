<table class="items-table" style="margin-top: -1px;">
    <tbody>
        <tr class="totals-row first-total">
            @if($sale->prefix !== 'INX')
            {{-- Left: volume + weight --}}
            <td style="width: 45%; vertical-align: top; border: none;">
                <div style="font-size: 8pt;">
                    <strong>{{ __('invoice.volume_cbm') }}:</strong> {{ number_format($totalVolume, 2) }}
                </div>
                <div style="font-size: 8pt;">
                    <strong>{{ __('invoice.weight_kg') }}:</strong> {{ number_format($totalWeight, 2) }}
                </div>
            </td>

            {{-- Middle: stamp --}}
            <td style="width: 27%; text-align: center; vertical-align: middle; border: none;">
                @if($sale->prefix === 'INV' && !empty($company['show_stamp']) && $company['show_stamp'] && !empty($company['stamp']) && !empty($company['stamp']['exists']))
                    @php $stampW = $company['stamp_width'] ?? 150; $stampH = $company['stamp_height'] ?? 150; @endphp
                    <img src="{{ $company['stamp']['path'] ?? '' }}"
                         alt="{{ $company['name'] ?? 'Company Stamp' }}"
                         style="height: {{ $stampH }}px; width: {{ $stampW }}px; opacity: 0.7;">
                @endif
            </td>
            @else
            <td colspan="2" style="border: none;"></td>
            @endif

            {{-- Right: financial totals --}}
            <td style="width: 28%; border: none; padding: 0; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse;">
                    @if($sale->prefix !== 'INX')
                    <tr>
                        <td colspan="2" class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap; width: 50%;">{{ __('invoice.sub_total') }}</td>
                        <td colspan="2" class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; width: 50%;">{{ number_format($sale->sub_total, $invoiceSettings['total_decimals']) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap;">{{ __('invoice.amount_discount') }}</td>
                        <td colspan="2" class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px;">{{ number_format($sale->discount_amount, $invoiceSettings['total_decimals']) }}</td>
                    </tr>
                    @endif

                    @if($sale->prefix === 'INV')
                        @if($invoiceSettings['show_local_currency_tax'])
                        <tr>
                            <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap; width: 28%;">{{ __('invoice.tax_amount') }} {{ $invoiceSettings['local_currency_symbol'] }}</td>
                            <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; width: 22%;">{{ number_format($sale->total_tax_amount_usd * ($sale->local_curreny_rate > 0 ? $sale->local_curreny_rate : 1), $invoiceSettings['total_decimals']) }}</td>
                            <td class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap; width: 28%;">{{ __('invoice.tax_amount') }}</td>
                            <td class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; width: 22%;">{{ number_format($sale->total_tax_amount, $invoiceSettings['total_decimals']) }}</td>
                        </tr>
                        @else
                        <tr>
                            <td colspan="2" class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap;">{{ __('invoice.tax_amount') }}</td>
                            <td colspan="2" class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px;">{{ number_format($sale->total_tax_amount, $invoiceSettings['total_decimals']) }}</td>
                        </tr>
                        @endif
                    @endif

                    @if($sale->prefix !== 'INX')
                        @if($invoiceSettings['show_local_currency_total'])
                        <tr>
                            <td colspan="2" class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap;">{{ __('invoice.net_total') }} {{ $invoiceSettings['local_currency_symbol'] }}</td>
                            <td colspan="2" class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px;">{{ number_format($sale->total_usd * ($sale->local_curreny_rate > 0 ? $sale->local_curreny_rate : 1), $invoiceSettings['total_decimals']) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td colspan="2" class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap;">{{ __('invoice.net_total') }}</td>
                            <td colspan="2" class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; font-size: 10pt;">{{ number_format($sale->total, $invoiceSettings['total_decimals']) }}</td>
                        </tr>
                    @endif

                    @if($sale->prefix === 'INX')
                    <tr>
                        <td colspan="2" class="font-bold" style="border: 1px solid #000; padding: 4px 2px; white-space: nowrap;">{{ __('invoice.net_total') }}</td>
                        <td colspan="2" class="text-right font-bold" style="border: 1px solid #000; padding: 4px 2px; font-size: 10pt;">{{ number_format($sale->total, $invoiceSettings['total_decimals']) }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </tbody>
</table>
