{{--
    A single response, nested under the "Responses" <h2> in the operation
    partial, so it starts at <h3>. The status code id keeps the TOC anchorable.
    The status class tints the pill: 2xx success, 3xx redirect, 4xx/5xx error.
--}}
@php
    $status = (string) $response['status'];
    $statusClass = match (substr($status, 0, 1)) {
        '2' => 'is-success',
        '3' => 'is-redirect',
        '4', '5' => 'is-error',
        default => 'is-info',
    };
@endphp
<div class="laradocs-openapi-response {{ $statusClass }}">
    <div class="laradocs-openapi-response-head">
        <h3 id="response-{{ \Illuminate\Support\Str::slug($status) }}" class="laradocs-openapi-status">
            <span class="laradocs-openapi-status-dot" aria-hidden="true"></span>{{ $status }}
        </h3>
        @if(($responseDescription = $describe($response['description'])) !== '')
            <div class="laradocs-openapi-response-description">{!! $responseDescription !!}</div>
        @endif
    </div>

    @foreach($response['content'] as $media)
        <div class="laradocs-openapi-media" data-media-type="{{ $media['mediaType'] }}">
            <span class="laradocs-openapi-media-type">{{ $media['mediaType'] }}</span>
            @if($media['schema'] !== null)
                {{-- Response schemas start collapsed so long payloads don't bloat the page. --}}
                @include('laradocs::partials.openapi.schema', ['node' => $media['schema'], 'open' => false, 'describe' => $describe])
            @endif
        </div>
    @endforeach
</div>
