{{--
    Operation parameters, grouped by their location (path / query / header /
    cookie). Starts at <h2> with a slugified id for the TOC. Each parameter is a
    term/detail pair: the name + type + required marker sit on the term row, the
    description (and any allowed values) below.
--}}
@php
    $groups = [];
    foreach ($parameters as $parameter) {
        $groups[$parameter['in'] === '' ? 'query' : $parameter['in']][] = $parameter;
    }
    // Present locations in a stable, sensible order regardless of spec ordering.
    $order = ['path' => 0, 'query' => 1, 'header' => 2, 'cookie' => 3];
    uksort($groups, function ($a, $b) use ($order) {
        return ($order[$a] ?? 99) <=> ($order[$b] ?? 99);
    });

    // Full, static translation keys per location so each resolves (and stays
    // discoverable by the localisation linter — no dynamic key concatenation).
    $inLabels = [
        'path' => __('laradocs::laradocs.openapi.in.path'),
        'query' => __('laradocs::laradocs.openapi.in.query'),
        'header' => __('laradocs::laradocs.openapi.in.header'),
        'cookie' => __('laradocs::laradocs.openapi.in.cookie'),
    ];
@endphp
<section class="laradocs-openapi-parameters">
    <h2 id="parameters">{{ __('laradocs::laradocs.openapi.parameters') }}</h2>

    @foreach($groups as $in => $group)
        <div class="laradocs-openapi-param-group">
            <span class="laradocs-openapi-param-group-label">{{ $inLabels[$in] ?? ucfirst($in) }}</span>
            <dl class="laradocs-openapi-param-list">
                @foreach($group as $parameter)
                    <div class="laradocs-openapi-param">
                        <dt class="laradocs-openapi-param-term">
                            <code class="laradocs-openapi-param-name">{{ $parameter['name'] }}</code>
                            @if($parameter['schema'] !== null)
                                <span class="laradocs-openapi-type">{{ $parameter['schema']['type'] ?? 'string' }}</span>
                                @if(! empty($parameter['schema']['format']))
                                    <span class="laradocs-openapi-format">{{ $parameter['schema']['format'] }}</span>
                                @endif
                            @endif
                            @if($parameter['required'])
                                <span class="laradocs-openapi-required">{{ __('laradocs::laradocs.openapi.required') }}</span>
                            @else
                                <span class="laradocs-openapi-optional">{{ __('laradocs::laradocs.openapi.optional') }}</span>
                            @endif
                            @if($parameter['deprecated'])
                                <span class="laradocs-openapi-deprecated">{{ __('laradocs::laradocs.openapi.deprecated') }}</span>
                            @endif
                        </dt>
                        <dd class="laradocs-openapi-param-detail">
                            @if(($paramDescription = $describe($parameter['description'])) !== '')
                                <div class="laradocs-openapi-param-description">{!! $paramDescription !!}</div>
                            @endif
                            @if($parameter['schema'] !== null && ! empty($parameter['schema']['enum']))
                                <div class="laradocs-openapi-enum">
                                    <span class="laradocs-openapi-enum-label">{{ __('laradocs::laradocs.openapi.enum') }}</span>
                                    @foreach($parameter['schema']['enum'] as $value)
                                        <code class="laradocs-openapi-enum-value">{{ is_scalar($value) ? $value : json_encode($value) }}</code>
                                    @endforeach
                                </div>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endforeach
</section>
