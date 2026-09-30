<?php

namespace App\Support;

use App\Models\Setting;

class SettingValue
{
    public static function error(Setting $setting, mixed $value): ?string
    {
        return match ($setting->data_type) {
            'integer' => self::integerError($value),
            'boolean' => self::booleanError($value),
            'decimal' => self::decimalError($value),
            'json' => self::jsonError($value),
            default => self::stringError($setting, $value),
        };
    }

    public static function store(Setting $setting, mixed $value): string
    {
        return match ($setting->data_type) {
            'integer' => (string) self::integer($value),
            'boolean' => self::boolean($value) ? 'true' : 'false',
            'decimal' => trim((string) $value),
            'json' => self::jsonStore($value),
            default => trim((string) $value),
        };
    }

    public static function typed(Setting $setting): mixed
    {
        return match ($setting->data_type) {
            'integer' => (int) $setting->value,
            'boolean' => in_array($setting->value, ['true', '1'], true),
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    private static function integerError(mixed $value): ?string
    {
        $integer = self::integer($value);

        if ($integer === null || $integer < 1) {
            return 'Enter a whole number of 1 or more.';
        }

        return null;
    }

    private static function booleanError(mixed $value): ?string
    {
        return self::boolean($value) === null ? 'Choose on or off.' : null;
    }

    private static function decimalError(mixed $value): ?string
    {
        if (is_bool($value) || is_array($value) || ! is_numeric($value)) {
            return 'Enter a number.';
        }

        return null;
    }

    private static function jsonError(mixed $value): ?string
    {
        if (is_array($value)) {
            return null;
        }

        if (! is_string($value) || json_decode($value, true) === null && json_last_error() !== JSON_ERROR_NONE) {
            return 'Enter valid JSON.';
        }

        return null;
    }

    private static function stringError(Setting $setting, mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > 255) {
            return 'Enter a value up to 255 characters.';
        }

        if (str_ends_with($setting->key, '_pattern')) {
            return NumberPattern::error(trim($value));
        }

        return null;
    }

    private static function jsonStore(mixed $value): string
    {
        $encoded = json_encode(is_string($value) ? json_decode($value, true) : $value);

        return is_string($encoded) ? $encoded : '{}';
    }

    private static function integer(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private static function boolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return null;
    }
}
