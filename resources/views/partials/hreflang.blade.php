{{-- Per-locale alternate URLs so search engines can index each language and
     surface the right one. Only emitted when URL-path locales are active and
     more than one language is offered. --}}
@if(\Laradocs\Support\Locale::urlEnabled())
    @php($slug = $activeSlug ?? '')
    @foreach(\Laradocs\Support\Locale::available() as $code => $label)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ \Laradocs\Routing\DocumentUrl::localized($slug, $code) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ \Laradocs\Routing\DocumentUrl::localized($slug, \Laradocs\Support\Locale::fallback()) }}">
@endif
