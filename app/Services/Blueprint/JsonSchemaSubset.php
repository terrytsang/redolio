<?php

namespace App\Services\Blueprint;

/**
 * Minimal JSON Schema validator covering exactly the keywords used by
 * database/catalogue/schemas/*.schema.json: type (single or list), enum,
 * required, properties, additionalProperties:false, items, minItems, maxItems,
 * minLength, maxLength, minimum, pattern. Unknown keywords are ignored, so the
 * same file stays usable by a full validator (e.g. Zod codegen in the Next.js compiler).
 */
class JsonSchemaSubset
{
    /** @return string[] */
    public function validate(array $schema, mixed $data, string $path = '$'): array
    {
        $errors = [];

        if (isset($schema['type']) && ! $this->typeMatches((array) $schema['type'], $data)) {
            return ["{$path}: expected ".implode('|', (array) $schema['type']).', got '.gettype($data)];
        }
        if (isset($schema['enum']) && ! in_array($data, $schema['enum'], true)) {
            $errors[] = "{$path}: must be one of ".implode(', ', $schema['enum']);
        }

        if (is_string($data)) {
            isset($schema['minLength']) && mb_strlen($data) < $schema['minLength'] && $errors[] = "{$path}: too short";
            isset($schema['maxLength']) && mb_strlen($data) > $schema['maxLength'] && $errors[] = "{$path}: too long";
            isset($schema['pattern']) && ! preg_match('/'.str_replace('/', '\/', $schema['pattern']).'/', $data) && $errors[] = "{$path}: does not match required pattern";
        }
        if ((is_int($data) || is_float($data)) && isset($schema['minimum']) && $data < $schema['minimum']) {
            $errors[] = "{$path}: below minimum";
        }

        if (is_array($data) && array_is_list($data) && ! isset($schema['properties']) && ! isset($schema['required'])) {
            isset($schema['minItems']) && count($data) < $schema['minItems'] && $errors[] = "{$path}: needs at least {$schema['minItems']} items";
            isset($schema['maxItems']) && count($data) > $schema['maxItems'] && $errors[] = "{$path}: at most {$schema['maxItems']} items";
            if (isset($schema['items'])) {
                foreach ($data as $i => $item) {
                    array_push($errors, ...$this->validate($schema['items'], $item, "{$path}[{$i}]"));
                }
            }
        } elseif (is_array($data) && (isset($schema['properties']) || isset($schema['required']))) {
            foreach ($schema['required'] ?? [] as $req) {
                array_key_exists($req, $data) || $errors[] = "{$path}.{$req}: is required";
            }
            if (($schema['additionalProperties'] ?? true) === false) {
                foreach (array_diff(array_keys($data), array_keys($schema['properties'] ?? [])) as $extra) {
                    $errors[] = "{$path}.{$extra}: unknown property";
                }
            }
            foreach ($schema['properties'] ?? [] as $key => $sub) {
                if (array_key_exists($key, $data)) {
                    array_push($errors, ...$this->validate($sub, $data[$key], "{$path}.{$key}"));
                }
            }
        }

        return $errors;
    }

    private function typeMatches(array $types, mixed $data): bool
    {
        foreach ($types as $t) {
            $ok = match ($t) {
                'string' => is_string($data), 'integer' => is_int($data), 'number' => is_int($data) || is_float($data),
                'boolean' => is_bool($data), 'null' => $data === null, 'array' => is_array($data) && array_is_list($data),
                'object' => is_array($data) && ($data === [] || ! array_is_list($data)),
                default => false,
            };
            if ($ok) {
                return true;
            }
        }
        // An empty PHP array is both an empty JSON array and an empty object.
        return $data === [] && (in_array('array', $types, true) || in_array('object', $types, true));
    }
}
