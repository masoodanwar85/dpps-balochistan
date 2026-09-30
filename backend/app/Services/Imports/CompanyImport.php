<?php

namespace App\Services\Imports;

use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\CompanyPremise;
use App\Models\CompanyProduct;
use App\Models\District;
use App\Models\License;
use App\Models\Person;
use App\Models\Province;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Companies\CompanyCode;
use App\Services\Companies\CompanyName;
use App\Services\Persons\PersonRules;
use App\Support\SettingValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CompanyImport
{
    public function __construct(
        private SpreadsheetRows $sheets,
        private ImportValues $values,
        private CompanyName $names,
        private CompanyCode $codes,
        private PersonRules $people,
        private ActivityLogger $logger,
    ) {}

    /**
     * @param  array<string, array{decision: string, fields: array<string, string>, target_id: ?int}>  $resolutions
     * @return array{total: int, ready: int, exceptions: list<array<string, mixed>>, records: list<array<string, mixed>>}
     */
    public function prepare(string $path, array $resolutions): array
    {
        $companies = $this->sheets->read($path, 'Company Details');
        $employ = $this->optionalSheet($path, 'Employ details');
        $products = $this->optionalSheet($path, 'Name Of Products');
        $districts = District::query()->orderBy('name')->get();
        $provinces = Province::query()->where('is_active', true)->pluck('name')->all();
        $seenNtn = [];
        $seenNames = [];
        $exceptions = [];
        $records = [];
        $heldNames = [];

        foreach ($companies['rows'] as $entry) {
            $row = $entry['row'];
            $raw = $this->applyFixes($entry['values'], $resolutions, $row);
            $name = trim((string) ($raw['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $issues = $this->companyIssues($raw, $name, $row, $seenNtn, $seenNames, $resolutions);
            $blocking = $this->unresolved($issues, $resolutions, $row);

            foreach ($blocking as $issue) {
                $exceptions[] = $issue;
            }

            $normalized = $this->names->normalize($name);

            $seenNames[] = $normalized;

            if ($blocking !== []) {
                $heldNames[$normalized] = $name;

                continue;
            }

            $duplicate = $this->decisionFor($resolutions, $row, 'possible_duplicate');
            $required = $this->decisionFor($resolutions, $row, 'missing_required');

            if (($duplicate['decision'] ?? null) === 'skip' || ($required['decision'] ?? null) === 'skip') {
                $heldNames[$normalized] = $name;

                continue;
            }

            $records[] = [
                'row' => $row,
                'name' => $name,
                'normalized' => $normalized,
                'merge_id' => ($duplicate['decision'] ?? null) === 'merge' ? $duplicate['target_id'] : null,
                'company' => $this->companyAttributes($raw, $name, $districts, $provinces),
                'license' => $this->licensePlan($raw, $row, $resolutions),
                'people' => [],
                'premises' => $this->premisePlan($raw, $districts),
                'products' => [],
            ];
            $ntn = $this->values->blank($raw['ntn #'] ?? null) ? null : trim((string) $raw['ntn #']);

            if ($ntn !== null && ($duplicate['decision'] ?? null) !== 'new') {
                $seenNtn[$ntn] = $row;
            }
        }

        $this->attachEmploy($employ, $records, $heldNames, $exceptions, $resolutions);
        $this->attachProducts($products, $records, $heldNames, $exceptions, $resolutions);

        return [
            'total' => count($companies['rows']),
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
        $months = $this->periodMonths();

        foreach ($records as $record) {
            $company = $record['merge_id']
                ? Company::query()->find($record['merge_id'])
                : $this->createCompany($record, $actor);

            if (! $company instanceof Company) {
                continue;
            }

            if ($record['merge_id'] === null) {
                $written++;
            }

            foreach ($record['people'] as $person) {
                $this->createPerson($company, $person, $actor);
            }

            foreach ($record['premises'] as $premise) {
                $created = CompanyPremise::query()->create([
                    ...$premise,
                    'company_id' => $company->id,
                ]);
                $this->logger->log('created', 'Imported warehouse for '.$company->name, 'company_premise', $created->id, $actor);
            }

            foreach ($record['products'] as $product) {
                $created = CompanyProduct::query()->create([
                    ...$product,
                    'company_id' => $company->id,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $this->logger->log('created', 'Imported product '.$created->brand_name, 'company_product', $created->id, $actor);
            }

            if (is_array($record['license'])) {
                $this->createLicense($company, $record['license'], $months, $actor);
            }
        }

        return $written;
    }

    /**
     * @param  array<string, string>  $raw
     * @param  array<string, array{decision: string, fields: array<string, string>, target_id: ?int}>  $resolutions
     * @param  list<string>  $seenNames
     * @param  array<string, int>  $seenNtn
     * @return list<array<string, mixed>>
     */
    private function companyIssues(
        array $raw,
        string $name,
        int $row,
        array &$seenNtn,
        array $seenNames,
        array $resolutions,
    ): array {
        $issues = [];
        $ntn = $this->values->blank($raw['ntn #'] ?? null) ? null : trim((string) $raw['ntn #']);

        if ($ntn !== null && (isset($seenNtn[$ntn]) || Company::query()->where('ntn', $ntn)->exists())) {
            $issues[] = $this->issue($row, 'possible_duplicate', 'Duplicate NTN '.$ntn.'.', 'company', ['ntn #'], $raw);
        }

        $normalized = $this->names->normalize($name);
        $similar = $this->names->similar($name);
        $fileMatch = null;

        foreach ($seenNames as $previous) {
            if ($this->similarName($normalized, $previous)) {
                $fileMatch = $previous;
                break;
            }
        }

        if ($similar !== [] || $fileMatch !== null) {
            $label = $similar[0]['name'] ?? $fileMatch;
            $issues[] = $this->issue(
                $row,
                'possible_duplicate',
                'Similar existing company: "'.$label.'".',
                'company',
                [],
                $raw,
                $similar[0]['id'] ?? null,
            );
        }

        $expiry = $this->expiry($raw['licenses exp'] ?? null);

        if ($expiry['bad']) {
            $shown = trim((string) ($raw['licenses exp'] ?? ''));
            $issues[] = $this->issue($row, 'bad_date', 'Licenses Exp = "'.($shown === '' ? '(blank)' : $shown).'".', 'license', ['licenses exp'], $raw);
        }

        return array_values(array_filter(
            $issues,
            fn (array $issue) => ! $this->resolved($resolutions, $row, $issue['issue_type'], $issue['issue_details'])
        ));
    }

    /**
     * @param  array<string, string>  $raw
     * @param  Collection<int, District>  $districts
     * @param  list<string>  $provinces
     * @return array<string, mixed>
     */
    private function companyAttributes(array $raw, string $name, $districts, array $provinces): array
    {
        $place = $this->place((string) ($raw['address'] ?? ''), $districts, $provinces);
        $address = trim((string) ($raw['address'] ?? ''));
        $city = trim((string) ($raw['city'] ?? $place['city'] ?? ''));
        $provinceName = trim((string) ($raw['province'] ?? $place['province'] ?? ''));
        $province = $provinceName === '' ? null : Province::query()->where('name', $provinceName)->first();
        $temporary = [];

        if ($this->values->blank($address)) {
            $address = 'Not recorded';
            $temporary[] = 'Temporary address: Not recorded';
        }

        if ($city === '') {
            $city = 'Not recorded';
            $temporary[] = 'Temporary city: Not recorded';
        }

        if (! $province instanceof Province) {
            $province = Province::query()->where('name', 'Balochistan')->first();
            $temporary[] = 'Temporary province: Balochistan';
        }

        $membership = mb_strtolower((string) ($raw['pcpa/croplife'] ?? ''));
        $notes = $this->values->notes([
            $this->note('Visited', $raw['visited'] ?? null),
            $this->note('Dealers', $raw['dealers'] ?? null),
            $this->note('chalan #', $raw['chalan #'] ?? null),
            $this->note('Amount', $raw['amount'] ?? null),
            $this->note('CSR', $raw['csr'] ?? null),
            $this->note('RD', $raw['rd'] ?? null),
            ...$temporary,
        ]);

        return [
            'name' => $this->values->clip($name, 250),
            'normalized_name' => $this->names->normalize($name),
            'legal_type' => 'other',
            'ntn' => $this->ntn($raw),
            'head_office_address' => $address,
            'city' => $this->values->clip($city, 100),
            'province_id' => $province?->id,
            'landline' => $this->values->phone($raw['ptcl'] ?? null),
            'mobile' => $this->values->phone($raw['phone #'] ?? null),
            'email' => $this->values->email($raw['gmail'] ?? null),
            'pcpa_member' => str_contains($membership, 'pcpa'),
            'croplife_member' => str_contains($membership, 'crop'),
            'status' => 'unlicensed',
            'legacy_reg_no' => $this->registration($raw),
            'legacy_notes' => $notes,
        ];
    }

    /**
     * @param  array<string, string>  $raw
     * @param  array<string, array{decision: string, fields: array<string, string>, target_id: ?int}>  $resolutions
     * @return array<string, mixed>|null
     */
    private function licensePlan(array $raw, int $row, array $resolutions): ?array
    {
        if (($this->decisionFor($resolutions, $row, 'bad_date')['decision'] ?? null) === 'skip') {
            return null;
        }

        $expiry = $this->expiry($raw['licenses exp'] ?? null);

        if ($expiry['bad'] || ! $expiry['date'] instanceof Carbon) {
            return null;
        }

        $number = $this->registration($raw);

        if ($number === null) {
            return null;
        }

        return [
            'license_no' => $this->values->clip($number, 100),
            'valid_to' => $expiry['date']->toDateString(),
            'renewal' => str_contains(mb_strtolower($number), 'renewal'),
        ];
    }

    /**
     * @param  array<string, string>  $raw
     * @param  Collection<int, District>  $districts
     * @return list<array<string, mixed>>
     */
    private function premisePlan(array $raw, $districts): array
    {
        $address = trim((string) ($raw['field office/ware house bln'] ?? ''));

        if ($this->values->blank($address)) {
            return [];
        }

        $districtId = null;

        foreach ($districts->sortByDesc(fn (District $district) => mb_strlen($district->name)) as $district) {
            if (str_contains(mb_strtolower($address), mb_strtolower($district->name))) {
                $districtId = $district->id;
                break;
            }
        }

        return [[
            'type' => 'warehouse',
            'district_id' => $districtId,
            'address' => $address,
            'is_active' => true,
        ]];
    }

    /**
     * @param  list<array{row: int, values: array<string, string>}>  $rows
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, string>  $heldNames
     * @param  list<array<string, mixed>>  $exceptions
     * @param  array<string, array{decision: string, fields: array<string, string>, target_id: ?int}>  $resolutions
     */
    private function attachEmploy(array $rows, array &$records, array $heldNames, array &$exceptions, array $resolutions): void
    {
        foreach ($rows as $entry) {
            $raw = $this->applyFixes($entry['values'], $resolutions, $entry['row']);
            $companyName = trim((string) ($raw['name of company'] ?? ''));

            if ($companyName === '') {
                continue;
            }

            $record = $this->findRecord($records, $companyName);

            if ($record === null && ! isset($heldNames[$this->names->normalize($companyName)])) {
                $issue = $this->issue($entry['row'], 'other', 'No company row for "'.$companyName.'".', 'person', [], $raw);

                if (! $this->resolved($resolutions, $entry['row'], $issue['issue_type'], $issue['issue_details'])) {
                    $exceptions[] = $issue;
                }

                continue;
            }

            if ($record === null) {
                continue;
            }

            $people = [];
            $ceo = trim((string) ($raw['name of ceo'] ?? ''));

            if ($ceo !== '') {
                $cnic = $this->people->digits((string) ($raw['cnic'] ?? ''));
                $mobile = $record['company']['mobile'] ?? $record['company']['landline'] ?? null;
                $skipped = in_array($this->decisionFor($resolutions, $entry['row'], 'missing_cnic')['decision'] ?? null, ['skip'], true)
                    || in_array($this->decisionFor($resolutions, $entry['row'], 'invalid_cnic')['decision'] ?? null, ['skip'], true);

                if (strlen($cnic) !== 13) {
                    $type = $this->values->blank($raw['cnic'] ?? null) ? 'missing_cnic' : 'invalid_cnic';
                    $issue = $this->issue($entry['row'], $type, 'CEO "'.$ceo.'" CNIC "'.trim((string) ($raw['cnic'] ?? '')).'".', 'person', ['cnic'], $raw);

                    if (! $this->resolved($resolutions, $entry['row'], $issue['issue_type'], $issue['issue_details'])) {
                        $exceptions[] = $issue;
                    }
                } elseif (! $skipped) {
                    if ($this->values->blank($mobile)) {
                        $mobile = '00000000000';
                        $this->noteOn($records, $record['row'], 'Temporary mobile for '.$ceo.'.');
                    }

                    $people[] = [
                        'full_name' => $this->values->clip($ceo, 150),
                        'cnic' => $cnic,
                        'cnic_pending' => false,
                        'mobile' => $mobile,
                        'role' => 'ceo',
                    ];
                }
            }

            foreach ([['ts 1', 'contact', 'TS 1'], ['ts2', 'contact 2', 'TS 2']] as [$nameKey, $phoneKey, $label]) {
                $person = $this->technicalStaff($raw, $entry['row'], $nameKey, $phoneKey, $label, $resolutions, $exceptions);

                if ($person !== null) {
                    if ($person['temporary_mobile'] ?? false) {
                        $this->noteOn($records, $record['row'], 'Temporary mobile for '.$person['full_name'].'.');
                    }

                    $people[] = $person;
                }
            }

            foreach ($records as $index => $candidate) {
                if ($candidate['row'] === $record['row']) {
                    $records[$index]['people'] = [...$candidate['people'], ...$people];
                }
            }
        }
    }

    /**
     * @param  array<string, string>  $raw
     * @param  array<string, array{decision: string, fields: array<string, string>, target_id: ?int}>  $resolutions
     * @param  list<array<string, mixed>>  $exceptions
     * @return array<string, mixed>|null
     */
    private function technicalStaff(array $raw, int $row, string $nameKey, string $phoneKey, string $label, array $resolutions, array &$exceptions): ?array
    {
        $name = trim((string) ($raw[$nameKey] ?? ''));

        if ($name === '') {
            return null;
        }

        if ($this->values->blank($name) || preg_match('/^\d+$/', $name) === 1) {
            $issue = $this->issue($row, 'other', $label.' "'.$name.'" is not a name.', 'person', [], $raw);

            if (! $this->resolved($resolutions, $row, $issue['issue_type'], $issue['issue_details'])) {
                $exceptions[] = $issue;
            }

            return null;
        }

        $mobile = $this->values->phone($raw[$phoneKey] ?? null);
        $temporaryMobile = false;

        if ($mobile === null) {
            $mobile = '00000000000';
            $temporaryMobile = true;
        }

        return [
            'full_name' => $this->values->clip($name, 150),
            'cnic' => null,
            'cnic_pending' => true,
            'mobile' => $mobile,
            'temporary_mobile' => $temporaryMobile,
            'role' => 'technical_staff',
        ];
    }

    /**
     * @param  list<array{row: int, values: array<string, string>}>  $rows
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, string>  $heldNames
     * @param  list<array<string, mixed>>  $exceptions
     * @param  array<string, array{decision: string, fields: array<string, string>, target_id: ?int}>  $resolutions
     */
    private function attachProducts(array $rows, array &$records, array $heldNames, array &$exceptions, array $resolutions): void
    {
        foreach ($rows as $entry) {
            $raw = $entry['values'];
            $companyName = trim((string) ($raw['name of company'] ?? ''));

            if ($companyName === '') {
                continue;
            }

            $record = $this->findRecord($records, $companyName);

            if ($record === null) {
                if (! isset($heldNames[$this->names->normalize($companyName)])) {
                    $issue = $this->issue($entry['row'], 'other', 'No company row for products "'.$companyName.'".', 'product', [], $raw);

                    if (! $this->resolved($resolutions, $entry['row'], $issue['issue_type'], $issue['issue_details'])) {
                        $exceptions[] = $issue;
                    }
                }

                continue;
            }

            $sample = mb_strtolower(trim((string) ($raw['samples#'] ?? '')));
            $provided = str_contains($sample, 'provided') && ! str_contains($sample, 'no');
            $chunks = preg_split('/[\r\n,\/]+/', (string) ($raw['name of products'] ?? '')) ?: [];
            $products = [];
            $used = [];

            foreach ($chunks as $chunk) {
                $chunk = trim($chunk);

                if (mb_strlen($chunk) < 2 || ! preg_match('/[a-z]/i', $chunk)) {
                    continue;
                }

                $brand = $this->values->clip($chunk, 150);
                $key = mb_strtolower($brand);

                if (isset($used[$key])) {
                    continue;
                }

                $used[$key] = true;
                $products[] = [
                    'product_id' => null,
                    'brand_name' => $brand,
                    'source' => 'own_import',
                    'sample_provided' => $provided,
                    'status' => 'needs_mapping',
                    'remarks' => mb_strlen($chunk) > 150 ? $this->values->clip($chunk, 255) : null,
                ];
            }

            foreach ($records as $index => $candidate) {
                if ($candidate['row'] === $record['row']) {
                    $records[$index]['products'] = [...$candidate['products'], ...$products];
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function createCompany(array $record, User $actor): ?Company
    {
        $attributes = $record['company'];

        if ($attributes['province_id'] === null || $attributes['city'] === '' || $attributes['head_office_address'] === '') {
            return null;
        }

        if ($attributes['ntn'] !== null && Company::query()->where('ntn', $attributes['ntn'])->exists()) {
            $attributes['legacy_notes'] = $this->values->notes([
                (string) $attributes['legacy_notes'],
                'Duplicate NTN kept in notes: '.$attributes['ntn'],
            ]);
            $attributes['ntn'] = null;
        }

        $company = Company::query()->create([
            ...$attributes,
            'company_code' => $this->codes->next(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $this->logger->log('created', 'Imported company '.$company->name, 'company', $company->id, $actor, null, [
            'legacy_reg_no' => $company->legacy_reg_no,
        ]);

        return $company;
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private function createPerson(Company $company, array $person, User $actor): void
    {
        $existing = $person['cnic']
            ? Person::query()->where('cnic', $person['cnic'])->first()
            : null;

        if (! $existing instanceof Person) {
            $existing = Person::query()->create([
                'cnic' => $person['cnic'],
                'cnic_pending' => $person['cnic_pending'],
                'full_name' => $person['full_name'],
                'normalized_name' => $this->people->normalizeName($person['full_name']),
                'mobile' => $person['mobile'],
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->logger->log('created', 'Imported person '.$existing->full_name, 'person', $existing->id, $actor);
        }

        if ($person['role'] === 'technical_staff' && CompanyPerson::query()->where('person_id', $existing->id)->where('role', 'technical_staff')->whereNull('end_date')->exists()) {
            return;
        }

        $assignment = CompanyPerson::query()->create([
            'company_id' => $company->id,
            'person_id' => $existing->id,
            'role' => $person['role'],
            'start_date' => now()->toDateString(),
            'verification_status' => 'pending',
            'source' => 'import',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $this->logger->log('created', 'Imported '.$person['role'].' '.$existing->full_name, 'company_person', $assignment->id, $actor);
    }

    /**
     * @param  array{license_no: string, valid_to: string, renewal: bool}  $plan
     */
    private function createLicense(Company $company, array $plan, int $months, User $actor): void
    {
        if (License::query()->where('license_no', $plan['license_no'])->exists()) {
            return;
        }

        $validTo = Carbon::parse($plan['valid_to'])->startOfDay();
        $validFrom = $validTo->copy()->subMonths($months)->addDay();

        if ($validFrom->greaterThanOrEqualTo($validTo)) {
            $validFrom = $validTo->copy()->subDay();
        }

        $expired = $validTo->lt(now()->startOfDay());
        $license = License::query()->create([
            'license_no' => $plan['license_no'],
            'licensable_type' => 'company',
            'licensable_id' => $company->id,
            'application_id' => null,
            'license_kind' => $plan['renewal'] ? 'renewal' : 'registration',
            'renewal_count' => $plan['renewal'] ? 1 : 0,
            'valid_from' => $validFrom->toDateString(),
            'valid_to' => $validTo->toDateString(),
            'issued_at' => $validFrom,
            'issued_by' => $actor->id,
            'status' => $expired ? 'expired' : 'active',
            'verification_token' => Str::random(40),
            'documents_status' => 'not_applicable',
            'issued_with_enforcement' => false,
            'is_legacy' => true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $company->status = $expired ? 'expired' : ($validTo->lte(now()->startOfDay()->addDays($this->amberDays())) ? 'expiring' : 'active');
        $company->save();
        $this->logger->log('created', 'Imported legacy license '.$license->license_no, 'license', $license->id, $actor);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>|null
     */
    private function findRecord(array $records, string $name): ?array
    {
        $normalized = $this->names->normalize($name);

        foreach ($records as $record) {
            if ($record['normalized'] === $normalized) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, array{decision: string, fields: array<string, string>, target_id: ?int}>  $resolutions
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
     * @param  list<array<string, mixed>>  $issues
     * @param  array<string, array{decision: string, fields: array<string, string>, target_id: ?int}>  $resolutions
     * @return list<array<string, mixed>>
     */
    private function unresolved(array $issues, array $resolutions, int $row): array
    {
        return array_values(array_filter($issues, function (array $issue) use ($resolutions, $row) {
            $decision = $this->decisionFor($resolutions, $row, $issue['issue_type']);

            if ($decision === null) {
                return true;
            }

            if ($issue['issue_type'] === 'possible_duplicate' && in_array($decision['decision'], ['new', 'merge', 'skip'], true)) {
                return false;
            }

            return $decision['decision'] !== 'skip' && $decision['decision'] !== 'fix';
        }));
    }

    /**
     * @param  array<string, array{decision: string, fields: array<string, string>, target_id: ?int, row: int, issue_type: string}>  $resolutions
     * @return array{decision: string, fields: array<string, string>, target_id: ?int}|null
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
     * @param  array<string, array{decision: string, row: int, issue_type: string, issue_details: string}>  $resolutions
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
     * @param  array<string, string>  $raw
     * @return array<string, mixed>
     */
    private function issue(int $row, string $type, string $details, string $scope, array $fields, array $raw, ?int $matchId = null): array
    {
        return [
            'row_no' => $row,
            'issue_type' => $type,
            'issue_details' => $this->values->clip($details, 255),
            'raw_data' => [
                'sheet' => 'Company Details',
                'scope' => $scope,
                'fields_needed' => $fields,
                'match_id' => $matchId,
                'values' => $raw,
            ],
        ];
    }

    /**
     * @param  Collection<int, District>  $districts
     * @param  list<string>  $provinces
     * @return array{city: ?string, province: ?string}
     */
    private function place(string $address, $districts, array $provinces): array
    {
        $haystack = mb_strtolower($address);
        $city = null;

        foreach ($districts->sortByDesc(fn (District $district) => mb_strlen($district->name)) as $district) {
            if ($district->name !== '' && str_contains($haystack, mb_strtolower($district->name))) {
                $city = $district->name;
                break;
            }
        }

        $province = null;

        foreach ($provinces as $name) {
            if (preg_match('/\b'.preg_quote(mb_strtolower($name), '/').'\b/u', $haystack) === 1) {
                $province = $name;
                break;
            }
        }

        if ($city !== null && $province === null) {
            $province = 'Balochistan';
        }

        return ['city' => $city, 'province' => $province];
    }

    /**
     * @param  array<string, string>  $raw
     */
    private function registration(array $raw): ?string
    {
        $left = trim((string) ($raw['reg'] ?? ''));
        $right = trim((string) ($raw['reg 2'] ?? ''));

        if ($left !== '' && $right !== '' && ! str_starts_with($right, $left)) {
            $combined = trim($left.' '.$right);
        } else {
            $combined = $right !== '' ? $right : $left;
        }

        return $this->values->blank($combined) ? null : $this->values->clip($combined, 100);
    }

    /**
     * @param  array<string, string>  $raw
     */
    private function ntn(array $raw): ?string
    {
        if ($this->values->blank($raw['ntn #'] ?? null)) {
            return null;
        }

        return $this->values->clip(trim((string) $raw['ntn #']), 20);
    }

    /**
     * @return array{date: ?Carbon, bad: bool}
     */
    private function expiry(?string $value): array
    {
        if ($this->values->blank($value)) {
            return ['date' => null, 'bad' => true];
        }

        $value = trim((string) $value);

        try {
            $date = Carbon::createFromFormat('!j-n-Y', $value);
        } catch (\Throwable) {
            return ['date' => null, 'bad' => true];
        }

        if (! $date instanceof Carbon || $date->format('j-n-Y') !== $value) {
            return ['date' => null, 'bad' => true];
        }

        return ['date' => $date, 'bad' => false];
    }

    private function similarName(string $left, string $right): bool
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

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function noteOn(array &$records, int $row, string $line): void
    {
        foreach ($records as $index => $candidate) {
            if ($candidate['row'] !== $row) {
                continue;
            }

            $records[$index]['company']['legacy_notes'] = $this->values->notes([
                (string) ($candidate['company']['legacy_notes'] ?? ''),
                $line,
            ]);
        }
    }

    private function note(string $label, ?string $value): string
    {
        if ($this->values->blank($value)) {
            return '';
        }

        return $label.': '.trim((string) $value);
    }

    /**
     * @return list<array{row: int, values: array<string, string>}>
     */
    private function optionalSheet(string $path, string $title): array
    {
        try {
            return $this->sheets->read($path, $title)['rows'];
        } catch (\Throwable) {
            return [];
        }
    }

    private function periodMonths(): int
    {
        $setting = Setting::query()->where('key', 'company_license_period_months')->first();
        $value = $setting ? SettingValue::typed($setting) : 12;

        return is_int($value) && $value > 0 ? $value : 12;
    }

    private function amberDays(): int
    {
        $setting = Setting::query()->where('key', 'alert_amber_days')->first();
        $value = $setting ? SettingValue::typed($setting) : 90;

        return is_int($value) && $value > 0 ? $value : 90;
    }
}
