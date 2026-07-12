@extends('laradocs::layout')


@section('title', $title)
@section('description', __('laradocs::laradocs.tags.index_intro'))

@section('content')
    <header class="laradocs-page-header">
        <span class="laradocs-page-eyebrow">{{ __('laradocs::laradocs.tags.eyebrow') }}</span>
        <h1 class="laradocs-page-title">{{ $title }}</h1>
        <p class="laradocs-page-description">{{ __('laradocs::laradocs.tags.index_intro') }}</p>
    </header>

    @if($tags->isEmpty())
        <p class="laradocs-tags-empty">{{ __('laradocs::laradocs.tags.empty') }}</p>
    @else
        <ul class="laradocs-tag-cloud">
            @foreach($tags as $tag)
                <li>
                    <a class="laradocs-tag-chip" href="{{ \Laradocs\Routing\DocumentUrl::tag($tag->slug) }}">
                        <span class="laradocs-tag-chip-label">{{ $tag->label }}</span>
                        <span class="laradocs-tag-chip-count" aria-hidden="true">{{ $tag->count() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
