@php
    /** @var array<string, mixed> $banner */
    $banner = (array) config('laradocs.ui.banner', []);
    $type = $banner['type'] ?? 'info';
    $message = $banner['message'] ?? '';
@endphp

@if(! empty($banner['enabled']) && $message !== '' && $message !== null)
    <div class="laradocs-banner laradocs-banner-{{ $type }}" role="alert">
        <div class="laradocs-banner-inner">{!! $message !!}</div>
    </div>
@endif
