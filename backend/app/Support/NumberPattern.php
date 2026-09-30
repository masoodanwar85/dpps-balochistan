<?php

namespace App\Support;

class NumberPattern
{
    public const SAMPLE_SERIAL = 46;

    public const SAMPLE_DISTRICT = 'QTA';

    public const SAMPLE_RENEWAL = '/R1';

    public const SAMPLE_PARTY = 'C';

    public static function error(string $pattern): ?string
    {
        $stripped = preg_replace('/\{(?:YYYY|DISTRICT|RENEWAL|C\/D|SERIAL:[1-8])\}/', '', $pattern);

        if (! is_string($stripped) || preg_match('/[{}]/', $stripped) === 1) {
            return 'The pattern has an unknown placeholder.';
        }

        return null;
    }

    public static function preview(string $pattern, ?int $year = null): string
    {
        $year ??= (int) now()->year;

        $rendered = preg_replace_callback('/\{([^{}]+)\}/', function (array $match) use ($year): string {
            $token = $match[1];

            if ($token === 'YYYY') {
                return (string) $year;
            }

            if ($token === 'DISTRICT') {
                return self::SAMPLE_DISTRICT;
            }

            if ($token === 'RENEWAL') {
                return self::SAMPLE_RENEWAL;
            }

            if ($token === 'C/D') {
                return self::SAMPLE_PARTY;
            }

            if (preg_match('/^SERIAL:([1-8])$/', $token, $serial) === 1) {
                return str_pad((string) self::SAMPLE_SERIAL, (int) $serial[1], '0', STR_PAD_LEFT);
            }

            return $match[0];
        }, $pattern);

        return is_string($rendered) ? $rendered : $pattern;
    }
}
