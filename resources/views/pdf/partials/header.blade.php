<div class="header">
    <div class="header-logo">
        <img src="{{ public_path('images/logo-full.png') }}" alt="{{ $store['name'] }}">
    </div>
    <div class="header-info">
        <div class="store-name">{{ $store['name'] }}</div>
        @if ($store['tagline'])
            <div class="store-tagline">{{ $store['tagline'] }}</div>
        @endif
        <div class="header-title">{{ $title }}</div>
        <div class="header-subtitle">{{ $dateLabel }}</div>
        @if ($audience ?? false)
            <span class="audience-badge">Para {{ strtolower($audience) }}</span>
        @endif
    </div>
</div>
