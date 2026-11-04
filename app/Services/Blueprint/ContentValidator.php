<?php

namespace App\Services\Blueprint;

/**
 * Validates section content against a registry prop schema. Strict by design:
 * unknown props, markup in text, unsafe URLs and unknown icons are all errors —
 * content is plain data, never markup or code (PRD v1.5 model_output_rules).
 */
class ContentValidator
{
    private const SAFE_HREF = '/^(#[\w-]*|\/[^\s]*|https:\/\/[^\s]+|mailto:[^\s]+|tel:[+\d\s()-]+)$/i';

    /** @return string[] error messages, empty when valid */
    public function validate(array $schema, mixed $content, string $path = ''): array
    {
        if (! is_array($content)) {
            return ["{$path}: expected an object"];
        }

        $errors = [];
        foreach (array_diff_key($content, $schema) as $key => $_) {
            $errors[] = $this->p($path, $key).': unknown property';
        }
        foreach ($schema as $key => $rule) {
            $value = $content[$key] ?? null;
            if ($value === null || $value === '' || $value === []) {
                if ($rule['required'] ?? false) {
                    $errors[] = $this->p($path, $key).': required content is missing';
                }

                continue;
            }
            array_push($errors, ...$this->validateValue($rule, $value, $this->p($path, $key)));
        }

        return $errors;
    }

    private function validateValue(array $rule, mixed $value, string $path): array
    {
        return match ($rule['type']) {
            'string', 'text' => $this->validateString($rule, $value, $path),
            'href' => $this->validateHref($value, $path),
            'mailto' => is_string($value) && preg_match('/^mailto:[^\s@]+@[^\s@]+\.[^\s@]+$/i', $value) ? [] : ["{$path}: must be a mailto: address"],
            'cta' => $this->validate(['label' => ['type' => 'string', 'required' => true, 'max' => 50], 'href' => ['type' => 'href', 'required' => true]], $value, $path),
            'image' => $this->validate(['asset_id' => ['type' => 'string', 'max' => 64], 'alt' => ['type' => 'string', 'required' => true, 'max' => 200]], $value, $path),
            'icon' => in_array($value, config('design_allowlist.icons'), true) ? [] : ["{$path}: icon not in allowlist"],
            'bool' => is_bool($value) ? [] : ["{$path}: expected boolean"],
            'enum' => in_array($value, $rule['values'], true) ? [] : ["{$path}: must be one of ".implode(', ', $rule['values'])],
            'strlist' => $this->validateStrList($rule, $value, $path),
            'list' => $this->validateList($rule, $value, $path),
            default => ["{$path}: unsupported schema type {$rule['type']}"],
        };
    }

    private function validateString(array $rule, mixed $value, string $path): array
    {
        if (! is_string($value)) {
            return ["{$path}: expected text"];
        }
        if ($value !== strip_tags($value) || preg_match('/[<>]/', $value)) {
            return ["{$path}: markup is not allowed"];
        }
        if (mb_strlen($value) > ($rule['max'] ?? 200)) {
            return ["{$path}: exceeds ".($rule['max'] ?? 200).' characters'];
        }

        return [];
    }

    private function validateHref(mixed $value, string $path): array
    {
        return is_string($value) && preg_match(self::SAFE_HREF, $value) ? [] : ["{$path}: unsafe or malformed link"];
    }

    private function validateStrList(array $rule, mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return ["{$path}: expected a list"];
        }
        $errors = count($value) > ($rule['max'] ?? 10) ? ["{$path}: too many items"] : [];
        foreach ($value as $i => $v) {
            array_push($errors, ...$this->validateString(['max' => 160], $v, "{$path}[{$i}]"));
        }

        return $errors;
    }

    private function validateList(array $rule, mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return ["{$path}: expected a list"];
        }
        $errors = [];
        if (count($value) < ($rule['min'] ?? 0)) {
            $errors[] = "{$path}: needs at least {$rule['min']} items";
        }
        if (count($value) > ($rule['max'] ?? 12)) {
            $errors[] = "{$path}: at most {$rule['max']} items";
        }
        foreach ($value as $i => $item) {
            array_push($errors, ...$this->validate($rule['items'], $item, "{$path}[{$i}]"));
        }

        return $errors;
    }

    private function p(string $path, string|int $key): string
    {
        return $path === '' ? (string) $key : "{$path}.{$key}";
    }
}
