@php
    /** @var array<string, mixed> $analytics */
    $analytics = (array) config('laradocs.analytics', []);

    $fathom = (array) ($analytics['fathom'] ?? []);
    $fathomSite = $fathom['site'] ?? null;
    $fathomScript = $fathom['script'] ?? 'https://cdn.usefathom.com/script.js';
    $fathomSpa = $fathom['spa'] ?? null;
@endphp

@if($fathomSite)
    <script src="{{ $fathomScript }}"
            data-site="{{ $fathomSite }}"
            @if($fathomSpa) data-spa="{{ $fathomSpa }}" @endif
            defer></script>
@endif
