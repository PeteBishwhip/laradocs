<?php

declare(strict_types=1);

namespace Laradocs\OpenApi\Generator;

use Illuminate\Support\Str;

/**
 * Translates a single field's Laravel validation ruleset (the `string|array`
 * value of one `rules()` entry) into a JSON Schema fragment plus a `required`
 * flag.
 *
 * Only the rules that carry schema meaning are mapped; unknown rules are
 * ignored rather than guessed at, so the output stays conservative and valid.
 */
final class RuleMapper
{
    /**
     * @param  string|array<array-key, mixed>  $rules
     * @return array{schema: array<string, mixed>, required: bool}
     */
    public function map($rules): array
    {
        $tokens = $this->tokens($rules);

        $schema = [];
        $required = false;
        $nullable = false;

        foreach ($tokens as $token) {
            [$name, $argument] = $this->split($token);

            switch ($name) {
                case 'required':
                    $required = true;
                    break;
                case 'nullable':
                    $nullable = true;
                    break;
                case 'string':
                    $schema['type'] = 'string';
                    break;
                case 'integer':
                case 'int':
                    $schema['type'] = 'integer';
                    break;
                case 'numeric':
                    $schema['type'] = $schema['type'] ?? 'number';
                    break;
                case 'boolean':
                case 'bool':
                    $schema['type'] = 'boolean';
                    break;
                case 'array':
                    $schema['type'] = 'array';
                    break;
                case 'email':
                    $schema['type'] = 'string';
                    $schema['format'] = 'email';
                    break;
                case 'url':
                    $schema['type'] = 'string';
                    $schema['format'] = 'uri';
                    break;
                case 'uuid':
                    $schema['type'] = 'string';
                    $schema['format'] = 'uuid';
                    break;
                case 'ulid':
                    $schema['type'] = 'string';
                    break;
                case 'date':
                    $schema['type'] = 'string';
                    $schema['format'] = 'date-time';
                    break;
                case 'in':
                    $enum = $this->enumValues($argument);

                    if ($enum !== []) {
                        $schema['enum'] = $enum;
                    }
                    break;
                case 'min':
                    $this->applyBound($schema, $argument, 'min');
                    break;
                case 'max':
                    $this->applyBound($schema, $argument, 'max');
                    break;
                default:
                    break;
            }
        }

        if (! isset($schema['type'])) {
            $schema['type'] = 'string';
        }

        if ($nullable) {
            $schema['nullable'] = true;
        }

        // Stable key order keeps the dumped YAML diff-friendly.
        $ordered = array_merge(['type' => $schema['type']], $schema);

        return ['schema' => $ordered, 'required' => $required];
    }

    /**
     * @param  string|array<array-key, mixed>  $rules
     * @return array<int, string>
     */
    private function tokens($rules): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $tokens = [];

        foreach ($rules as $rule) {
            // Object rules (Rule::in(), Enum, etc.) carry no parseable string.
            if (is_string($rule) && $rule !== '') {
                $tokens[] = $rule;
            }
        }

        return $tokens;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $token): array
    {
        $name = $token;
        $argument = '';

        if (strpos($token, ':') !== false) {
            [$name, $argument] = explode(':', $token, 2);
        }

        return [strtolower(trim($name)), $argument];
    }

    /**
     * @return array<int, string>
     */
    private function enumValues(string $argument): array
    {
        $values = [];

        foreach (explode(',', $argument) as $value) {
            $value = trim($value);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function applyBound(array &$schema, string $argument, string $bound): void
    {
        if (! is_numeric($argument)) {
            return;
        }

        $type = $schema['type'] ?? 'string';

        if ($type === 'integer' || $type === 'number') {
            $schema[$bound === 'min' ? 'minimum' : 'maximum'] = $type === 'integer'
                ? (int) $argument
                : (float) $argument;

            return;
        }

        if ($type === 'array') {
            $schema[$bound === 'min' ? 'minItems' : 'maxItems'] = (int) $argument;

            return;
        }

        // Default to string length bounds.
        $schema[$bound === 'min' ? 'minLength' : 'maxLength'] = (int) $argument;
    }

    /**
     * Derive a JSON Schema property name from a Laravel field key, flattening
     * dotted/array notation to its leading segment.
     */
    public function propertyName(string $field): string
    {
        return (string) Str::of($field)->before('.')->before('*');
    }
}
