@use('Laradocs\Routing\DocumentUrl')
@use('Laradocs\Support\Locale')
{{-- Per-locale alternate URLs so search engines can index each language and
     surface the right one. Only emitted when URL-path locales are active and
     more than one language is offered. --}}
@if(Locale::urlEnabled())
    @php($slug = $activeSlug ?? '')
    @foreach(Locale::available() as $code => $label)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ DocumentUrl::localized($slug, $code) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ DocumentUrl::localized($slug, Locale::fallback()) }}">
@endif
