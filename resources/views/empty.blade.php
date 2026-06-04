@extends('laradocs::layout')

@section('title', config('laradocs.ui.brand.title', 'Documentation'))

@section('content')
    <section class="laradocs-empty" aria-labelledby="laradocs-empty-title">
        <span class="laradocs-empty-eyebrow">Get started</span>
        <h1 id="laradocs-empty-title">Your documentation, ready when you are.</h1>
        <p>
            Laradocs is wired up and waiting for content. Pages are sourced from
            <code>{{ config('laradocs.docs.path') }}</code>.
        </p>

        <div class="laradocs-empty-steps">
            <div class="laradocs-empty-step">
                <span class="num">01</span>
                <div>
                    <strong>Scaffold a starter page</strong>
                    Run <code>php artisan laradocs:install</code> to drop a welcome page and folder into your docs directory.
                </div>
            </div>
            <div class="laradocs-empty-step">
                <span class="num">02</span>
                <div>
                    <strong>Write your first page</strong>
                    Use <code>php artisan make:doc getting-started</code> to generate a new markdown file with front-matter.
                </div>
            </div>
            <div class="laradocs-empty-step">
                <span class="num">03</span>
                <div>
                    <strong>Tune the look</strong>
                    Switch presets with <code>LARADOCS_UI_PRESET=classic|minimal|wide</code> or tune the accent with <code>LARADOCS_ACCENT</code>.
                </div>
            </div>
        </div>

        <p>
            <a class="laradocs-button" href="https://github.com/petebishwhip/laradocs" target="_blank" rel="noopener">
                Read the handbook →
            </a>
        </p>
    </section>
@endsection
