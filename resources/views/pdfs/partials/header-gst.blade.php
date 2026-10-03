<div style="border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 8px;">
<table style="width: 100%; border-collapse: collapse;"><tr>
<td style="width: 230px; vertical-align: top; text-align: left;">
    @if(!empty($company['show_logo']) && $company['show_logo'] && !empty($company['logo']) && !empty($company['logo']['exists']))
        <img src="{{ $company['logo']['path'] ?? '' }}"
             alt="{{ $company['name'] ?? 'Company Logo' }}"
             style="max-height: 80px; max-width: 220px;">
    @endif
</td>
<td style="text-align: center; vertical-align: top;">
    @if(!empty($company['name']))
        <div class="company-name">{{ mb_strtoupper($company['name']) }}</div>
    @endif

    @if(!empty($company['address']))
        <div style="font-size: 9pt;">{{ $company['address'] }}</div>
    @endif

    <div style="font-size: 8pt;">
        @if(!empty($company['phone']))Phone : {{ $company['phone'] }}@endif
        @if(!empty($company['website'])) &nbsp; Website : {{ $company['website'] }}@endif
        @if(!empty($company['email'])) &nbsp; E-Mail : {{ $company['email'] }}@endif
    </div>

    <div class="invoice-title" style="margin-top: 6px;">{{ __('invoice.gst_invoice_title') }}</div>

    @if(!empty($company['tax_number']))
        <div style="font-size: 9pt; font-weight: bold;">{{ __('invoice.label_gstin') }} : {{ mb_strtoupper($company['tax_number']) }}</div>
    @endif
</td>
<td style="width: 230px;"></td>
</tr></table>
</div>
