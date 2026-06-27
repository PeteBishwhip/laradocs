{{--
    API overview page body. The page header already renders the spec title, so
    this partial starts at <h2> and emits no <h1>. Lists info, servers and the
    operations grouped by their first tag.
--}}
<div class="laradocs-openapi laradocs-openapi-overview">
    @if(($description = $describe($infoDescription)) !== '')
        <div class="laradocs-openapi-description">{!! $description !!}</div>
    @endif

    @if($servers !== [])
        <section class="laradocs-openapi-servers">
            <h2 id="servers">{{ __('laradocs::laradocs.openapi.servers') }}</h2>
            <ul class="laradocs-openapi-server-list">
                @foreach($servers as $server)
                    <li class="laradocs-openapi-server">
                        <code>{{ $server['url'] ?? '' }}</code>
                        @if(! empty($server['description']))
                            <span class="laradocs-openapi-server-description">{{ $server['description'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @php
        $byTag = [];
        foreach ($operations as $operation) {
            $tag = $operation->tags[0] ?? __('laradocs::laradocs.openapi.default_tag');
            $byTag[$tag][] = $operation;
        }
    @endphp

    <section class="laradocs-openapi-operations">
        <h2 id="operations">{{ __('laradocs::laradocs.openapi.operations') }}</h2>

        @foreach($byTag as $tag => $operationsForTag)
            <h3 id="tag-{{ \Illuminate\Support\Str::slug($tag) }}">{{ $tag }}</h3>
            <ul class="laradocs-openapi-operation-list">
                @foreach($operationsForTag as $operation)
                    <li class="laradocs-openapi-operation-item">
                        <span class="laradocs-openapi-method method-{{ strtolower($operation->method) }}">{{ $operation->method }}</span>
                        <code class="laradocs-openapi-path">{{ $operation->path }}</code>
                        @if($operation->summary !== null && $operation->summary !== '')
                            <span class="laradocs-openapi-summary">{{ $operation->summary }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endforeach
    </section>
</div>
