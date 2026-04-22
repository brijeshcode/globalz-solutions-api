<div class="header-row clearfix">
    <div class="header-left">
        @if($sale->prefix === 'INV')
            @if(!empty($company['show_logo']) && $company['show_logo'] && !empty($company['logo']) && !empty($company['logo']['exists']))
                <img src="{{ $company['logo']['path'] ?? '' }}"
                     alt="{{ $company['name'] ?? 'Company Logo' }}"
                     class="company-logo"
                     style="height: {{ $company['logo_height'] ?? '80' }}px; width: {{ $company['logo_width'] ?? '200' }}px;">
            @elseif(!empty($company['name']))
                <div class="company-name">{{ $company['name'] }}</div>
            @endif

            @if(!empty($company['tax_number']))
                <div style="margin-top: 5px;">
                    <strong>{{ $company['tax_number'] }}</strong>
                </div>
            @endif
        @endif
    </div>

    <div class="header-center">
        <div class="invoice-title">{{ __('invoice.title') }}</div>
        <div class="invoice-code">{{ $sale->prefix }}-{{ $sale->code }}</div>
    </div>

    <div class="header-right">
        @if(!empty($qrCodeBase64))
            <div style="font-size: 8pt; margin-bottom: 2px;">{{ __('invoice.google_map') }}</div>
            <img src="data:image/png;base64,{{ $qrCodeBase64 }}"
                 style="width: 70px; height: 70px;"
                 alt="Customer Location">
        @endif
    </div>
</div>
