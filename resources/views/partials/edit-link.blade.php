@php
    $template = config('laradocs.ui.edit.url');
    $label = config('laradocs.ui.edit.label', 'Edit this page');
    $url = $template ? str_replace('{path}', ltrim($document->slug ?: 'index', '/'), (string) $template) : null;
@endphp
@if($url)
    <a class="laradocs-edit-link" href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a>
@endif
