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
    $title = $brand['title'] ?? 'Documentation';
    $tagline = $brand['tagline'] ?? null;
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
    <link rel="stylesheet" href="{{ route('laradocs.asset', ['file' => 'laradocs.css']) }}">
    @if($accent || $fontSans || $fontMono)
        <style>
            :root {
                @if($accent) --dc-accent: {{ $accent }}; @endif
                @if($fontSans) --dc-font: {{ $fontSans }}; @endif
                @if($fontMono) --dc-mono: {{ $fontMono }}; @endif
            }
        </style>
    @endif
    @stack('head')
</head>
<body class="laradocs" data-preset="{{ $preset }}">
    @include('laradocs::partials.header', ['brand' => $brand, 'tagline' => $tagline, 'title' => $title])

    <div class="laradocs-shell">
        @include('laradocs::partials.sidebar', ['nodes' => $tree->navigation() ?? []])

        <div class="laradocs-main">
            <main class="laradocs-content">
                @yield('content')
            </main>
            @yield('toc')
        </div>
    </div>

    @if(! empty($footer['enabled']))
        @include('laradocs::partials.footer', ['footer' => $footer, 'title' => $title])
    @endif

    <script src="{{ route('laradocs.asset', ['file' => 'laradocs.js']) }}"></script>
    @stack('scripts')
</body>
</html>
