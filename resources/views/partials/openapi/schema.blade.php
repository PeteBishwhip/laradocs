{{--
    A resolved schema node (produced by SchemaRenderer): a finite plain-array
    tree. Renders recursively and emits NO headings — only the operation /
    overview partials own the heading outline, so the TOC stays clean.

    Expected node keys: type, nullable, and optionally format, description,
    enum, ref, properties, items, oneOf, anyOf, circular, truncated, unresolved.
--}}
<div class="laradocs-openapi-schema">
    <span class="laradocs-openapi-type">{{ $node['type'] ?? 'mixed' }}</span>

    @if(! empty($node['format']))
        <span class="laradocs-openapi-format">{{ $node['format'] }}</span>
    @endif

    @if(! empty($node['nullable']))
        <span class="laradocs-openapi-nullable">{{ __('laradocs::laradocs.openapi.nullable') }}</span>
    @endif

    @if(! empty($node['ref']))
        <span class="laradocs-openapi-ref">{{ $node['ref'] }}</span>
    @endif

    @if(! empty($node['circular']))
        <span class="laradocs-openapi-circular">{{ __('laradocs::laradocs.openapi.circular') }}</span>
    @endif

    @if(! empty($node['unresolved']))
        <span class="laradocs-openapi-unresolved">{{ __('laradocs::laradocs.openapi.unresolved') }}</span>
    @endif

    @if(isset($node['description']) && ($schemaDescription = $describe($node['description'])) !== '')
        <div class="laradocs-openapi-schema-description">{!! $schemaDescription !!}</div>
    @endif

    @if(! empty($node['enum']))
        <div class="laradocs-openapi-enum">
            <span class="laradocs-openapi-enum-label">{{ __('laradocs::laradocs.openapi.enum') }}</span>
            @foreach($node['enum'] as $value)
                <code class="laradocs-openapi-enum-value">{{ is_scalar($value) ? $value : json_encode($value) }}</code>
            @endforeach
        </div>
    @endif

    @if(! empty($node['properties']))
        <ul class="laradocs-openapi-properties">
            @foreach($node['properties'] as $propertyName => $property)
                <li class="laradocs-openapi-property">
                    <code class="laradocs-openapi-prop-name">{{ $propertyName }}</code>
                    @if(! empty($property['required']))
                        <span class="laradocs-openapi-required">{{ __('laradocs::laradocs.openapi.required') }}</span>
                    @endif
                    @include('laradocs::partials.openapi.schema', ['node' => $property['schema'], 'describe' => $describe])
                </li>
            @endforeach
        </ul>
    @endif

    @if(! empty($node['items']))
        <div class="laradocs-openapi-items">
            <span class="laradocs-openapi-items-label">{{ __('laradocs::laradocs.openapi.items') }}</span>
            @include('laradocs::partials.openapi.schema', ['node' => $node['items'], 'describe' => $describe])
        </div>
    @endif

    @if(! empty($node['oneOf']))
        <div class="laradocs-openapi-one-of">
            <span class="laradocs-openapi-composite-label">{{ __('laradocs::laradocs.openapi.one_of') }}</span>
            @foreach($node['oneOf'] as $variant)
                @include('laradocs::partials.openapi.schema', ['node' => $variant, 'describe' => $describe])
            @endforeach
        </div>
    @endif

    @if(! empty($node['anyOf']))
        <div class="laradocs-openapi-any-of">
            <span class="laradocs-openapi-composite-label">{{ __('laradocs::laradocs.openapi.any_of') }}</span>
            @foreach($node['anyOf'] as $variant)
                @include('laradocs::partials.openapi.schema', ['node' => $variant, 'describe' => $describe])
            @endforeach
        </div>
    @endif
</div>
