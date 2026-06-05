@use('Laradocs\Routing\DocumentUrl')
@php
    $active = $activeSlug ?? null;
    $showRoot = (bool) config('laradocs.ui.sidebar.show_root', true);
    $hasContent = $tree->rootDocument || $tree->grouped()->isNotEmpty();
@endphp
@if($hasContent)
    <aside class="laradocs-sidebar">
        <nav aria-label="Documentation">
            @if($showRoot && $tree->rootDocument)
                <ul>
                    <li>
                        <a href="{{ DocumentUrl::index() }}" class="{{ ($active === '' || $active === null) ? 'is-active' : '' }}">
                            {{ $tree->rootDocument->title() }}
                        </a>
                    </li>
                </ul>
            @endif

            @foreach($tree->grouped() as $group => $nodes)
                @if($group !== '')
                    <div class="laradocs-nav-group"><span>{{ $group }}</span></div>
                @endif
                <ul>
                    @foreach($nodes as $node)
                        @include('laradocs::partials.sidebar-node', ['node' => $node, 'active' => $active])
                    @endforeach
                </ul>
            @endforeach
        </nav>
    </aside>
@endif
