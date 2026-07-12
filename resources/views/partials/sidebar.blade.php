@php
    $active = $activeSlug ?? null;
    $showRoot = (bool) config('laradocs.ui.sidebar.show_root', true);
    $hasContent = $tree->rootDocument || $tree->grouped()->isNotEmpty();
@endphp
@if($hasContent)
    <aside class="laradocs-sidebar">
        <nav aria-label="{{ __('laradocs::laradocs.nav.documentation') }}">
            @if($showRoot && $tree->rootDocument)
                <ul>
                    <li>
                        <a href="{{ \Laradocs\Routing\DocumentUrl::index() }}" class="{{ ($active === '' || $active === null) ? 'is-active' : '' }}">
                            {{ $tree->rootDocument->title() }}
                        </a>
                    </li>
                </ul>
            @endif

            @foreach($tree->grouped() as $group => $nodes)
                @php
                    // An OpenAPI "Overview" node parents every tag section by slug
                    // (api → api/{tag}/{op}). Lift those sections up so they sit
                    // beside the Overview as siblings, rather than nesting the whole
                    // reference inside the Overview page.
                    $items = [];
                    foreach ($nodes as $node) {
                        $marker = $node->document !== null ? $node->document->metadata->get('openapi') : null;
                        if (is_array($marker) && ($marker['type'] ?? null) === 'overview' && $node->children !== []) {
                            $items[] = new \Laradocs\Documents\TreeNode($node->title, $node->slug, $node->document, [], $node->depth);
                            foreach ($node->children as $child) {
                                $items[] = $child;
                            }
                        } else {
                            $items[] = $node;
                        }
                    }
                @endphp
                @if($group !== '')
                    <div class="laradocs-nav-group"><span>{{ $group }}</span></div>
                @endif
                <ul class="{{ $group !== '' ? 'laradocs-nav-group-items' : '' }}">
                    @foreach($items as $node)
                        @include('laradocs::partials.sidebar-node', ['node' => $node, 'active' => $active])
                    @endforeach
                </ul>
            @endforeach
        </nav>
    </aside>
@endif
