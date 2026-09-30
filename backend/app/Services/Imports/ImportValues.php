<?php

namespace App\Services\Imports;

class ImportValues
{
    public function blank(?string $value): bool
    {
        $value = mb_strtolower(trim((string) $value));

        return in_array($value, ['', 'nill', 'nil', 'null', 'na', 'n/a', 'none', '-', '--'], true);
    }

    public function phone(?string $value): ?string
    {
        if ($this->blank($value)) {
            return null;
        }

        $parts = preg_split('/[,;\/]/', (string) $value) ?: [];
        $first = trim((string) ($parts[0] ?? ''));

        return $first !== '' && mb_strlen($first) <= 20 ? $first : null;
    }

    public function email(?string $value): ?string
    {
        if ($this->blank($value)) {
            return null;
        }

        $email = trim((string) $value);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @param  list<string>  $lines
     */
    public function notes(array $lines): ?string
    {
        $lines = array_values(array_filter($lines, fn (string $line) => trim($line) !== ''));

        return $lines === [] ? null : implode("\n", $lines);
    }

    public function clip(string $value, int $length): string
    {
        return mb_substr(trim($value), 0, $length);
    }
}
