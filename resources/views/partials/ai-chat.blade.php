@use('Laradocs\Ai\ChatService')
@use('Laradocs\Routing\DocumentUrl')
@use('Laradocs\Support\Locale')
@use('Laradocs\Support\Version')
@php
    $chat = app(ChatService::class);
@endphp
@if($chat->available())
    @php
        $position = config('laradocs.ai.widget.position', 'right') === 'left' ? 'left' : 'right';
        $greeting = config('laradocs.ai.widget.greeting') ?: __('laradocs::laradocs.ai.greeting');
        $maxChars = (int) config('laradocs.ai.max_chars', 2000);
        $endpoint = DocumentUrl::aiChat();
        $lang = Locale::segment();

        if ($lang !== null) {
            $endpoint .= '?lang=' . $lang;
        }

        // The endpoint is a POST inside the docs' middleware group, which
        // carries CSRF verification by default. The token travels on the
        // element rather than in a page-wide meta tag so the component works
        // wherever it is dropped, and is omitted outright when the host app
        // has no session for it to come from.
        $token = app()->bound('session') ? csrf_token() : null;

        // The widget's own strings, handed to the script rather than hard
        // coded in it, so a translated docs site translates the assistant too.
        $strings = [
            'thinking' => __('laradocs::laradocs.ai.thinking'),
            'searching' => __('laradocs::laradocs.ai.searching'),
            'error' => __('laradocs::laradocs.ai.error'),
            'rate_limited' => __('laradocs::laradocs.ai.rate_limited'),
            'unauthorised' => __('laradocs::laradocs.ai.unauthorised'),
            'unavailable' => __('laradocs::laradocs.ai.unavailable'),
            'greeting' => $greeting,
        ];
    @endphp
    <div class="laradocs-ai"
         data-laradocs-ai
         data-laradocs-ai-url="{{ $endpoint }}"
         data-laradocs-ai-stream="{{ $chat->streams() ? '1' : '0' }}"
         data-laradocs-ai-max="{{ $maxChars }}"
         data-laradocs-ai-history="{{ (int) config('laradocs.ai.history', 10) }}"
         @if(Version::current() !== null) data-laradocs-ai-version="{{ Version::current() }}" @endif
         @if($token !== null) data-laradocs-ai-token="{{ $token }}" @endif
         data-laradocs-ai-strings="{{ json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
         data-position="{{ $position }}">
        <button type="button" class="laradocs-ai-launcher" data-laradocs-ai-open
                aria-label="{{ __('laradocs::laradocs.ai.open') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
            </svg>
            <span>{{ __('laradocs::laradocs.ai.launcher') }}</span>
        </button>

        <div class="laradocs-ai-panel" data-laradocs-ai-panel hidden role="dialog" aria-modal="false"
             aria-label="{{ __('laradocs::laradocs.ai.label') }}">
            <div class="laradocs-ai-head">
                <span class="laradocs-ai-title">{{ __('laradocs::laradocs.ai.title') }}</span>
                <button type="button" class="laradocs-ai-icon" data-laradocs-ai-reset
                        aria-label="{{ __('laradocs::laradocs.ai.reset') }}" title="{{ __('laradocs::laradocs.ai.reset') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/>
                    </svg>
                </button>
                <button type="button" class="laradocs-ai-icon" data-laradocs-ai-close
                        aria-label="{{ __('laradocs::laradocs.ai.close') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="laradocs-ai-thread" data-laradocs-ai-thread aria-live="polite" aria-atomic="false">
                <div class="laradocs-ai-message is-assistant">
                    <div class="laradocs-ai-bubble">{{ $greeting }}</div>
                </div>
            </div>

            <p class="laradocs-ai-disclaimer">{{ __('laradocs::laradocs.ai.disclaimer') }}</p>

            <form class="laradocs-ai-form" data-laradocs-ai-form>
                <label class="laradocs-ai-sr" for="laradocs-ai-input">{{ __('laradocs::laradocs.ai.placeholder') }}</label>
                <textarea id="laradocs-ai-input" data-laradocs-ai-input rows="1"
                          maxlength="{{ $maxChars }}"
                          placeholder="{{ __('laradocs::laradocs.ai.placeholder') }}"></textarea>
                <button type="submit" class="laradocs-ai-send" data-laradocs-ai-send
                        aria-label="{{ __('laradocs::laradocs.ai.send') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
@endif
