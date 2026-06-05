@use('Laradocs\Routing\DocumentUrl')
<li>
    @if($node->isLink())
        <a href="{{ DocumentUrl::toSlug($node->slug) }}"
           class="{{ ($active ?? null) === $node->slug ? 'is-active' : '' }}">
            {{ $node->title }}
            @if($node->document?->metadata->badge)
                <span class="laradocs-badge">{{ $node->document->metadata->badge }}</span>
            @endif
        </a>
    @else
        <div class="laradocs-nav-group"><span>{{ $node->title }}</span></div>
    @endif

    @if($node->children)
        <ul class="laradocs-children">
            @foreach($node->children as $child)
                @include('laradocs::partials.sidebar-node', ['node' => $child, 'active' => $active ?? null])
            @endforeach
        </ul>
    @endif
</li>
