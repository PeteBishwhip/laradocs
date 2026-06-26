@php
    $template = config('laradocs.ui.edit.url');
    $label = __('laradocs::laradocs.page.edit');

    // {file} — the real path on disk relative to docs.path, with its actual
    //          extension (handles section _index files and any custom
    //          extension declared in docs.extensions).
    // {path} — the same path with its extension stripped (back-compat with
    //          templates that hard-code `.md` after the placeholder).
    // {ext}  — just the file extension (no leading dot).
    // Synthetic documents (e.g. OpenAPI operation pages) encode their logical
    // identity as a `#fragment` (and `@locale`) suffix on relativePath, e.g.
    // `api/openapi.yaml#get-pets@en`. Strip everything from the first `#`
    // onward so the edit link points at the real spec file, not a broken
    // `…/openapi.yaml#op` URL.
    $relative = ltrim((string) ($document->relativePath ?? ''), '/');
    if (($hash = strpos($relative, '#')) !== false) {
        $relative = substr($relative, 0, $hash);
    }
    $file = $relative !== '' ? $relative : 'index.md';

    $info = pathinfo($file);
    $ext = $info['extension'] ?? '';
    $dir = ($info['dirname'] ?? '.') !== '.' ? $info['dirname'] . '/' : '';
    $path = $dir . ($info['filename'] ?? 'index');

    $url = null;
    if ($template) {
        $url = strtr((string) $template, [
            '{file}' => $file,
            '{path}' => $path,
            '{ext}'  => $ext,
        ]);
    }
@endphp
@if($url)
    <a class="laradocs-edit-link" href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a>
@endif
