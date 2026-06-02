@php($minHeadings = (int) config('laradocs.parser.toc.min_headings', 2))
@if(! $toc->isEmpty() && $toc->count() >= $minHeadings)
    <nav class="laradocs-toc" aria-label="On this page">
        <strong>On this page</strong>
        <ul>
            @foreach($toc->headings as $heading)
                <li class="lvl-{{ $heading->level }}">
                    <a href="#{{ $heading->id }}">{{ $heading->text }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
