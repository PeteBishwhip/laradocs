@php
    $type = $type ?? 'info';
    $message = $body ?? ($arguments[0] ?? '');
@endphp
<div class="laradocs-alert laradocs-alert-{{ $type }}" role="alert">{{ $message }}</div>
