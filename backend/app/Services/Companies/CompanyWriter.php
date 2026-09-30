<?php

namespace App\Services\Companies;

use App\Models\Company;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class CompanyWriter
{
    public function __construct(
        private CompanyName $names,
        private CompanyCode $codes,
        private ActivityLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Company
    {
        $matches = $this->names->similar((string) $data['name']);
        $this->guardWarnings($matches, $data);

        return DB::transaction(function () use ($data, $actor, $matches) {
            $company = new Company;
            $this->fill($company, $data);
            $company->company_code = $this->codes->next();
            $company->status = 'unlicensed';
            $company->created_by = $actor->id;
            $company->updated_by = $actor->id;
            $company->save();

            $this->logWarning($company, $actor, $matches, $data);
            $this->logger->log(
                'created',
                'Created company '.$company->name,
                'company',
                $company->id,
                $actor,
                null,
                $this->snapshot($company),
            );

            return $company->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data, User $actor): Company
    {
        $matches = $this->names->similar((string) $data['name'], $company->id);
        $this->guardWarnings($matches, $data);

        return DB::transaction(function () use ($company, $data, $actor, $matches) {
            $before = $this->snapshot($company);
            $this->fill($company, $data);
            $company->updated_by = $actor->id;
            $company->save();

            $this->logWarning($company, $actor, $matches, $data);
            $this->logger->log(
                'updated',
                'Updated company '.$company->name,
                'company',
                $company->id,
                $actor,
                $before,
                $this->snapshot($company->refresh()),
            );

            return $company;
        });
    }

    public function delete(Company $company, string $reason, User $actor): void
    {
        DB::transaction(function () use ($company, $reason, $actor) {
            $company->updated_by = $actor->id;
            $company->save();
            $company->delete();
            $this->logger->log(
                'deleted',
                $reason,
                'company',
                $company->id,
                $actor,
                null,
                ['reason' => $reason],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Company $company, array $data): void
    {
        $company->fill([
            'name' => $data['name'],
            'normalized_name' => $this->names->normalize((string) $data['name']),
            'legal_type' => $data['legal_type'],
            'ntn' => $data['ntn'] ?? null,
            'incorporation_no' => $data['incorporation_no'] ?? null,
            'incorporation_date' => $data['incorporation_date'] ?? null,
            'head_office_address' => $data['head_office_address'],
            'city' => $data['city'],
            'province_id' => $data['province_id'],
            'landline' => $data['landline'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'pcpa_member' => (bool) $data['pcpa_member'],
            'croplife_member' => (bool) $data['croplife_member'],
            'membership_no' => $data['membership_no'] ?? null,
        ]);
    }

    /**
     * @param  list<array{id: int, company_code: string, name: string, message: string}>  $matches
     * @param  array<string, mixed>  $data
     */
    private function guardWarnings(array $matches, array $data): void
    {
        if ($matches !== [] && empty($data['confirm_warnings'])) {
            throw new CompanyWarningException(array_column($matches, 'message'));
        }
    }

    /**
     * @param  list<array{id: int, company_code: string, name: string, message: string}>  $matches
     * @param  array<string, mixed>  $data
     */
    private function logWarning(Company $company, User $actor, array $matches, array $data): void
    {
        if ($matches === []) {
            return;
        }

        $reason = (string) $data['warning_reason'];
        $this->logger->log(
            'warning_overridden',
            'Warning confirmed: '.$reason,
            'company',
            $company->id,
            $actor,
            null,
            [
                'warnings' => array_column($matches, 'message'),
                'warning_reason' => $reason,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Company $company): array
    {
        return [
            'company_code' => $company->company_code,
            'name' => $company->name,
            'normalized_name' => $company->normalized_name,
            'legal_type' => $company->legal_type,
            'ntn' => $company->ntn,
            'incorporation_no' => $company->incorporation_no,
            'incorporation_date' => $company->incorporation_date?->toDateString(),
            'head_office_address' => $company->head_office_address,
            'city' => $company->city,
            'province_id' => $company->province_id,
            'landline' => $company->landline,
            'mobile' => $company->mobile,
            'email' => $company->email,
            'website' => $company->website,
            'pcpa_member' => $company->pcpa_member,
            'croplife_member' => $company->croplife_member,
            'membership_no' => $company->membership_no,
            'status' => $company->status,
        ];
    }
}
