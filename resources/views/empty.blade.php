@extends('laradocs::layout')

@section('title', config('laradocs.ui.brand.title', 'Documentation'))

@section('content')
    <section class="laradocs-empty" aria-labelledby="laradocs-empty-title">
        <span class="laradocs-empty-eyebrow">{{ __('laradocs::laradocs.empty.eyebrow') }}</span>
        <h1 id="laradocs-empty-title">{{ __('laradocs::laradocs.empty.title') }}</h1>
        <p>
            {!! __('laradocs::laradocs.empty.intro', ['path' => '<code>' . e(config('laradocs.docs.path')) . '</code>']) !!}
        </p>

        <div class="laradocs-empty-steps">
            <div class="laradocs-empty-step">
                <span class="num">01</span>
                <div>
                    <strong>{{ __('laradocs::laradocs.empty.step_one_title') }}</strong>
                    {!! __('laradocs::laradocs.empty.step_one_body', ['command' => '<code>php artisan laradocs:install</code>']) !!}
                </div>
            </div>
            <div class="laradocs-empty-step">
                <span class="num">02</span>
                <div>
                    <strong>{{ __('laradocs::laradocs.empty.step_two_title') }}</strong>
                    {!! __('laradocs::laradocs.empty.step_two_body', ['command' => '<code>php artisan make:doc getting-started</code>']) !!}
                </div>
            </div>
            <div class="laradocs-empty-step">
                <span class="num">03</span>
                <div>
                    <strong>{{ __('laradocs::laradocs.empty.step_three_title') }}</strong>
                    {!! __('laradocs::laradocs.empty.step_three_body', ['preset' => '<code>LARADOCS_UI_PRESET=classic|minimal|wide</code>', 'accent' => '<code>LARADOCS_ACCENT</code>']) !!}
                </div>
            </div>
        </div>

        <p>
            <a class="laradocs-button" href="https://github.com/petebishwhip/laradocs" target="_blank" rel="noopener">
                {!! __('laradocs::laradocs.empty.handbook') !!}
            </a>
        </p>
    </section>
@endsection
