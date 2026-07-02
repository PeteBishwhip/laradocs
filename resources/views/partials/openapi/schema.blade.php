{{--
    A resolved schema node (produced by SchemaRenderer): a finite plain-array
    tree. Renders recursively and emits NO headings — only the operation /
    overview partials own the heading outline, so the TOC stays clean.

    Branch nodes (objects with properties, arrays with items, and oneOf/anyOf
    composites) render as <details> so every branch can be collapsed
    individually; the "Expand all / Collapse all" toolbar toggles them in bulk.

    $open (default true) sets the initial disclosure state of every branch —
    response schemas pass false so they start collapsed. $head (default true)
    controls whether the node's own type "meta" line is rendered; property rows
    render that meta line beside the property name themselves and then recurse
    with head=false to emit only the description and nested structure, so the
    type and name always share a line.

    Expected node keys: type, nullable, and optionally format, description,
    enum, ref, properties, items, oneOf, anyOf, circular, truncated, unresolved.
--}}
@php
    $head = $head ?? true;
    $open = $open ?? true;
    $expandable = static fn (array $n): bool =>
        ! empty($n['properties']) || ! empty($n['items']) || ! empty($n['oneOf']) || ! empty($n['anyOf']);
@endphp
<div class="laradocs-openapi-schema">
    @if($head)
        <div class="laradocs-openapi-schema-meta">
            <span class="laradocs-openapi-type">{{ $node['type'] ?? 'object' }}</span>
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
        </div>
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
        <ul class="laradocs-openapi-properties" role="list">
            @foreach($node['properties'] as $propertyName => $property)
                @php
                    $child = $property['schema'] ?? [];
                    $required = ! empty($property['required']);
                    $isExpandable = $expandable($child);
                @endphp
                <li>
                    @if($isExpandable)
                        <details class="laradocs-openapi-property is-expandable" @if($open) open @endif>
                            <summary class="laradocs-openapi-prop-head">
                                @include('laradocs::partials.openapi.property-head', ['name' => $propertyName, 'child' => $child, 'required' => $required, 'expandable' => true])
                            </summary>
                            @include('laradocs::partials.openapi.schema', ['node' => $child, 'head' => false, 'open' => $open, 'describe' => $describe])
                        </details>
                    @else
                        <div class="laradocs-openapi-property">
                            <div class="laradocs-openapi-prop-head">
                                @include('laradocs::partials.openapi.property-head', ['name' => $propertyName, 'child' => $child, 'required' => $required, 'expandable' => false])
                            </div>
                            @if(! empty($child['description']) || ! empty($child['enum']))
                                @include('laradocs::partials.openapi.schema', ['node' => $child, 'head' => false, 'open' => $open, 'describe' => $describe])
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if(! empty($node['items']))
        @if($expandable($node['items']))
            <details class="laradocs-openapi-branch" @if($open) open @endif>
                <summary class="laradocs-openapi-branch-label">
                    <span class="laradocs-openapi-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>{{ __('laradocs::laradocs.openapi.items') }}
                </summary>
                @include('laradocs::partials.openapi.schema', ['node' => $node['items'], 'head' => true, 'open' => $open, 'describe' => $describe])
            </details>
        @else
            <div class="laradocs-openapi-items">
                <span class="laradocs-openapi-items-label">{{ __('laradocs::laradocs.openapi.items') }}</span>
                @include('laradocs::partials.openapi.schema', ['node' => $node['items'], 'head' => true, 'open' => $open, 'describe' => $describe])
            </div>
        @endif
    @endif

    @if(! empty($node['oneOf']))
        <details class="laradocs-openapi-branch" @if($open) open @endif>
            <summary class="laradocs-openapi-branch-label">
                <span class="laradocs-openapi-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>{{ __('laradocs::laradocs.openapi.one_of') }}
            </summary>
            @foreach($node['oneOf'] as $variant)
                @include('laradocs::partials.openapi.schema', ['node' => $variant, 'head' => true, 'open' => $open, 'describe' => $describe])
            @endforeach
        </details>
    @endif

    @if(! empty($node['anyOf']))
        <details class="laradocs-openapi-branch" @if($open) open @endif>
            <summary class="laradocs-openapi-branch-label">
                <span class="laradocs-openapi-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>{{ __('laradocs::laradocs.openapi.any_of') }}
            </summary>
            @foreach($node['anyOf'] as $variant)
                @include('laradocs::partials.openapi.schema', ['node' => $variant, 'head' => true, 'open' => $open, 'describe' => $describe])
            @endforeach
        </details>
    @endif
</div>
