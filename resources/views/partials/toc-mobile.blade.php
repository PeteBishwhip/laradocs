@php($minHeadings = (int) config('laradocs.parser.toc.min_headings', 2))
@if(! $toc->isEmpty() && $toc->count() >= $minHeadings)
    <details class="laradocs-toc-mobile" aria-label="{{ __('laradocs::laradocs.toc.label') }}">
        <summary>
            <span class="laradocs-toc-mobile-label">{{ __('laradocs::laradocs.toc.label') }}</span>
            <span class="laradocs-toc-mobile-count">{{ $toc->count() }}</span>
            <svg class="laradocs-toc-mobile-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </summary>
        <ul>
            @foreach($toc->headings as $heading)
                <li class="lvl-{{ $heading->level }}">
                    <a href="#{{ $heading->id }}">{{ $heading->text }}</a>
                </li>
            @endforeach
        </ul>
    </details>
@endif
