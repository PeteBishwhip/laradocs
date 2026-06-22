@use('Laradocs\Routing\DocumentUrl')
@use('Laradocs\Support\Version')
@php
    $versionsEnabled = (bool) config('laradocs.versions.enabled', false);
    $outdatedEnabled = (bool) config('laradocs.versions.outdated_banner', true);

    $currentVersion = Version::current();
    $defaultVersion = Version::default();
    $currentInfo = $currentVersion !== null ? Version::info($currentVersion) : null;

    // A page may opt out of the banner with `version_banner: false` front-matter.
    $bannerMeta = isset($document) ? $document->metadata->get('version_banner', true) : true;
    $suppressed = $bannerMeta === false
        || (is_string($bannerMeta) && in_array(strtolower(trim($bannerMeta)), ['false', '0', 'no', 'off'], true));

    $showOutdated = $versionsEnabled
        && $outdatedEnabled
        && $currentVersion !== null
        && $defaultVersion !== null
        && $currentVersion !== $defaultVersion
        && ! $suppressed
        && ! ($currentInfo?->hidden ?? false);

    $currentLabel = $currentInfo?->label ?? $currentVersion;
    $deprecatedMessage = $currentInfo?->deprecatedMessage;
@endphp

@if($showOutdated)
    @php($currentUrl = DocumentUrl::forVersion(isset($document) ? $document->slug : '', $defaultVersion))
    <div class="laradocs-banner laradocs-version-outdated" role="alert">
        <div class="laradocs-banner-inner">
            @if($deprecatedMessage !== null && $deprecatedMessage !== '')
                {{ $deprecatedMessage }}
            @else
                {{ __('laradocs::laradocs.version.outdated.viewing', ['version' => $currentLabel]) }}
            @endif
            <a href="{{ $currentUrl }}">{{ __('laradocs::laradocs.version.outdated.link') }}</a>
        </div>
        <button type="button"
                class="laradocs-version-outdated-dismiss"
                data-laradocs-dismiss-version-banner
                aria-label="{{ __('laradocs::laradocs.version.outdated.dismiss') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 6 6 18M6 6l12 12"/>
            </svg>
        </button>
    </div>
@endif
