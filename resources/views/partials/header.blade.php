@use('Laradocs\Routing\DocumentUrl')
@php
    /** @var array<int, array<string, mixed>> $links */
    $links = (array) config('laradocs.ui.header.links', []);
@endphp
<header class="laradocs-header">
    <button class="laradocs-icon-btn laradocs-menu-btn" type="button" aria-label="{{ __('laradocs::laradocs.nav.toggle_navigation') }}" data-laradocs-menu>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 6h18M3 12h18M3 18h18"/>
        </svg>
    </button>

    <a class="laradocs-brand" href="{{ DocumentUrl::index() }}">
        @if(! empty($brand['logo']))
            <img src="{{ $brand['logo'] }}" alt="{{ $title }}">
        @else
            <span class="laradocs-brand-mark" aria-hidden="true"></span>
        @endif
        <span class="laradocs-brand-title">{{ $title }}</span>
        @if(! empty($tagline))
            <span class="laradocs-brand-tag">{{ $tagline }}</span>
        @endif
    </a>

    <button type="button" class="laradocs-palette-trigger" data-laradocs-palette-open aria-label="{{ __('laradocs::laradocs.search.open') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <span>{{ __('laradocs::laradocs.search.trigger') }}</span>
        <kbd data-laradocs-kbd-trigger>⌘K</kbd>
    </button>

    @if(! empty($links))
        <nav class="laradocs-header-nav" aria-label="{{ __('laradocs::laradocs.nav.header') }}">
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

    <div class="laradocs-spacer"></div>

    @include('laradocs::partials.version-selector')
    @include('laradocs::partials.language-selector')

    <button class="laradocs-icon-btn" type="button" data-laradocs-theme-toggle aria-label="{{ __('laradocs::laradocs.theme.toggle') }}">
        <svg class="icon-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 3v18"/>
            <path d="M12 3a9 9 0 0 1 0 18" fill="currentColor" stroke="none"/>
        </svg>
        <svg class="icon-light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
        </svg>
        <svg class="icon-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
    </button>
</header>
