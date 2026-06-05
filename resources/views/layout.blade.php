@php
    /** @var array<string, mixed> $brand */
    $brand = (array) config('laradocs.ui.brand', []);
    /** @var array<string, mixed> $fonts */
    $fonts = (array) config('laradocs.ui.fonts', []);
    /** @var array<string, mixed> $footer */
    $footer = (array) config('laradocs.ui.footer', []);

    $defaultTheme = config('laradocs.ui.theme', 'auto');
    $preset = config('laradocs.ui.preset', 'classic');
    $accent = config('laradocs.ui.accent');
    $fontSans = $fonts['sans'] ?? null;
    $fontMono = $fonts['mono'] ?? null;
    $fontDisplay = $fonts['display'] ?? null;
    $title = $brand['title'] ?? 'Documentation';
    $tagline = $brand['tagline'] ?? null;
    $loadWebfonts = (bool) config('laradocs.ui.webfonts', true);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"@if($defaultTheme !== 'auto') data-theme="{{ $defaultTheme }}"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $title)</title>
    @hasSection('description')
        <meta name="description" content="@yield('description')">
    @endif
    @if(! empty($brand['favicon']))
        <link rel="icon" href="{{ $brand['favicon'] }}">
    @endif
    <script>
        (function () {
            try {
                var t = localStorage.getItem('laradocs-theme');
                if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    @if($loadWebfonts)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">
    @endif
    <link rel="stylesheet" href="{{ route('laradocs.asset', ['file' => 'laradocs.css']) }}">
    @if($accent || $fontSans || $fontMono || $fontDisplay)
        <style>
            :root {
                @if($accent) --dc-accent: {{ $accent }}; @endif
                @if($fontSans) --dc-font: {{ $fontSans }}; @endif
                @if($fontMono) --dc-mono: {{ $fontMono }}; @endif
                @if($fontDisplay) --dc-display: {{ $fontDisplay }}; @endif
            }
        </style>
    @endif
    @include('laradocs::partials.analytics')
    @stack('head')
</head>
<body class="laradocs" data-preset="{{ $preset }}">
    <div class="laradocs-progress" aria-hidden="true"><span></span></div>

    @include('laradocs::partials.header', ['brand' => $brand, 'tagline' => $tagline, 'title' => $title, 'tree' => $tree ?? null])

    @if(isset($tree) && method_exists($tree, 'grouped'))
        <nav class="laradocs-tabs" aria-label="Sections">
            <div class="laradocs-tabs-inner">
                @php $activeGroup = $document->metadata->group ?? null; @endphp
                @foreach($tree->grouped() as $group => $nodes)
                    @php
                        $first = collect($nodes)->first(fn($n) => $n->isLink());
                        $href = $first ? route('laradocs.show', ['path' => $first->slug]) : '#';
                        $label = $group === '' ? 'Overview' : $group;
                    @endphp
                    <a href="{{ $href }}"
                       class="laradocs-tab {{ ($activeGroup ?? '') === $group ? 'is-active' : '' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </nav>
    @endif

    <div class="laradocs-shell">
        <div class="laradocs-backdrop" aria-hidden="true" data-laradocs-backdrop></div>
        @include('laradocs::partials.sidebar', ['nodes' => $tree->navigation() ?? []])

        <div class="laradocs-main">
            <main class="laradocs-content">
                @yield('content')
            </main>
            @yield('toc')
        </div>
    </div>

    {{-- Variant: command palette dialog. --}}
    @php $searchEnabled = (bool) config('laradocs.ui.search.enabled', true); @endphp
    <div class="laradocs-palette" data-laradocs-palette
         @if($searchEnabled)
             data-laradocs-search-url="{{ route('laradocs.search') }}"
             data-laradocs-search-min="{{ (int) config('laradocs.search.min_chars', 2) }}"
         @endif
         hidden role="dialog" aria-label="Quick search" aria-modal="true">
        <div class="laradocs-palette-backdrop" data-laradocs-palette-close></div>
        <div class="laradocs-palette-panel">
            <div class="laradocs-palette-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" placeholder="Search pages…" data-laradocs-palette-input>
                <kbd>esc</kbd>
            </div>
            <ul class="laradocs-palette-results" data-laradocs-palette-results>
                @if(isset($tree) && method_exists($tree, 'grouped'))
                    @foreach($tree->grouped() as $group => $nodes)
                        @foreach($nodes as $node)
                            @if($node->isLink())
                                <li>
                                    <a href="{{ route('laradocs.show', ['path' => $node->slug]) }}"
                                       data-label="{{ strtolower($node->title) }}">
                                        <span class="laradocs-palette-title">{{ $node->title }}</span>
                                        @if($group !== '')
                                            <span class="laradocs-palette-group">{{ $group }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endif
                            @if($node->children)
                                @foreach($node->children as $child)
                                    @if($child->isLink())
                                        <li>
                                            <a href="{{ route('laradocs.show', ['path' => $child->slug]) }}"
                                               data-label="{{ strtolower($child->title) }}">
                                                <span class="laradocs-palette-title">{{ $child->title }}</span>
                                                <span class="laradocs-palette-group">{{ $group !== '' ? $group . ' › ' . $node->title : $node->title }}</span>
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            @endif
                        @endforeach
                    @endforeach
                @endif
            </ul>
            <div class="laradocs-palette-footer">
                <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
                <span><kbd>↵</kbd> open</span>
                <span><kbd>esc</kbd> close</span>
            </div>
        </div>
    </div>

    @if(! empty($footer['enabled']))
        @include('laradocs::partials.footer', ['footer' => $footer, 'title' => $title])
    @endif

    <script src="{{ route('laradocs.asset', ['file' => 'laradocs.js']) }}"></script>
    @stack('scripts')
</body>
</html>
