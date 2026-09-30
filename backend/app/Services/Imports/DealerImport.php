<?php

namespace App\Services\Imports;

use App\Models\Dealer;
use App\Models\DealerOwner;
use App\Models\District;
use App\Models\Person;
use App\Models\Tehsil;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Dealers\DealerCode;
use App\Services\Dealers\DealerName;
use App\Services\Persons\PersonRules;

class DealerImport
{
    public function __construct(
        private SpreadsheetRows $sheets,
        private ImportValues $values,
        private DealerName $names,
        private DealerCode $codes,
        private PersonRules $people,
        private ActivityLogger $logger,
    ) {}

    /**
     * @param  array<string, array{decision: string, fields: array<string, string>, target_id: ?int, row: int, issue_type: string, issue_details: string}>  $resolutions
     * @return array{total: int, ready: int, exceptions: list<array<string, mixed>>, records: list<array<string, mixed>>}
     */
    public function prepare(string $path, array $resolutions): array
    {
        $sheet = $this->sheets->read($path, 'By District');
        $districts = District::query()->get()->keyBy(fn (District $district) => mb_strtolower(trim($district->name)));
        $seenRegistration = [];
        $exceptions = [];
        $records = [];

        foreach ($sheet['rows'] as $entry) {
            $raw = $this->applyFixes($entry['values'], $resolutions, $entry['row']);
            $districtName = trim((string) ($raw['district'] ?? ''));

            if ($districtName === '' || in_array(mb_strtolower($districtName), ['day', 'month', 'year'], true)) {
                continue;
            }

            $district = $districts->get(mb_strtolower($districtName));
            $issues = [];

            if (! $district instanceof District) {
                $issues[] = $this->issue($entry['row'], 'unknown_district', 'Unknown district "'.$districtName.'".', ['district'], $raw);
            }

            $registration = $this->values->blank($raw['registration no.'] ?? null) ? null : trim((string) $raw['registration no.']);
            $registrationKey = $registration === null ? null : mb_strtolower($registration);

            if ($registrationKey !== null && (isset($seenRegistration[$registrationKey]) || Dealer::query()->whereRaw('lower(legacy_reg_no) = ?', [$registrationKey])->exists())) {
                $existingId = Dealer::query()->whereRaw('lower(legacy_reg_no) = ?', [$registrationKey])->value('id');
                $issues[] = $this->issue($entry['row'], 'possible_duplicate', 'Repeated registration number "'.$registration.'".', [], $raw, is_numeric($existingId) ? (int) $existingId : null);
            }

            $address = trim((string) ($raw['buisness address'] ?? ''));

            if ($this->values->blank($address)) {
                $issues[] = $this->issue($entry['row'], 'missing_required', 'Business address is blank.', ['buisness address'], $raw);
            }

            $owner = trim((string) ($raw['name of onwer'] ?? ''));
            $mobile = $this->values->phone($raw['contact no'] ?? null);

            if ($owner !== '' && $mobile === null) {
                $issues[] = $this->issue($entry['row'], 'missing_required', 'Owner "'.$owner.'" has no contact number.', ['contact no'], $raw);
            }

            $blocking = array_values(array_filter(
                $issues,
                fn (array $issue) => ! $this->resolved($resolutions, $entry['row'], $issue['issue_type'], $issue['issue_details'])
            ));

            foreach ($blocking as $issue) {
                $exceptions[] = $issue;
            }

            if ($registrationKey !== null) {
                $seenRegistration[$registrationKey] = $entry['row'];
            }

            $decision = $this->decisionFor($resolutions, $entry['row'], 'possible_duplicate');
            $unknown = $this->decisionFor($resolutions, $entry['row'], 'unknown_district');
            $required = $this->decisionFor($resolutions, $entry['row'], 'missing_required');

            if ($blocking !== [] || ($decision['decision'] ?? null) === 'skip' || ($unknown['decision'] ?? null) === 'skip' || ($required['decision'] ?? null) === 'skip') {
                continue;
            }

            if (! $district instanceof District) {
                continue;
            }

            $tehsilName = trim((string) ($raw['area/tehsil'] ?? ''));
            $shop = $this->shopName($address, $tehsilName);
            $records[] = [
                'row' => $entry['row'],
                'merge_id' => ($decision['decision'] ?? null) === 'merge' ? $decision['target_id'] : null,
                'dealer' => [
                    'shop_name' => $this->values->clip($shop, 250),
                    'normalized_name' => $this->names->normalize($shop),
                    'district_id' => $district->id,
                    'tehsil_name' => $this->values->blank($tehsilName) ? null : $this->values->clip($tehsilName, 150),
                    'business_address' => $address,
                    'mobile' => $mobile,
                    'status' => 'unlicensed',
                    'legacy_reg_no' => $registration === null ? null : $this->values->clip($registration, 100),
                    'legacy_notes' => $this->values->notes([
                        $this->note('Renewal', $raw['renewal'] ?? null),
                        $this->note('Registration', $raw['regisration'] ?? null),
                        $this->note('fee', $raw['fee'] ?? null),
                        $this->note('fee', $raw['fee 2'] ?? null),
                    ]),
                ],
                'owner' => $owner === '' || $mobile === null ? null : [
                    'full_name' => $this->values->clip($owner, 150),
                    'mobile' => $mobile,
                ],
            ];
        }

        return [
            'total' => count($sheet['rows']),
            'ready' => count($records),
            'exceptions' => $exceptions,
            'records' => $records,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function commit(array $records, User $actor): int
    {
        $written = 0;

        foreach ($records as $record) {
            if ($record['merge_id']) {
                $dealer = Dealer::query()->find($record['merge_id']);
            } else {
                $district = District::query()->find($record['dealer']['district_id']);

                if (! $district instanceof District) {
                    continue;
                }

                $tehsilId = $this->tehsilId($district, $record['dealer']['tehsil_name'], $actor);
                $dealer = Dealer::query()->create([
                    'dealer_code' => $this->codes->next($district),
                    'shop_name' => $record['dealer']['shop_name'],
                    'normalized_name' => $record['dealer']['normalized_name'],
                    'district_id' => $district->id,
                    'tehsil_id' => $tehsilId,
                    'business_address' => $record['dealer']['business_address'],
                    'mobile' => $record['dealer']['mobile'],
                    'status' => 'unlicensed',
                    'legacy_reg_no' => $record['dealer']['legacy_reg_no'],
                    'legacy_notes' => $record['dealer']['legacy_notes'],
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $this->logger->log('created', 'Imported dealer '.$dealer->shop_name, 'dealer', $dealer->id, $actor);
                $written++;
            }

            if (! $dealer instanceof Dealer || $record['owner'] === null) {
                continue;
            }

            $person = Person::query()->create([
                'cnic' => null,
                'cnic_pending' => true,
                'full_name' => $record['owner']['full_name'],
                'normalized_name' => $this->people->normalizeName($record['owner']['full_name']),
                'mobile' => $record['owner']['mobile'],
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->logger->log('created', 'Imported dealer owner '.$person->full_name, 'person', $person->id, $actor);
            $owner = DealerOwner::query()->create([
                'dealer_id' => $dealer->id,
                'person_id' => $person->id,
                'start_date' => now()->toDateString(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->logger->log('created', 'Imported owner link for '.$dealer->shop_name, 'dealer_owner', $owner->id, $actor);
        }

        return $written;
    }

    private function tehsilId(District $district, ?string $name, User $actor): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }

        $existing = Tehsil::query()
            ->where('district_id', $district->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing instanceof Tehsil) {
            return $existing->id;
        }

        $tehsil = Tehsil::query()->create([
            'district_id' => $district->id,
            'name' => $name,
            'is_active' => true,
        ]);
        $this->logger->log('created', 'Imported tehsil '.$tehsil->name, 'tehsil', $tehsil->id, $actor);

        return $tehsil->id;
    }

    private function shopName(string $address, string $tehsil): string
    {
        if ($tehsil !== '' && str_ends_with(mb_strtolower($address), mb_strtolower($tehsil))) {
            $shop = trim(mb_substr($address, 0, mb_strlen($address) - mb_strlen($tehsil)), ' ,-');

            if ($shop !== '') {
                return $shop;
            }
        }

        return $address;
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, array{decision: string, fields: array<string, string>, target_id: ?int, row: int}>  $resolutions
     * @return array<string, string>
     */
    private function applyFixes(array $values, array $resolutions, int $row): array
    {
        foreach ($resolutions as $resolution) {
            if ($resolution['row'] !== $row || $resolution['decision'] !== 'fix') {
                continue;
            }

            foreach ($resolution['fields'] as $field => $value) {
                $values[mb_strtolower((string) $field)] = trim((string) $value);
            }
        }

        return $values;
    }

    /**
     * @param  array<string, array{row: int, issue_type: string, issue_details: string}>  $resolutions
     */
    private function resolved(array $resolutions, int $row, string $type, string $details): bool
    {
        foreach ($resolutions as $resolution) {
            if ($resolution['row'] === $row && $resolution['issue_type'] === $type && $resolution['issue_details'] === $details) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array{row: int, issue_type: string, decision: string, target_id: ?int}>  $resolutions
     * @return array{decision: string, target_id: ?int}|null
     */
    private function decisionFor(array $resolutions, int $row, string $type): ?array
    {
        foreach ($resolutions as $resolution) {
            if ($resolution['row'] === $row && $resolution['issue_type'] === $type) {
                return $resolution;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, string>  $raw
     * @return array<string, mixed>
     */
    private function issue(int $row, string $type, string $details, array $fields, array $raw, ?int $matchId = null): array
    {
        return [
            'row_no' => $row,
            'issue_type' => $type,
            'issue_details' => $this->values->clip($details, 255),
            'raw_data' => [
                'sheet' => 'By District',
                'scope' => 'dealer',
                'fields_needed' => $fields,
                'match_id' => $matchId,
                'values' => $raw,
            ],
        ];
    }

    private function note(string $label, ?string $value): string
    {
        if ($this->values->blank($value)) {
            return '';
        }

        return $label.': '.trim((string) $value);
    }
}
