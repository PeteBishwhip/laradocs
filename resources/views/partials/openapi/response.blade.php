{{--
    A single response, nested under the "Responses" <h2> in the operation
    partial, so it starts at <h3>. The status code id keeps the TOC anchorable.
--}}
<div class="laradocs-openapi-response">
    <h3 id="response-{{ \Illuminate\Support\Str::slug($response['status']) }}">{{ $response['status'] }}</h3>

    @if(($responseDescription = $describe($response['description'])) !== '')
        <div class="laradocs-openapi-response-description">{!! $responseDescription !!}</div>
    @endif

    @foreach($response['content'] as $media)
        <div class="laradocs-openapi-media" data-media-type="{{ $media['mediaType'] }}">
            <code class="laradocs-openapi-media-type">{{ $media['mediaType'] }}</code>
            @if($media['schema'] !== null)
                @include('laradocs::partials.openapi.schema', ['node' => $media['schema'], 'describe' => $describe])
            @endif
        </div>
    @endforeach
</div>
