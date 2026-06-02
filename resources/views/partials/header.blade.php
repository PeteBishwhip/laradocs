@php
    /** @var array<int, array<string, mixed>> $links */
    $links = (array) config('laradocs.ui.header.links', []);
    $searchEnabled = (bool) config('laradocs.ui.search.enabled', true);
@endphp
<header class="laradocs-header">
    <button class="laradocs-icon-btn laradocs-menu-btn" type="button" aria-label="Toggle navigation">
        <span aria-hidden="true">☰</span>
    </button>

    <a class="laradocs-brand" href="{{ route('laradocs.index') }}">
        @if(! empty($brand['logo']))
            <img src="{{ $brand['logo'] }}" alt="{{ $title }}">
        @endif
        <span class="laradocs-brand-title">{{ $title }}</span>
        @if(! empty($tagline))
            <span class="laradocs-brand-tag">{{ $tagline }}</span>
        @endif
    </a>

    <div class="laradocs-spacer"></div>

    @if($searchEnabled)
        <label class="laradocs-search" aria-label="Search documentation">
            <input type="search" placeholder="Search documentation" autocomplete="off" data-laradocs-search>
            <kbd>/</kbd>
        </label>
    @endif

    @if(! empty($links))
        <nav class="laradocs-header-nav" aria-label="Header">
            @foreach($links as $link)
                @php
                    $url = $link['url'] ?? '#';
                    $label = $link['label'] ?? $url;
                    $external = ! empty($link['external']);
                @endphp
                <a href="{{ $url }}"
                   class="{{ $external ? 'ext' : '' }}"
                   @if($external) target="_blank" rel="noopener" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    @endif

    <button class="laradocs-icon-btn" type="button" data-laradocs-theme-toggle aria-label="Toggle theme">
        <span aria-hidden="true">◐</span>
    </button>
</header>
