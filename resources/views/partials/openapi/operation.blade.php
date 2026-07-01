{{--
    Operation page body. The page header already renders the document title
    (the operation summary), so this partial starts at <h2> and never emits an
    <h1>. Every section heading carries a slugified id so
    TableOfContents::fromHtml picks it up.
--}}
@php
    $copyTarget = ($baseUrl ?? '') !== '' ? rtrim($baseUrl, '/') . $operation->path : $operation->path;
@endphp
<div class="laradocs-openapi laradocs-openapi-operation">
    <div class="laradocs-openapi-endpoint">
        <span class="laradocs-openapi-method method-{{ strtolower($operation->method) }}">{{ $operation->method }}</span>
        @include('laradocs::partials.openapi.path', ['path' => $operation->path])
        @if($operation->deprecated)
            <span class="laradocs-openapi-deprecated">{{ __('laradocs::laradocs.openapi.deprecated') }}</span>
        @endif
        <button type="button"
                class="laradocs-openapi-copy"
                data-laradocs-copy="{{ $copyTarget }}"
                aria-label="{{ __('laradocs::laradocs.openapi.copy_endpoint') }}">
            <span class="laradocs-openapi-copy-idle" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
            </span>
            <span class="laradocs-openapi-copy-done" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            </span>
        </button>
    </div>

    @if(($description = $describe($operation->description)) !== '')
        <div class="laradocs-openapi-description">{!! $description !!}</div>
    @endif

    @if($parameters !== [])
        @include('laradocs::partials.openapi.parameters', ['parameters' => $parameters, 'describe' => $describe])
    @endif

    @if($requestBody !== null)
        <section class="laradocs-openapi-request-body">
            <div class="laradocs-openapi-section-head">
                <h2 id="request-body">{{ __('laradocs::laradocs.openapi.request_body') }}</h2>
                @include('laradocs::partials.openapi.schema-toolbar')
            </div>

            @if(($bodyDescription = $describe($requestBody['description'])) !== '')
                <div class="laradocs-openapi-description">{!! $bodyDescription !!}</div>
            @endif

            @foreach($requestBody['content'] as $media)
                <div class="laradocs-openapi-media" data-media-type="{{ $media['mediaType'] }}">
                    <span class="laradocs-openapi-media-type">{{ $media['mediaType'] }}</span>
                    @if($media['schema'] !== null)
                        @include('laradocs::partials.openapi.schema', ['node' => $media['schema'], 'describe' => $describe])
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    @if($responses !== [])
        <section class="laradocs-openapi-responses">
            <div class="laradocs-openapi-section-head">
                <h2 id="responses">{{ __('laradocs::laradocs.openapi.responses') }}</h2>
                @include('laradocs::partials.openapi.schema-toolbar')
            </div>

            @foreach($responses as $response)
                @include('laradocs::partials.openapi.response', ['response' => $response, 'describe' => $describe])
            @endforeach
        </section>
    @endif
</div>
