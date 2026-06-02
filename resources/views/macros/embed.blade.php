@php($url = \Laradocs\Support\Url::safe((string) ($url ?? ($arguments[0] ?? ''))))
<a href="{{ $url }}">{{ $url }}</a>
