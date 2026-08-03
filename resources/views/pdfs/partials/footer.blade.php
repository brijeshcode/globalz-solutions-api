<div class="payment-note">
    @if(!empty($sale->invoice_nb1) && $sale->prefix !== 'INX' && $invoiceSettings['show_note_1'])
        {{ $sale->invoice_nb1 }}<br><br>
    @endif
    @if(!empty($sale->invoice_nb2) && $sale->prefix !== 'INX' && $invoiceSettings['show_note_2'])
        {{ $sale->invoice_nb2 }}
    @endif
</div>
