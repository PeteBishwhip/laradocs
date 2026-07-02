<?php

declare(strict_types=1);

namespace Laradocs\OpenApi;

/**
 * Builds copy-pasteable request snippets for an operation in several languages
 * (cURL, PHP, JavaScript, Python, Ruby), plus an example JSON response body.
 *
 * Everything is derived from primitives — method, URL, headers and a resolved
 * request-body schema node (as produced by {@see SchemaRenderer}) — so the
 * class is pure and unit-testable without a live spec. Example values are
 * synthesised from each schema's type/format/enum; path parameters are left as
 * their `{placeholder}` so the reader can see exactly what to substitute.
 */
final class CodeSampleBuilder
{
    private const TOKEN = 'YOUR_TOKEN';

    /**
     * @param  array<string, mixed>|null  $requestSchema  Resolved request-body schema node.
     * @return array<string, string> Language label => source snippet.
     */
    public function forOperation(string $method, string $url, ?array $requestSchema): array
    {
        $method = strtoupper($method);
        $body = $requestSchema === null ? null : $this->example($requestSchema, 0);
        $hasBody = $body !== null && $method !== 'GET' && $method !== 'HEAD';

        return [
            'cURL' => $this->curl($method, $url, $hasBody ? $body : null),
            'PHP' => $this->php($method, $url, $hasBody ? $body : null),
            'JavaScript' => $this->javascript($method, $url, $hasBody ? $body : null),
            'Python' => $this->python($method, $url, $hasBody ? $body : null),
            'Ruby' => $this->ruby($method, $url, $hasBody ? $body : null),
        ];
    }

    /**
     * A pretty-printed example JSON response body, or null when the response
     * carries no (object/array) schema worth showing.
     *
     * @param  array<string, mixed>|null  $responseSchema
     */
    public function responseJson(?array $responseSchema): ?string
    {
        if ($responseSchema === null) {
            return null;
        }

        $example = $this->example($responseSchema, 0);

        if ($example === null || $example === []) {
            return null;
        }

        return $this->render($example, 'json', 0);
    }

    // ── snippet templates ─────────────────────────────────────────────────────

    private function curl(string $method, string $url, mixed $body): string
    {
        $lines = ['curl -X ' . $method . ' "' . $url . '" \\'];
        $lines[] = '  -H "Authorization: Bearer ' . self::TOKEN . '" \\';
        $lines[] = '  -H "Accept: application/json"' . ($body !== null ? ' \\' : '');

        if ($body !== null) {
            $lines[] = '  -H "Content-Type: application/json" \\';
            $lines[] = "  -d '" . $this->render($body, 'json', 1) . "'";
        }

        return implode("\n", $lines);
    }

    private function php(string $method, string $url, mixed $body): string
    {
        $call = strtolower($method);
        $out = "use Illuminate\\Support\\Facades\\Http;\n\n";
        $out .= '$response = Http::withToken(\'' . self::TOKEN . "')\n";
        $out .= "    ->acceptJson()\n";

        if ($body !== null) {
            return $out . "    ->{$call}('{$url}', " . $this->render($body, 'php', 1) . ');';
        }

        return $out . "    ->{$call}('{$url}');";
    }

    private function javascript(string $method, string $url, mixed $body): string
    {
        $out = "const response = await fetch(\"{$url}\", {\n";
        $out .= '  method: "' . $method . "\",\n";
        $out .= "  headers: {\n";
        $out .= '    "Authorization": "Bearer ' . self::TOKEN . "\",\n";
        $out .= '    "Accept": "application/json",' . "\n";

        if ($body !== null) {
            $out .= '    "Content-Type": "application/json",' . "\n";
            $out .= "  },\n";
            $out .= '  body: JSON.stringify(' . $this->render($body, 'json', 1) . "),\n";

            return $out . '});';
        }

        return $out . "  },\n});";
    }

    private function python(string $method, string $url, mixed $body): string
    {
        $call = strtolower($method);
        $out = "import requests\n\n";
        $out .= "response = requests.{$call}(\n";
        $out .= "    \"{$url}\",\n";
        $out .= "    headers={\n";
        $out .= '        "Authorization": "Bearer ' . self::TOKEN . "\",\n";
        $out .= '        "Accept": "application/json",' . "\n";

        if ($body !== null) {
            $out .= '        "Content-Type": "application/json",' . "\n";
            $out .= "    },\n";
            $out .= '    json=' . $this->render($body, 'python', 1) . ",\n";

            return $out . ')';
        }

        return $out . "    },\n)";
    }

    private function ruby(string $method, string $url, mixed $body): string
    {
        $class = ucfirst(strtolower($method));
        $out = "require \"net/http\"\nrequire \"json\"\nrequire \"uri\"\n\n";
        $out .= "uri = URI(\"{$url}\")\n";
        $out .= "http = Net::HTTP.new(uri.host, uri.port)\n";
        $out .= "http.use_ssl = true\n\n";
        $out .= "request = Net::HTTP::{$class}.new(uri)\n";
        $out .= 'request["Authorization"] = "Bearer ' . self::TOKEN . "\"\n";
        $out .= 'request["Accept"] = "application/json"' . "\n";

        if ($body !== null) {
            $out .= 'request["Content-Type"] = "application/json"' . "\n";
            $out .= 'request.body = ' . $this->render($body, 'ruby', 0) . ".to_json\n\n";
            $out .= 'response = http.request(request)';

            return $out;
        }

        return $out . "\nresponse = http.request(request)";
    }

    // ── example synthesis ─────────────────────────────────────────────────────

    /**
     * Synthesise a representative example value for a resolved schema node.
     *
     * @param  array<array-key, mixed>  $node
     */
    private function example(array $node, int $depth): mixed
    {
        if ($depth > 4) {
            return null;
        }

        if (! empty($node['enum']) && is_array($node['enum'])) {
            return $node['enum'][0];
        }

        foreach (['oneOf', 'anyOf'] as $key) {
            if (! empty($node[$key]) && is_array($node[$key]) && isset($node[$key][0]) && is_array($node[$key][0])) {
                return $this->example($node[$key][0], $depth);
            }
        }

        $type = $node['type'] ?? (! empty($node['properties']) ? 'object' : (! empty($node['items']) ? 'array' : 'string'));

        return match ($type) {
            'object' => $this->objectExample($node, $depth),
            'array' => [$this->example(is_array($node['items'] ?? null) ? $node['items'] : [], $depth + 1)],
            'integer', 'number' => 0,
            'boolean' => true,
            default => $this->stringExample($node),
        };
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return array<string, mixed>
     */
    private function objectExample(array $node, int $depth): array
    {
        $out = [];

        if (! empty($node['properties']) && is_array($node['properties'])) {
            foreach ($node['properties'] as $name => $property) {
                $schema = is_array($property) && is_array($property['schema'] ?? null) ? $property['schema'] : [];
                $out[(string) $name] = $this->example($schema, $depth + 1);
            }
        }

        return $out;
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private function stringExample(array $node): string
    {
        return match ($node['format'] ?? '') {
            'date-time' => '2024-01-01T00:00:00Z',
            'date' => '2024-01-01',
            'email' => 'user@example.com',
            'uuid' => '00000000-0000-0000-0000-000000000000',
            'uri', 'url' => 'https://example.com',
            default => 'string',
        };
    }

    // ── literal rendering ─────────────────────────────────────────────────────

    /**
     * Render a value as a literal in the target language's object/dict/hash/JSON
     * syntax, indented from the given base depth (two spaces per level).
     */
    private function render(mixed $value, string $style, int $indent): string
    {
        $s = $this->style($style);
        $pad = str_repeat('  ', $indent);
        $inner = str_repeat('  ', $indent + 1);

        if (is_array($value) && $this->isList($value)) {
            $items = array_map(fn ($v): string => $inner . $this->render($v, $style, $indent + 1), $value);

            return "[\n" . implode(",\n", $items) . "\n" . $pad . ']';
        }

        if (is_array($value)) {
            if ($value === []) {
                return $s['open'] . $s['close'];
            }
            $rows = [];
            foreach ($value as $key => $v) {
                $rows[] = $inner . $s['key']((string) $key) . $s['arrow'] . $this->render($v, $style, $indent + 1);
            }

            return $s['open'] . "\n" . implode(",\n", $rows) . "\n" . $pad . $s['close'];
        }

        if (is_bool($value)) {
            return $value ? $s['true'] : $s['false'];
        }

        if ($value === null) {
            return $s['null'];
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $s['str'](is_scalar($value) ? (string) $value : '');
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function isList(array $value): bool
    {
        return $value === [] ? false : array_is_list($value);
    }

    /**
     * @return array{open: string, close: string, arrow: string, true: string, false: string, null: string, key: callable(string): string, str: callable(string): string}
     */
    private function style(string $style): array
    {
        return match ($style) {
            'php' => [
                'open' => '[', 'close' => ']', 'arrow' => ' => ',
                'true' => 'true', 'false' => 'false', 'null' => 'null',
                'key' => fn (string $k): string => $this->phpString($k),
                'str' => fn (string $v): string => $this->phpString($v),
            ],
            'python' => [
                'open' => '{', 'close' => '}', 'arrow' => ': ',
                'true' => 'True', 'false' => 'False', 'null' => 'None',
                'key' => fn (string $k): string => $this->doubleQuoted($k),
                'str' => fn (string $v): string => $this->doubleQuoted($v),
            ],
            'ruby' => [
                'open' => '{', 'close' => '}', 'arrow' => ' => ',
                'true' => 'true', 'false' => 'false', 'null' => 'nil',
                // Ruby interpolates "#{...}" in double-quoted strings, so neutralise it too.
                'key' => fn (string $k): string => $this->doubleQuoted($k, ['#{' => '\\#{']),
                'str' => fn (string $v): string => $this->doubleQuoted($v, ['#{' => '\\#{']),
            ],
            default => [ // json (also valid JavaScript object-literal syntax)
                'open' => '{', 'close' => '}', 'arrow' => ': ',
                'true' => 'true', 'false' => 'false', 'null' => 'null',
                'key' => fn (string $k): string => $this->jsonString($k),
                'str' => fn (string $v): string => $this->jsonString($v),
            ],
        };
    }

    /**
     * A PHP single-quoted string literal — only `\` and `'` are special inside
     * one, and strtr's single pass escapes both without re-escaping its output.
     */
    private function phpString(string $value): string
    {
        return "'" . strtr($value, ['\\' => '\\\\', "'" => "\\'"]) . "'";
    }

    /**
     * A double-quoted string literal for Python/Ruby: escape the backslash,
     * the delimiter and the control characters that would otherwise terminate
     * or corrupt the line. $extra adds language-specific sequences.
     *
     * @param  array<string, string>  $extra
     */
    private function doubleQuoted(string $value, array $extra = []): string
    {
        return '"' . strtr($value, [
            '\\' => '\\\\', '"' => '\\"',
            "\n" => '\\n', "\r" => '\\r', "\t" => '\\t',
        ] + $extra) . '"';
    }

    /**
     * A JSON string literal (also valid JavaScript). json_encode handles every
     * escape — backslash, quote, control and unicode — so the value can never
     * break out of the literal; fall back to an empty string on invalid UTF-8.
     */
    private function jsonString(string $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '""' : $encoded;
    }
}
