@php
    $template = config('laradocs.ui.edit.url');
    $label = config('laradocs.ui.edit.label') ?? __('laradocs::laradocs.page.edit');

    // {file} — the real path on disk relative to docs.path, with its actual
    //          extension (handles section _index files and any custom
    //          extension declared in docs.extensions).
    // {path} — the same path with its extension stripped (back-compat with
    //          templates that hard-code `.md` after the placeholder).
    // {ext}  — just the file extension (no leading dot).
    $relative = ltrim((string) ($document->relativePath ?? ''), '/');
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
