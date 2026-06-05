@extends('laradocs::layout')

@use('Laradocs\Routing\DocumentUrl')

@section('title', $document->title())
@if($document->metadata->description)
    @section('description', $document->metadata->description)
@endif

@section('content')
    @if(count($breadcrumbs) > 1)
        <nav class="laradocs-breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ DocumentUrl::index() }}">Home</a>
            @foreach($breadcrumbs as $crumb)
                <span aria-hidden="true">·</span>
                @if($loop->last)
                    <span>{{ $crumb->title }}</span>
                @elseif($crumb->isLink())
                    <a href="{{ DocumentUrl::toSlug($crumb->slug) }}">{{ $crumb->title }}</a>
                @else
                    <span>{{ $crumb->title }}</span>
                @endif
            @endforeach
        </nav>
    @endif

    @php
        $eyebrow = $document->metadata->get('eyebrow') ?? $document->metadata->group;
    @endphp
    <header class="laradocs-page-header">
        @if($eyebrow)
            <span class="laradocs-page-eyebrow">{{ $eyebrow }}</span>
        @endif
        <h1 class="laradocs-page-title">{{ $document->title() }}</h1>
        @if($document->metadata->description)
            <p class="laradocs-page-description">{{ $document->metadata->description }}</p>
        @endif
    </header>

    @include('laradocs::partials.toc-mobile', ['toc' => $toc])

    @php
        // If the body opens with an <h1> whose text matches the page title,
        // strip it to avoid duplicating the page-header title above. The
        // rendered heading may include a permalink anchor whose "#" text
        // leaks into strip_tags, so trim it before comparing.
        $html = (string) $document->html;
        if (preg_match('~^\s*<h1\b[^>]*>(?<inner>.*?)</h1>\s*~is', $html, $m)) {
            $bodyTitle = trim(strip_tags(html_entity_decode($m['inner'], ENT_QUOTES | ENT_HTML5)));
            $bodyTitle = trim(ltrim($bodyTitle, '#'));
            if (mb_strtolower($bodyTitle) === mb_strtolower($document->title())) {
                $html = (string) preg_replace('~^\s*<h1\b[^>]*>.*?</h1>\s*~is', '', $html, 1);
            }
        }
    @endphp
    <article class="laradocs-prose">
        {!! $html !!}
    </article>

    @php
        $editTemplate = config('laradocs.ui.edit.url');
        $updatedAt = $document->metadata->updatedAt;
    @endphp
    @if($editTemplate || $updatedAt)
        <div class="laradocs-page-meta">
            @if($editTemplate)
                @include('laradocs::partials.edit-link', ['document' => $document])
            @endif
            @if($updatedAt)
                <span>Last updated {{ $updatedAt }}</span>
            @endif
        </div>
    @endif

    @if($previous || $next)
        <nav class="laradocs-pager" aria-label="Pagination">
            @if($previous)
                <a class="prev" href="{{ DocumentUrl::toSlug($previous->slug) }}">
                    <span>Previous</span>{{ $previous->title }}
                </a>
            @endif
            @if($next)
                <a class="next" href="{{ DocumentUrl::toSlug($next->slug) }}">
                    <span>Next</span>{{ $next->title }}
                </a>
            @endif
        </nav>
    @endif
@endsection

@section('toc')
    @include('laradocs::partials.toc', ['toc' => $toc])
@endsection
