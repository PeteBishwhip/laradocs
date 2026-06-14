@php($minHeadings = (int) config('laradocs.parser.toc.min_headings', 2))
@if(! $toc->isEmpty() && $toc->count() >= $minHeadings)
    <nav class="laradocs-toc" aria-label="{{ __('laradocs::laradocs.toc.label') }}">
        <strong>{{ __('laradocs::laradocs.toc.label') }}</strong>
        <ul>
            @foreach($toc->headings as $heading)
                <li class="lvl-{{ $heading->level }}">
                    <a href="#{{ $heading->id }}">{{ $heading->text }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
