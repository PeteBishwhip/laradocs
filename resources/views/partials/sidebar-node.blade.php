<li>
    @if($node->isLink())
        <a href="{{ \Laradocs\Routing\DocumentUrl::toSlug($node->slug) }}"
           class="{{ ($active ?? null) === $node->slug ? 'is-active' : '' }}">
            @if($node->document !== null && $node->document->metadata->icon)
                {!! \Laradocs\Icons\Icon::render($node->document->metadata->icon) !!}
            @endif
            {{ $node->title }}
            @if($node->document !== null && $node->document->metadata->badge)
                <span class="laradocs-badge">{{ $node->document->metadata->badge }}</span>
            @endif
        </a>
    @else
        <div class="laradocs-nav-group">
            @if($node->document !== null && $node->document->metadata->icon)
                {!! \Laradocs\Icons\Icon::render($node->document->metadata->icon) !!}
            @endif
            <span>{{ $node->title }}</span>
        </div>
    @endif

    @if($node->children)
        <ul class="laradocs-children">
            @foreach($node->children as $child)
                @include('laradocs::partials.sidebar-node', ['node' => $child, 'active' => $active ?? null])
            @endforeach
        </ul>
    @endif
</li>
