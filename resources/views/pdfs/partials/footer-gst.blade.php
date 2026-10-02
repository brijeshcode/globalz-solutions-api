@php
    // Terms & bank details come from the invoice note settings (live), falling back to the
    // per-sale snapshot — same source as the standard footer.
    $terms1 = $invoiceSettings['note_1'] ?? '';
    if ($terms1 === '') { $terms1 = $sale->invoice_nb1; }
    $terms2 = $invoiceSettings['note_2'] ?? '';
    if ($terms2 === '') { $terms2 = $sale->invoice_nb2; }
    $showTerms1 = !empty($terms1) && ($invoiceSettings['show_note_1'] ?? true);
    $showTerms2 = !empty($terms2) && ($invoiceSettings['show_note_2'] ?? true);
@endphp

<table style="width: 100%; margin-top: 8px; border-top: 1px solid #000; padding-top: 4px;">
    <tr>
        <td style="width: 65%; vertical-align: top; font-size: 8pt;">
            @if($showTerms1 || $showTerms2) 
                @if($showTerms1)<div>{!! nl2br($terms1) !!}</div>@endif
                @if($showTerms2)<div style="margin-top: 16px;">{!! nl2br($terms2) !!}</div>@endif
            @endif
        </td>
        <td style="width: 35%; text-align: center; vertical-align: top; font-size: 9pt;">
            <div>{{ $company['name'] ?? '' }}</div>
            @if(!empty($company['show_stamp']) && $company['show_stamp'] && !empty($company['stamp']) && !empty($company['stamp']['exists']))
                @php $stampW = $company['stamp_width'] ?? 120; $stampH = $company['stamp_height'] ?? 120; @endphp
                <img src="{{ $company['stamp']['path'] ?? '' }}"
                     alt="{{ $company['name'] ?? 'Stamp' }}"
                     style="height: {{ $stampH }}px; width: {{ $stampW }}px; opacity: 0.8;">
            @else
                <div style="margin-top: 40px;"></div>
            @endif
            <div style="margin-top: 4px;">{{ __('invoice.proprietor') }}</div>
        </td>
    </tr>
</table>
