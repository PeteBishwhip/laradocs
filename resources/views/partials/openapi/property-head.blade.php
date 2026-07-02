{{--
    The header row for one schema property: an optional disclosure chevron (when
    the property is an expandable branch), the property name, its type meta and a
    required marker. Rendered inside a <summary> for expandable branches and a
    plain <div> for scalar leaves. Expects: $name, $child (the property's schema
    node), $required (bool), $expandable (bool).
--}}
@if($expandable ?? false)
    <span class="laradocs-openapi-chevron" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
    </span>
@endif
<code class="laradocs-openapi-prop-name">{{ $name }}</code>
<span class="laradocs-openapi-schema-meta">
    <span class="laradocs-openapi-type">{{ $child['type'] ?? 'object' }}</span>
    @if(! empty($child['format']))
        <span class="laradocs-openapi-format">{{ $child['format'] }}</span>
    @endif
    @if(! empty($child['nullable']))
        <span class="laradocs-openapi-nullable">{{ __('laradocs::laradocs.openapi.nullable') }}</span>
    @endif
    @if(! empty($child['ref']))
        <span class="laradocs-openapi-ref">{{ $child['ref'] }}</span>
    @endif
    @if(! empty($child['circular']))
        <span class="laradocs-openapi-circular">{{ __('laradocs::laradocs.openapi.circular') }}</span>
    @endif
    @if(! empty($child['unresolved']))
        <span class="laradocs-openapi-unresolved">{{ __('laradocs::laradocs.openapi.unresolved') }}</span>
    @endif
</span>
@if($required)
    <span class="laradocs-openapi-required">{{ __('laradocs::laradocs.openapi.required') }}</span>
@endif
