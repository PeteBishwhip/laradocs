@php
    $text = $footer['text'] ?? ('© ' . date('Y') . ' ' . $title);
    /** @var array<int, array<string, mixed>> $links */
    $links = (array) ($footer['links'] ?? []);
@endphp
<footer class="laradocs-footer" role="contentinfo">
    <div class="laradocs-footer-text">{{ $text }}</div>

    @if(! empty($links))
        <nav class="laradocs-footer-links" aria-label="{{ __('laradocs::laradocs.nav.footer') }}">
            @foreach($links as $link)
                @php
                    $url = $link['url'] ?? '#';
                    $label = $link['label'] ?? $url;
                    $external = ! empty($link['external']);
                @endphp
                <a href="{{ $url }}"
                   @if($external) target="_blank" rel="noopener" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    @endif
</footer>
