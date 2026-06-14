@php($languages = \Laradocs\Support\Locale::available())
@php($current = app()->getLocale())

{{-- Only render once there is a genuine choice to make. --}}
@if((bool) config('laradocs.locale.selector', true) && count($languages) > 1)
    <details class="laradocs-lang" data-laradocs-lang>
        <summary class="laradocs-icon-btn" aria-label="{{ __('laradocs::laradocs.language.label') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/>
                <path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>
            </svg>
            <span class="laradocs-lang-current">{{ $languages[$current] ?? strtoupper($current) }}</span>
        </summary>
        <ul class="laradocs-lang-menu" role="menu" aria-label="{{ __('laradocs::laradocs.language.select') }}">
            @foreach($languages as $code => $label)
                <li role="none">
                    <a role="menuitem"
                       hreflang="{{ $code }}"
                       href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                       @if($code === $current) aria-current="true" class="is-active" @endif>
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </details>
@endif
