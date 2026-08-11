<div class="footer">
    <div class="footer-left">
        {{ $footerNote }}
        @if ($store['phone'] || $store['address'] || $store['email'])
            <div class="footer-contact">
                @if ($store['phone'])
                    Tel: {{ $store['phone'] }}
                @endif
                @if ($store['address'])
                    @if ($store['phone']) &middot; @endif
                    {{ $store['address'] }}
                @endif
                @if ($store['email'])
                    @if ($store['phone'] || $store['address']) &middot; @endif
                    {{ $store['email'] }}
                @endif
            </div>
        @endif
    </div>
    <div class="footer-right">
        Total: {{ $products->count() }} producto(s)
    </div>
</div>
