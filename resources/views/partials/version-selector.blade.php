@php
    $versions = \Laradocs\Support\Version::available();
    $currentVersion = \Laradocs\Support\Version::current();
    $latestVersion = \Laradocs\Support\Version::latest();
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
                @php($info = \Laradocs\Support\Version::info($handle))
                <li role="none">
                    <a role="menuitem"
                       href="{{ \Laradocs\Routing\DocumentUrl::forVersion($activeSlug ?? '', $handle) }}"
                       @if($handle === $currentVersion) aria-current="true" class="is-active" @endif>
                        {{ $label }}
                        @if($handle === $latestVersion)
                            <span class="laradocs-version-badge laradocs-version-badge--latest">{{ __('laradocs::laradocs.version.badge.latest') }}</span>
                        @endif
                        @if($info !== null && $info->deprecated)
                            <span class="laradocs-version-badge laradocs-version-badge--deprecated">{{ __('laradocs::laradocs.version.badge.deprecated') }}</span>
                        @endif
                        @if($info !== null && $info->preRelease)
                            <span class="laradocs-version-badge laradocs-version-badge--pre-release">{{ __('laradocs::laradocs.version.badge.pre_release') }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </details>
@endif
