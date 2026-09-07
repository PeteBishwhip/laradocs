{{--
    The documentation assistant, droppable anywhere in the host application:

        <x-laradocs::ai-chat />

    On a docs page the layout includes the widget already, so reach for this
    component off the docs instead: a dashboard, a support page, a marketing
    site sharing the application. It brings its own stylesheet and script,
    which are the widget's alone and restyle nothing else on the page, and
    renders nothing at all while the assistant is switched off.

    Pass `:assets="false"` when your own build bundles them instead.
--}}
@use('Laradocs\Ai\ChatService')
@use('Laradocs\Routing\DocumentUrl')
@props(['assets' => true])
@if(app(ChatService::class)->available())
    @php $withAssets = filter_var($assets, FILTER_VALIDATE_BOOLEAN); @endphp
    @if($withAssets)
        <link rel="stylesheet" href="{{ DocumentUrl::asset('laradocs-ai.css') }}">
    @endif

    @include('laradocs::partials.ai-chat')

    @if($withAssets)
        <script src="{{ DocumentUrl::asset('laradocs-ai.js') }}" defer></script>
    @endif
@endif
