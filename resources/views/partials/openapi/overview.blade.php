{{--
    API overview / landing page body. The page header already renders the
    section title ("Overview") and the spec description, so this partial starts
    at <h2> and emits no <h1>. It surfaces the base URL(s) and version, then a
    compact, collapsible index: one <details> per resource (tag) that expands to
    reveal its endpoints — so a large spec stays scannable instead of unrolling
    every operation at once.
--}}
@use('Laradocs\Routing\DocumentUrl')
@use('Illuminate\Support\Str')
@php
    $baseSlug = config('laradocs.openapi.base_slug', 'api');
    $version = isset($info['version']) && is_scalar($info['version']) ? (string) $info['version'] : null;

    // Group operations under their first tag, preserving first-seen order.
    $byTag = [];
    foreach ($operations as $operation) {
        $tag = $operation->tags[0] ?? __('laradocs::laradocs.openapi.default_tag');
        $byTag[$tag][] = $operation;
    }

    $operationUrl = static function ($operation) use ($baseSlug) {
        $tag = $operation->tags[0] ?? 'default';
        $segment = ($operation->operationId !== null && $operation->operationId !== '')
            ? $operation->operationId
            : $operation->method . ' ' . $operation->path;

        return DocumentUrl::toSlug($baseSlug . '/' . Str::slug($tag) . '/' . Str::slug($segment));
    };
@endphp
<div class="laradocs-openapi laradocs-openapi-overview">
    @if(($description = $describe($infoDescription)) !== '')
        <div class="laradocs-openapi-description laradocs-openapi-lede">{!! $description !!}</div>
    @endif

    @if($servers !== [] || $version !== null)
        <dl class="laradocs-openapi-meta">
            @foreach($servers as $server)
                @php $url = is_scalar($server['url'] ?? null) ? (string) $server['url'] : ''; @endphp
                @if($url !== '')
                    <div class="laradocs-openapi-meta-item">
                        <dt class="laradocs-openapi-meta-label">{{ __('laradocs::laradocs.openapi.base_url') }}</dt>
                        <dd class="laradocs-openapi-meta-value">
                            <code class="laradocs-openapi-base-url">{{ $url }}</code>
                            <button type="button"
                                    class="laradocs-openapi-copy"
                                    data-laradocs-copy="{{ $url }}"
                                    aria-label="{{ __('laradocs::laradocs.openapi.copy') }}">
                                <span class="laradocs-openapi-copy-idle" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg></span>
                                <span class="laradocs-openapi-copy-done" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                            </button>
                            @if(! empty($server['description']))
                                <span class="laradocs-openapi-server-description">{{ $server['description'] }}</span>
                            @endif
                        </dd>
                    </div>
                @endif
            @endforeach
            @if($version !== null)
                <div class="laradocs-openapi-meta-item">
                    <dt class="laradocs-openapi-meta-label">{{ __('laradocs::laradocs.openapi.version') }}</dt>
                    <dd class="laradocs-openapi-meta-value">
                        <span class="laradocs-openapi-version-badge">v{{ ltrim($version, 'vV') }}</span>
                    </dd>
                </div>
            @endif
        </dl>
    @endif

    <section class="laradocs-openapi-index">
        <div class="laradocs-openapi-index-bar">
            <span class="laradocs-openapi-index-label">
                {{ __('laradocs::laradocs.openapi.resources') }}
                <span class="laradocs-openapi-index-count">{{ count($byTag) }}</span>
            </span>
            <div class="laradocs-openapi-toolbar" role="group" aria-label="{{ __('laradocs::laradocs.openapi.expand_all') }} / {{ __('laradocs::laradocs.openapi.collapse_all') }}">
                <button type="button" class="laradocs-openapi-tool" data-laradocs-schema-toggle="expand">{{ __('laradocs::laradocs.openapi.expand_all') }}</button>
                <span class="laradocs-openapi-tool-sep" aria-hidden="true"></span>
                <button type="button" class="laradocs-openapi-tool" data-laradocs-schema-toggle="collapse">{{ __('laradocs::laradocs.openapi.collapse_all') }}</button>
            </div>
        </div>

        @foreach($byTag as $tag => $group)
            <details class="laradocs-openapi-tag-section" id="section-{{ Str::slug($tag) }}">
                <summary class="laradocs-openapi-tag-summary">
                    <span class="laradocs-openapi-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                    <h2 id="tag-{{ Str::slug($tag) }}" class="laradocs-openapi-tag-heading">
                        {{ $tag }}
                        <span class="laradocs-openapi-tag-count">{{ trans_choice('laradocs::laradocs.openapi.endpoint_count', count($group), ['count' => count($group)]) }}</span>
                    </h2>
                </summary>
                <ul class="laradocs-openapi-operation-list" role="list">
                    @foreach($group as $operation)
                        <li>
                            <a class="laradocs-openapi-operation-item" href="{{ $operationUrl($operation) }}">
                                <span class="laradocs-openapi-method method-{{ strtolower($operation->method) }}">{{ $operation->method }}</span>
                                @include('laradocs::partials.openapi.path', ['path' => $operation->path])
                                @if($operation->summary !== null && $operation->summary !== '')
                                    <span class="laradocs-openapi-summary">{{ $operation->summary }}</span>
                                @endif
                                @if($operation->deprecated)
                                    <span class="laradocs-openapi-deprecated">{{ __('laradocs::laradocs.openapi.deprecated') }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endforeach
    </section>
</div>
