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
        $continued = $this->continuedNumber($application);

        if ($continued !== null) {
            return $continued;
        }

        $context = $this->context($application);

        if ($context['entity'] === 'dealer') {
            return $this->render($context, [
                'district' => $this->nextDistrictSerial($context),
                'balochistan' => $this->nextBalochistanSerial(),
            ]);
        }

        return $this->render($context, ['company' => $this->nextSerial($context)]);
    }

    public function next(LicenseApplication $application): string
    {
        $continued = $this->continuedNumber($application);

        if ($continued !== null) {
            return $continued;
        }

        $context = $this->context($application);

        return DB::transaction(function () use ($context) {
            if ($context['entity'] === 'dealer') {
                return $this->allocateDealer($context);
            }

            $row = $this->lockedRow($context);
            $serial = $this->nextSerial($context);

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

            return $this->render($context, ['company' => $serial]);
        });
    }

    /**
     * @param  array{entity: string, district_id: int|null, district_code: string|null, year: int, pattern: string, renewal: string}  $context
     */
    private function allocateDealer(array $context): string
    {
        $this->lockDealerCounters($context['district_id']);
        $districtSerial = $this->nextDistrictSerial($context);
        $balochistanSerial = $this->nextBalochistanSerial();
        $this->saveCounter('dealer', $context['district_id'], $context['year'], $districtSerial);
        $this->saveCounter('dealer', null, $context['year'], $balochistanSerial);

        return $this->render($context, [
            'district' => $districtSerial,
            'balochistan' => $balochistanSerial,
        ]);
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
            ? '{DISTRICT_SERIAL:4} / DPPB - {BALOCHISTAN_SERIAL:4} / {YYYY} / {DISTRICT}{RENEWAL}'
            : 'DPPB/{SERIAL:4}/{YYYY}{RENEWAL}';
        $setting = Setting::query()->where('key', $key)->first();
        $pattern = $fallback;

        if ($setting) {
            $value = SettingValue::typed($setting);

            if (is_string($value) && trim($value) !== '') {
                $pattern = trim($value);
            }
        }

        $dealerReady = preg_match('/\{DISTRICT_SERIAL:([1-8])\}/', $pattern) === 1
            && preg_match('/\{BALOCHISTAN_SERIAL:([1-8])\}/', $pattern) === 1;

        if ($key === 'dealer_license_no_pattern' && ! $dealerReady) {
            throw ValidationException::withMessages([
                'license_no' => ['The dealer license number pattern needs a district serial and a Balochistan serial.'],
            ]);
        }

        if ($key !== 'dealer_license_no_pattern' && preg_match('/\{SERIAL:([1-8])\}/', $pattern) !== 1) {
            throw ValidationException::withMessages([
                'license_no' => ['The license number pattern needs a {SERIAL:n} placeholder.'],
            ]);
        }

        return $pattern;
    }

    private function continuedNumber(LicenseApplication $application): ?string
    {
        if ($application->application_type !== 'renewal') {
            return null;
        }

        $previous = $application->previous_license_id
            ? License::query()->find($application->previous_license_id)
            : null;

        if (! $previous instanceof License) {
            return null;
        }

        $base = preg_replace('/\/R\d+$/', '', $previous->license_no);

        if (! is_string($base)) {
            return null;
        }

        $matches = ($application->licensable_type === 'company' && preg_match('/^DPPB\/\d+\/\d{4}$/', $base) === 1)
            || ($application->licensable_type === 'dealer' && preg_match('/^\d+\s*\/\s*DPPB\s*-\s*\d+\s*\/\s*\d{4}\s*\/\s*[A-Za-z0-9]+$/', $base) === 1);

        if (! $matches) {
            return null;
        }

        return $base.'/R'.(((int) $previous->renewal_count) + 1);
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

    /**
     * @param  array{entity: string, district_id: int|null, district_code: string|null, year: int, pattern: string, renewal: string}  $context
     */
    private function lockedRow(array $context): ?object
    {
        $query = DB::table('license_number_sequences')
            ->where('entity_type', $context['entity'])
            ->orderByDesc('last_serial');

        if ($context['district_id'] === null) {
            $query->whereNull('district_id');
        } else {
            $query->where('district_id', $context['district_id'])->where('year', $context['year']);
        }

        $row = $query->lockForUpdate()->get()->first(
            fn (object $sequence): bool => (int) $sequence->year === $context['year']
        );

        return $row instanceof \stdClass ? $row : null;
    }

    /**
     * @param  array{entity: string, district_id: int|null, district_code: string|null, year: int, pattern: string, renewal: string}  $context
     */
    private function nextSerial(array $context): int
    {
        if ($context['entity'] !== 'company') {
            return $this->lastSerial($context['entity'], $context['district_id'], $context['year']) + 1;
        }

        return max($this->storedCompanySerial(), $this->issuedCompanySerial()) + 1;
    }

    private function storedCompanySerial(): int
    {
        return (int) (DB::table('license_number_sequences')
            ->where('entity_type', 'company')
            ->whereNull('district_id')
            ->max('last_serial') ?? 0);
    }

    private function issuedCompanySerial(): int
    {
        $highest = 0;

        foreach (License::withTrashed()->where('licensable_type', 'company')->pluck('license_no') as $number) {
            if (preg_match('/^DPPB\/(\d+)\/\d{4}(?:\/R\d+)?$/', (string) $number, $match) === 1) {
                $highest = max($highest, (int) $match[1]);
            }
        }

        return $highest;
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
    private function lockDealerCounters(?int $districtId): void
    {
        DB::table('license_number_sequences')
            ->where('entity_type', 'dealer')
            ->where(function ($query) use ($districtId): void {
                $query->where('district_id', $districtId)->orWhereNull('district_id');
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  array{entity: string, district_id: int|null, district_code: string|null, year: int, pattern: string, renewal: string}  $context
     */
    private function nextDistrictSerial(array $context): int
    {
        $stored = (int) (DB::table('license_number_sequences')
            ->where('entity_type', 'dealer')
            ->where('district_id', $context['district_id'])
            ->max('last_serial') ?? 0);
        $code = strtoupper((string) $context['district_code']);
        $issued = 0;

        foreach ($this->issuedDealerNumbers() as $number) {
            if ($number['district'] === $code) {
                $issued = max($issued, $number['district_serial']);
            }
        }

        return max($stored, $issued) + 1;
    }

    private function nextBalochistanSerial(): int
    {
        $stored = (int) (DB::table('license_number_sequences')
            ->where('entity_type', 'dealer')
            ->whereNull('district_id')
            ->max('last_serial') ?? 0);
        $issued = 0;

        foreach ($this->issuedDealerNumbers() as $number) {
            $issued = max($issued, $number['balochistan_serial']);
        }

        return max($stored, $issued) + 1;
    }

    /**
     * @return list<array{district_serial: int, balochistan_serial: int, district: string}>
     */
    private function issuedDealerNumbers(): array
    {
        $numbers = [];

        foreach (License::withTrashed()->where('licensable_type', 'dealer')->pluck('license_no') as $licenseNo) {
            $base = preg_replace('/\/R\d+$/', '', (string) $licenseNo);

            if (! is_string($base) || preg_match('/^(\d+)\s*\/\s*DPPB\s*-\s*(\d+)\s*\/\s*\d{4}\s*\/\s*([A-Za-z0-9]+)$/', $base, $match) !== 1) {
                continue;
            }

            $numbers[] = [
                'district_serial' => (int) $match[1],
                'balochistan_serial' => (int) $match[2],
                'district' => strtoupper($match[3]),
            ];
        }

        return $numbers;
    }

    private function saveCounter(string $entity, ?int $districtId, int $year, int $serial): void
    {
        $query = DB::table('license_number_sequences')
            ->where('entity_type', $entity)
            ->where('year', $year);

        if ($districtId === null) {
            $query->whereNull('district_id');
        } else {
            $query->where('district_id', $districtId);
        }

        $row = $query->orderByDesc('last_serial')->first();

        if ($row === null) {
            DB::table('license_number_sequences')->insert([
                'entity_type' => $entity,
                'district_id' => $districtId,
                'year' => $year,
                'last_serial' => $serial,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('license_number_sequences')->where('id', $row->id)->update([
            'last_serial' => $serial,
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array{entity: string, district_id: int|null, district_code: string|null, year: int, pattern: string, renewal: string}  $context
     * @param  array{company?: int, district?: int, balochistan?: int}  $serials
     */
    private function render(array $context, array $serials): string
    {
        $rendered = preg_replace_callback('/\{([^{}]+)\}/', function (array $match) use ($context, $serials): string {
            $token = $match[1];

            if ($token === 'YYYY') {
                return (string) $context['year'];
            }

            if ($token === 'DISTRICT') {
                return (string) $context['district_code'];
            }

            if ($token === 'RENEWAL') {
                return $context['renewal'];
            }

            if (preg_match('/^SERIAL:([1-8])$/', $token, $width) === 1) {
                return str_pad((string) ($serials['company'] ?? 0), (int) $width[1], '0', STR_PAD_LEFT);
            }

            if (preg_match('/^DISTRICT_SERIAL:([1-8])$/', $token, $width) === 1) {
                return str_pad((string) ($serials['district'] ?? 0), (int) $width[1], '0', STR_PAD_LEFT);
            }

            if (preg_match('/^BALOCHISTAN_SERIAL:([1-8])$/', $token, $width) === 1) {
                return str_pad((string) ($serials['balochistan'] ?? 0), (int) $width[1], '0', STR_PAD_LEFT);
            }

            return $match[0];
        }, $context['pattern']);

        return is_string($rendered) ? $rendered : $context['pattern'];
    }
}
