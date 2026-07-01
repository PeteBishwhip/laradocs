{{--
    Bulk expand / collapse controls for a section's schema branches. The JS
    (initSchemaToggle) opens or closes every <details> inside the button's
    nearest <section>. Hidden via CSS when the section has no collapsible
    branch, so it never dangles uselessly.
--}}
<div class="laradocs-openapi-toolbar" role="group" aria-label="{{ __('laradocs::laradocs.openapi.responses') }}">
    <button type="button" class="laradocs-openapi-tool" data-laradocs-schema-toggle="expand">{{ __('laradocs::laradocs.openapi.expand_all') }}</button>
    <span class="laradocs-openapi-tool-sep" aria-hidden="true"></span>
    <button type="button" class="laradocs-openapi-tool" data-laradocs-schema-toggle="collapse">{{ __('laradocs::laradocs.openapi.collapse_all') }}</button>
</div>
