<?php

namespace App\Services\Companies;

use App\Models\Company;
use App\Models\CompanyPremise;
use App\Models\District;
use App\Models\User;
use App\Services\ActivityLogger;

class CompanyPremises
{
    public function __construct(private ActivityLogger $logger) {}

    /**
     * @return array<string, mixed>
     */
    public function present(CompanyPremise $premise): array
    {
        $premise->loadMissing(['district', 'contactPerson']);

        return [
            'id' => $premise->id,
            'company_id' => $premise->company_id,
            'type' => $premise->type,
            'district_id' => $premise->district_id,
            'district_name' => $premise->district?->name,
            'address' => $premise->address,
            'gps_lat' => $premise->gps_lat,
            'gps_lng' => $premise->gps_lng,
            'contact_person_id' => $premise->contact_person_id,
            'contact_name' => $premise->contactPerson?->full_name,
            'phone' => $premise->phone,
            'is_active' => $premise->is_active,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function districts(): array
    {
        return District::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (District $district) => [
                'id' => $district->id,
                'name' => $district->name,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, User $actor): CompanyPremise
    {
        $premise = CompanyPremise::query()->create([
            'company_id' => $company->id,
            ...$this->fields($data),
        ]);

        $this->logger->log(
            'created',
            'Added '.$premise->type.' premise',
            'company_premise',
            $premise->id,
            $actor,
            null,
            $this->present($premise),
        );

        return $premise;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CompanyPremise $premise, array $data, User $actor): CompanyPremise
    {
        $old = $this->present($premise);
        $premise->fill($this->fields($data));
        $premise->save();

        $this->logger->log(
            'updated',
            'Updated premise',
            'company_premise',
            $premise->id,
            $actor,
            $old,
            $this->present($premise),
        );

        return $premise->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        return [
            'type' => $data['type'],
            'district_id' => $data['district_id'] ?? null,
            'address' => $data['address'],
            'gps_lat' => $data['gps_lat'] ?? null,
            'gps_lng' => $data['gps_lng'] ?? null,
            'contact_person_id' => $data['contact_person_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ];
    }
}
