<?php

namespace App\Services\Companies;

use App\Models\Company;
use App\Models\CompanyAsset;
use App\Models\User;
use App\Services\ActivityLogger;

class CompanyAssets
{
    public function __construct(private ActivityLogger $logger) {}

    /**
     * @return array<string, mixed>
     */
    public function present(CompanyAsset $asset): array
    {
        $asset->loadMissing('district');

        return [
            'id' => $asset->id,
            'company_id' => $asset->company_id,
            'asset_type' => $asset->asset_type,
            'description' => $asset->description,
            'district_id' => $asset->district_id,
            'district_name' => $asset->district?->name,
            'estimated_value' => $asset->estimated_value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, User $actor): CompanyAsset
    {
        $asset = CompanyAsset::query()->create([
            'company_id' => $company->id,
            ...$this->fields($data),
        ]);

        $this->logger->log(
            'created',
            'Added '.$asset->asset_type.' asset',
            'company_asset',
            $asset->id,
            $actor,
            null,
            $this->present($asset),
        );

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CompanyAsset $asset, array $data, User $actor): CompanyAsset
    {
        $old = $this->present($asset);
        $asset->fill($this->fields($data));
        $asset->save();

        $this->logger->log(
            'updated',
            'Updated asset',
            'company_asset',
            $asset->id,
            $actor,
            $old,
            $this->present($asset),
        );

        return $asset->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        return [
            'asset_type' => $data['asset_type'],
            'description' => $data['description'],
            'district_id' => $data['district_id'] ?? null,
            'estimated_value' => $data['estimated_value'] ?? null,
        ];
    }
}
