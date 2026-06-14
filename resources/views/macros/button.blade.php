@php
    $url = $href ?? ($arguments[1] ?? '#');
    $label = $text ?? ($arguments[0] ?? __('laradocs::laradocs.macros.read_more'));
@endphp
<a class="laradocs-button" href="{{ \Laradocs\Support\Url::safe((string) $url) }}">{{ $label }}</a>
