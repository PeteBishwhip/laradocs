{{--
    Operation page body. The page header already renders the document title
    (the operation summary), so this partial starts at <h2> and never emits an
    <h1>. Every section heading carries a slugified id so
    TableOfContents::fromHtml picks it up.
--}}
<div class="laradocs-openapi laradocs-openapi-operation">
    <p class="laradocs-openapi-endpoint">
        <span class="laradocs-openapi-method method-{{ strtolower($operation->method) }}">{{ $operation->method }}</span>
        <code class="laradocs-openapi-path">{{ $operation->path }}</code>
        @if($operation->deprecated)
            <span class="laradocs-openapi-deprecated">{{ __('laradocs::laradocs.openapi.deprecated') }}</span>
        @endif
    </p>

    @if(($description = $describe($operation->description)) !== '')
        <div class="laradocs-openapi-description">{!! $description !!}</div>
    @endif

    @if($parameters !== [])
        @include('laradocs::partials.openapi.parameters', ['parameters' => $parameters, 'describe' => $describe])
    @endif

    @if($requestBody !== null)
        <section class="laradocs-openapi-request-body">
            <h2 id="request-body">{{ __('laradocs::laradocs.openapi.request_body') }}</h2>

            @if(($bodyDescription = $describe($requestBody['description'])) !== '')
                <div class="laradocs-openapi-description">{!! $bodyDescription !!}</div>
            @endif

            @foreach($requestBody['content'] as $media)
                <div class="laradocs-openapi-media" data-media-type="{{ $media['mediaType'] }}">
                    <code class="laradocs-openapi-media-type">{{ $media['mediaType'] }}</code>
                    @if($media['schema'] !== null)
                        @include('laradocs::partials.openapi.schema', ['node' => $media['schema'], 'describe' => $describe])
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    @if($responses !== [])
        <section class="laradocs-openapi-responses">
            <h2 id="responses">{{ __('laradocs::laradocs.openapi.responses') }}</h2>

            @foreach($responses as $response)
                @include('laradocs::partials.openapi.response', ['response' => $response, 'describe' => $describe])
            @endforeach
        </section>
    @endif
</div>
