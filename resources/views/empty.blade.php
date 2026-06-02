@extends('laradocs::layout')

@section('title', config('laradocs.ui.brand.title', 'Documentation'))

@section('content')
    <section class="laradocs-empty" aria-labelledby="laradocs-empty-title">
        <span class="laradocs-empty-eyebrow">Get started</span>
        <h1 id="laradocs-empty-title">Your docs live here.</h1>
        <p>
            Laradocs is wired up and ready &mdash; you just need some markdown to read.
            Documents are sourced from
            <code>{{ config('laradocs.docs.path') }}</code>.
        </p>

        <div class="laradocs-empty-steps">
            <div class="laradocs-empty-step">
                <span class="num">1</span>
                <div>
                    <strong>Scaffold a starter page</strong>
                    Run <code>php artisan docs:install</code> to drop a welcome page and folder into your docs directory.
                </div>
            </div>
            <div class="laradocs-empty-step">
                <span class="num">2</span>
                <div>
                    <strong>Create your first page</strong>
                    Use <code>php artisan make:doc getting-started</code> to generate a new markdown file with front-matter.
                </div>
            </div>
            <div class="laradocs-empty-step">
                <span class="num">3</span>
                <div>
                    <strong>Pick your look</strong>
                    Switch presets with <code>LARADOCS_UI_PRESET=classic|minimal|wide</code> or tweak the accent colour via <code>LARADOCS_ACCENT</code>.
                </div>
            </div>
        </div>

        <p>
            <a class="laradocs-button" href="https://github.com/pete/laradocs" target="_blank" rel="noopener">
                Read the documentation
            </a>
        </p>
    </section>
@endsection
