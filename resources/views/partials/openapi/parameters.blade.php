{{-- Operation parameters. Starts at <h2> with a slugified id for the TOC. --}}
<section class="laradocs-openapi-parameters">
    <h2 id="parameters">{{ __('laradocs::laradocs.openapi.parameters') }}</h2>

    <ul class="laradocs-openapi-parameter-list">
        @foreach($parameters as $parameter)
            <li class="laradocs-openapi-parameter">
                <code class="laradocs-openapi-param-name">{{ $parameter['name'] }}</code>
                @if($parameter['in'] !== '')
                    <span class="laradocs-openapi-param-in">{{ $parameter['in'] }}</span>
                @endif
                @if($parameter['required'])
                    <span class="laradocs-openapi-required">{{ __('laradocs::laradocs.openapi.required') }}</span>
                @endif
                @if($parameter['deprecated'])
                    <span class="laradocs-openapi-deprecated">{{ __('laradocs::laradocs.openapi.deprecated') }}</span>
                @endif

                @if($parameter['schema'] !== null)
                    @include('laradocs::partials.openapi.schema', ['node' => $parameter['schema'], 'describe' => $describe])
                @endif

                @if(($paramDescription = $describe($parameter['description'])) !== '')
                    <div class="laradocs-openapi-param-description">{!! $paramDescription !!}</div>
                @endif
            </li>
        @endforeach
    </ul>
</section>
