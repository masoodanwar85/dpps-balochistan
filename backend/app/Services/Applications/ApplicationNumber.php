<?php

namespace App\Services\Applications;

use App\Models\LicenseApplication;
use App\Models\Setting;
use App\Support\SettingValue;
use Illuminate\Validation\ValidationException;

class ApplicationNumber
{
    public function next(string $party): string
    {
        $pattern = $this->pattern();
        $year = (int) now()->year;
        $width = $this->width($pattern);
        $serial = $this->highest($pattern, $party, $year, $width) + 1;

        return $this->render($pattern, $party, $year, $serial, $width);
    }

    public function letterNext(): string
    {
        $year = (int) now()->year;
        $prefix = 'DEF-'.$year.'-';
        $highest = 0;

        foreach (DeficiencyLetterNumbers::existing($prefix) as $number) {
            $suffix = substr($number, strlen($prefix));

            if (preg_match('/^\d+$/', $suffix) === 1) {
                $highest = max($highest, (int) $suffix);
            }
        }

        return $prefix.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }

    private function pattern(): string
    {
        $setting = Setting::query()->where('key', 'application_no_pattern')->first();
        $pattern = 'APP-{C/D}-{YYYY}-{SERIAL:4}';

        if ($setting) {
            $value = SettingValue::typed($setting);

            if (is_string($value) && trim($value) !== '') {
                $pattern = trim($value);
            }
        }

        if ($this->width($pattern) === null) {
            throw ValidationException::withMessages([
                'application_no' => ['The application number pattern needs a {SERIAL:n} placeholder.'],
            ]);
        }

        return $pattern;
    }

    private function width(string $pattern): ?int
    {
        if (preg_match('/\{SERIAL:([1-8])\}/', $pattern, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function highest(string $pattern, string $party, int $year, int $width): int
    {
        $regex = $this->regex($pattern, $party, $year, $width);
        $highest = 0;

        foreach (LicenseApplication::withTrashed()->pluck('application_no') as $number) {
            if (preg_match($regex, (string) $number, $match) === 1) {
                $highest = max($highest, (int) $match[1]);
            }
        }

        return $highest;
    }

    private function render(string $pattern, string $party, int $year, int $serial, int $width): string
    {
        $rendered = preg_replace_callback('/\{([^{}]+)\}/', function (array $match) use ($party, $year, $serial, $width): string {
            $token = $match[1];

            if ($token === 'YYYY') {
                return (string) $year;
            }

            if ($token === 'C/D') {
                return $party;
            }

            if (preg_match('/^SERIAL:[1-8]$/', $token) === 1) {
                return str_pad((string) $serial, $width, '0', STR_PAD_LEFT);
            }

            return '';
        }, $pattern);

        return is_string($rendered) ? $rendered : $pattern;
    }

    private function regex(string $pattern, string $party, int $year, int $width): string
    {
        $body = preg_replace_callback('/\{([^{}]+)\}|([^{]+)/', function (array $match) use ($party, $year, $width): string {
            if (($match[1] ?? '') !== '') {
                $token = $match[1];

                if ($token === 'YYYY') {
                    return (string) $year;
                }

                if ($token === 'C/D') {
                    return preg_quote($party, '/');
                }

                if (preg_match('/^SERIAL:[1-8]$/', $token) === 1) {
                    return '(\d{'.$width.'})';
                }

                return '';
            }

            return preg_quote($match[2] ?? '', '/');
        }, $pattern);

        return '/^'.(is_string($body) ? $body : '').'$/';
    }
}
