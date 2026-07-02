{{--
    Renders an endpoint path with its {param} placeholders tinted distinctly
    from the static segments. Expects a $path string. The markup is built in PHP
    so no stray whitespace leaks between adjacent inline segments.
--}}
@php
    $segments = preg_split('/(\{[^}]+\})/', (string) $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [(string) $path];
    $rendered = '';
    foreach ($segments as $segment) {
        $isParam = strlen($segment) > 1 && $segment[0] === '{' && substr($segment, -1) === '}';
        $class = $isParam ? 'laradocs-openapi-path-param' : 'laradocs-openapi-path-static';
        $rendered .= '<span class="' . $class . '">' . e($segment) . '</span>';
    }
@endphp
<code class="laradocs-openapi-path">{!! $rendered !!}</code>
