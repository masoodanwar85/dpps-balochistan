<?php

namespace App\Services\Licenses;

use App\Models\Dealer;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\Setting;
use App\Support\SettingValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LicenseNumbers
{
    public function preview(LicenseApplication $application): string
    {
        $context = $this->context($application);
        $serial = $this->lastSerial($context['entity'], $context['district_id'], $context['year']) + 1;

        return $this->render($context, $serial);
    }

    public function next(LicenseApplication $application): string
    {
        $context = $this->context($application);

        return DB::transaction(function () use ($context) {
            $query = DB::table('license_number_sequences')
                ->where('entity_type', $context['entity'])
                ->where('year', $context['year']);

            if ($context['district_id'] === null) {
                $query->whereNull('district_id');
            } else {
                $query->where('district_id', $context['district_id']);
            }

            $row = $query->lockForUpdate()->first();
            $serial = ($row->last_serial ?? 0) + 1;

            if ($row === null) {
                DB::table('license_number_sequences')->insert([
                    'entity_type' => $context['entity'],
                    'district_id' => $context['district_id'],
                    'year' => $context['year'],
                    'last_serial' => $serial,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('license_number_sequences')->where('id', $row->id)->update([
                    'last_serial' => $serial,
                    'updated_at' => now(),
                ]);
            }

            return $this->render($context, $serial);
        });
    }

    /**
     * @return array{entity: string, district_id: int|null, district_code: string|null, year: int, pattern: string, renewal: string}
     */
    private function context(LicenseApplication $application): array
    {
        $districtId = null;
        $districtCode = null;

        if ($application->licensable_type === 'dealer') {
            $dealer = Dealer::withTrashed()->with('district')->find($application->licensable_id);
            $districtId = $dealer?->district_id;
            $districtCode = $dealer?->district?->code;

            if (! is_string($districtCode) || $districtCode === '') {
                throw ValidationException::withMessages([
                    'license_no' => ['This dealer has no district code.'],
                ]);
            }
        }

        $key = $application->licensable_type === 'dealer' ? 'dealer_license_no_pattern' : 'company_license_no_pattern';
        $pattern = $this->pattern($key);

        return [
            'entity' => $application->licensable_type,
            'district_id' => $districtId,
            'district_code' => $districtCode,
            'year' => (int) now()->year,
            'pattern' => $pattern,
            'renewal' => $this->renewal($application),
        ];
    }

    private function pattern(string $key): string
    {
        $fallback = $key === 'dealer_license_no_pattern'
            ? 'DPP/D/{DISTRICT}/{YYYY}/{SERIAL:4}{RENEWAL}'
            : 'DPP/C/{YYYY}/{SERIAL:4}{RENEWAL}';
        $setting = Setting::query()->where('key', $key)->first();
        $pattern = $fallback;

        if ($setting) {
            $value = SettingValue::typed($setting);

            if (is_string($value) && trim($value) !== '') {
                $pattern = trim($value);
            }
        }

        if (preg_match('/\{SERIAL:([1-8])\}/', $pattern) !== 1) {
            throw ValidationException::withMessages([
                'license_no' => ['The license number pattern needs a {SERIAL:n} placeholder.'],
            ]);
        }

        return $pattern;
    }

    private function renewal(LicenseApplication $application): string
    {
        if ($application->application_type !== 'renewal') {
            return '';
        }

        $previous = $application->previous_license_id
            ? License::query()->find($application->previous_license_id)
            : null;
        $count = $previous instanceof License ? ((int) $previous->renewal_count) + 1 : 1;

        return '/R'.$count;
    }

    public function renewalCount(LicenseApplication $application): int
    {
        $renewal = $this->renewal($application);

        return $renewal === '' ? 0 : (int) substr($renewal, 2);
    }

    private function lastSerial(string $entity, ?int $districtId, int $year): int
    {
        $query = DB::table('license_number_sequences')
            ->where('entity_type', $entity)
            ->where('year', $year);

        if ($districtId === null) {
            $query->whereNull('district_id');
        } else {
            $query->where('district_id', $districtId);
        }

        return (int) ($query->value('last_serial') ?? 0);
    }

    /**
     * @param  array{entity: string, district_id: int|null, district_code: string|null, year: int, pattern: string, renewal: string}  $context
     */
    private function render(array $context, int $serial): string
    {
        preg_match('/\{SERIAL:([1-8])\}/', $context['pattern'], $width);
        $padded = str_pad((string) $serial, (int) ($width[1] ?? 4), '0', STR_PAD_LEFT);
        $rendered = preg_replace_callback('/\{([^{}]+)\}/', function (array $match) use ($context, $padded): string {
            return match ($match[1]) {
                'YYYY' => (string) $context['year'],
                'DISTRICT' => (string) $context['district_code'],
                'RENEWAL' => $context['renewal'],
                default => str_starts_with($match[1], 'SERIAL:') ? $padded : $match[0],
            };
        }, $context['pattern']);

        return is_string($rendered) ? $rendered : $context['pattern'];
    }
}
