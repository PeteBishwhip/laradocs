@php
    $versions = \Laradocs\Support\Version::available();
    $currentVersion = \Laradocs\Support\Version::current();
@endphp

{{-- Only render when there is more than one version to choose from. --}}
@if((bool) config('laradocs.versions.selector', true) && count($versions) > 1 && $currentVersion !== null)
    <details class="laradocs-version" data-laradocs-version>
        <summary class="laradocs-icon-btn laradocs-version-btn" aria-label="{{ __('laradocs::laradocs.version.label') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 2 2 7l10 5 10-5-10-5z"/>
                <path d="M2 17l10 5 10-5"/>
                <path d="M2 12l10 5 10-5"/>
            </svg>
            <span class="laradocs-version-current">{{ $versions[$currentVersion] ?? $currentVersion }}</span>
        </summary>
        <ul class="laradocs-version-menu" role="menu" aria-label="{{ __('laradocs::laradocs.version.select') }}">
            @foreach($versions as $handle => $label)
                <li role="none">
                    <a role="menuitem"
                       href="{{ \Laradocs\Routing\DocumentUrl::forVersion($activeSlug ?? '', $handle) }}"
                       @if($handle === $currentVersion) aria-current="true" class="is-active" @endif>
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </details>
@endif
