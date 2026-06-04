@php
    /** @var array<string, mixed> $analytics */
    $analytics = (array) config('laradocs.analytics', []);

    $fathom = (array) ($analytics['fathom'] ?? []);
    $fathomSite = $fathom['site'] ?? null;
    $fathomScript = $fathom['script'] ?? 'https://cdn.usefathom.com/script.js';
    $fathomSpa = $fathom['spa'] ?? null;

    $google = (array) ($analytics['google'] ?? []);
    $googleId = $google['measurement_id'] ?? null;
    $googleAnonymize = (bool) ($google['anonymize_ip'] ?? false);
@endphp

@if($fathomSite)
    <script src="{{ $fathomScript }}"
            data-site="{{ $fathomSite }}"
            @if($fathomSpa) data-spa="{{ $fathomSpa }}" @endif
            defer></script>
@endif

@if($googleId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($googleId)@if($googleAnonymize), { 'anonymize_ip': true }@endif);
    </script>
@endif
