@if($sale->prefix !== 'INX')
@if(!empty($catalogQrCodeBase64))
<div class="signature-section clearfix" style="display: flex; align-items: flex-end; justify-content: space-between;">
    <div style="display: flex;">
        <div class="signature-box">
            <div><strong>{{ __('invoice.received_by') }}</strong></div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-box">
            <div><strong>{{ __('invoice.signature') }}</strong></div>
            <div class="signature-line"></div>
        </div>
    </div>
    <div style="text-align: right;">
        @if(!empty($catalogLabel))
        <div style="font-size: 8pt; margin-bottom: 3px;">{{ $catalogLabel }}</div>
        @endif
        <img src="data:image/png;base64,{{ $catalogQrCodeBase64 }}"
             style="width: 80px; height: 80px;"
             alt="Item Catalog">
    </div>
</div>
@else
<div class="signature-section clearfix" style="display: flex;">
    <div class="signature-box">
        <div><strong>{{ __('invoice.received_by') }}</strong></div>
        <div class="signature-line"></div>
    </div>
    <div class="signature-box">
        <div><strong>{{ __('invoice.signature') }}</strong></div>
        <div class="signature-line"></div>
    </div>
</div>
@endif
@endif
