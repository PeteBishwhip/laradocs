@extends('laradocs::layout')

@use('Laradocs\Routing\DocumentUrl')

@section('title', $title)

@section('content')
    <nav class="laradocs-breadcrumbs" aria-label="{{ __('laradocs::laradocs.nav.breadcrumb') }}">
        <a href="{{ DocumentUrl::index() }}">{{ __('laradocs::laradocs.nav.home') }}</a>
        <span aria-hidden="true">·</span>
        <a href="{{ DocumentUrl::tags() }}">{{ __('laradocs::laradocs.tags.index_title') }}</a>
        <span aria-hidden="true">·</span>
        <span>{{ $tag->label }}</span>
    </nav>

    <header class="laradocs-page-header">
        <span class="laradocs-page-eyebrow">{{ __('laradocs::laradocs.tags.eyebrow') }}</span>
        <h1 class="laradocs-page-title">{{ $title }}</h1>
        <p class="laradocs-page-description">
            {{ trans_choice('laradocs::laradocs.tags.count', $tag->count(), ['count' => $tag->count()]) }}
        </p>
    </header>

    <ul class="laradocs-tag-pages">
        @foreach($tag->documents as $document)
            <li>
                <a class="laradocs-tag-page" href="{{ DocumentUrl::toSlug($document->slug) }}">
                    <span class="laradocs-tag-page-title">{{ $document->title() }}</span>
                    @if($document->metadata->description)
                        <span class="laradocs-tag-page-desc">{{ $document->metadata->description }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    <p class="laradocs-tag-back">
        <a href="{{ DocumentUrl::tags() }}">← {{ __('laradocs::laradocs.tags.all') }}</a>
    </p>
@endsection
