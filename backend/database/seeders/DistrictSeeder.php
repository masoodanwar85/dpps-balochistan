<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Tehsil;
use App\Support\DealersListReader;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $reader = new DealersListReader;
        $usedCodes = [];

        foreach ($reader->districts($this->workbookPath()) as $district) {
            $code = $this->code($district['name'], $usedCodes);
            $usedCodes[$code] = true;

            $record = District::query()->firstOrCreate(
                ['name' => $district['name']],
                [
                    'code' => $code,
                    'is_active' => true,
                ],
            );

            foreach ($district['tehsils'] as $tehsil) {
                Tehsil::query()->firstOrCreate(
                    [
                        'district_id' => $record->id,
                        'name' => $tehsil,
                    ],
                    ['is_active' => true],
                );
            }
        }
    }

    private function workbookPath(): string
    {
        return dirname(base_path()).'/docs/data/Dealers_List.xlsx';
    }

    /**
     * @param  array<string, true>  $usedCodes
     */
    private function code(string $name, array $usedCodes): string
    {
        $overrides = [
            'quetta' => 'QTA',
            'barkhan' => 'BRK',
            'khuzdar' => 'KZD',
            'hub' => 'HUB',
        ];
        $reserved = array_flip($overrides);
        $base = $overrides[strtolower($name)] ?? $this->letters($name);
        $candidate = $base;
        $suffix = 0;

        while (
            isset($usedCodes[$candidate])
            || (isset($reserved[$candidate]) && $reserved[$candidate] !== strtolower($name))
            || District::query()->where('code', $candidate)->exists()
        ) {
            $suffix++;
            $candidate = substr($base, 0, 2).chr(ord('A') + (($suffix - 1) % 26));
        }

        return $candidate;
    }

    private function letters(string $name): string
    {
        $letters = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $name));

        if (strlen($letters) <= 3) {
            return str_pad($letters, 3, 'X');
        }

        $consonants = preg_replace('/[AEIOU]/', '', substr($letters, 1)) ?: '';
        $code = $letters[0].substr($consonants, 0, 2);

        if (strlen($code) < 3) {
            return substr($letters, 0, 3);
        }

        return $code;
    }
}
