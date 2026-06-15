@php
    $tone = $type ?? 'note';
    $heading = $title ?? null;
    // The slot is passed through verbatim so nested macros / inline HTML survive.
    $content = $slot ?? ($arguments[0] ?? '');
@endphp
<div class="laradocs-callout laradocs-callout-{{ $tone }}" role="note">
    @if ($heading)
        <div class="laradocs-callout-title">{{ $heading }}</div>
    @endif
    <div class="laradocs-callout-body">{!! $content !!}</div>
</div>
