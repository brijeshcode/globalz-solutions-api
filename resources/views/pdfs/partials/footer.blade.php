@php
    // Prefer the current invoice-settings note; fall back to the per-sale snapshot.
    $note1 = $invoiceSettings['note_1'] ?? '';
    if ($note1 === '') { $note1 = $sale->invoice_nb1; }
    $note2 = $invoiceSettings['note_2'] ?? '';
    if ($note2 === '') { $note2 = $sale->invoice_nb2; }
@endphp
<div class="payment-note">
    @if(!empty($note1) && $sale->prefix !== 'INX' && $invoiceSettings['show_note_1'])
        {{ $note1 }}<br><br>
    @endif
    @if(!empty($note2) && $sale->prefix !== 'INX' && $invoiceSettings['show_note_2'])
        {{ $note2 }}
    @endif
</div>
