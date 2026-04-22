<div class="payment-note">
    @if(!empty($sale->invoice_nb1) && $invoiceSettings['show_note_1'])
        {{ $sale->invoice_nb1 }}<br><br>
    @endif
    @if(!empty($sale->invoice_nb2) && $sale->prefix !== 'INX' && $invoiceSettings['show_note_2'])
        {{ $sale->invoice_nb2 }}
    @endif
</div>

@if(!empty($catalogQrCodeBase64))
<div style="text-align: right; margin-top: 15px;">
    @if(!empty($catalogLabel))
    <div style="font-size: 8pt; margin-bottom: 3px;">{{ $catalogLabel }}</div>
    @endif
    <img src="data:image/png;base64,{{ $catalogQrCodeBase64 }}"
         style="width: 80px; height: 80px;"
         alt="Item Catalog">
</div>
@endif
