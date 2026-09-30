<?php

namespace App\Services\Companies;

use App\Models\Company;

class CompanyName
{
    public function normalize(string $name): string
    {
        $name = mb_strtolower($name);
        $name = preg_replace('/[^a-z0-9\s]/', '', $name) ?? $name;
        $name = preg_replace('/\b(pvt|ltd|private|limited)\b/', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? trim($name);

        return $name;
    }

    /**
     * @return list<array{id: int, company_code: string, name: string, message: string}>
     */
    public function similar(string $name, ?int $ignoreId = null): array
    {
        $normalized = $this->normalize($name);

        if ($normalized === '') {
            return [];
        }

        $matches = [];
        $companies = Company::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get(['id', 'company_code', 'name', 'normalized_name']);

        foreach ($companies as $company) {
            if ($this->isSimilar($normalized, (string) $company->normalized_name)) {
                $matches[] = [
                    'id' => $company->id,
                    'company_code' => $company->company_code,
                    'name' => $company->name,
                    'message' => 'Similar existing company: "'.$company->name.'" ('.$company->company_code.').',
                ];
            }
        }

        return $matches;
    }

    private function isSimilar(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        $shorter = strlen($left) <= strlen($right) ? $left : $right;
        $longer = $shorter === $left ? $right : $left;

        if (strlen($shorter) >= 6 && str_contains($longer, $shorter)) {
            return true;
        }

        similar_text($left, $right, $percent);

        return $percent >= 85;
    }
}
