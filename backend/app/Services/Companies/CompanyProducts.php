<?php

namespace App\Services\Companies;

use App\Models\Company;
use App\Models\CompanyProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Validation\ValidationException;

class CompanyProducts
{
    public function __construct(private ActivityLogger $logger) {}

    /**
     * @return array<string, mixed>
     */
    public function present(CompanyProduct $row): array
    {
        $row->loadMissing('product');

        return [
            'id' => $row->id,
            'company_id' => $row->company_id,
            'product_id' => $row->product_id,
            'brand_name' => $row->brand_name,
            'market_name' => $row->product?->market_name,
            'generic_name' => $row->product?->generic_name,
            'display_name' => $row->product?->displayName(),
            'product_label' => $row->product?->catalogLabel(),
            'concentration' => $row->product?->concentration,
            'formulation' => $row->product?->formulation,
            'dpp_registration_no' => $row->dpp_registration_no,
            'dpp_valid_to' => $row->dpp_valid_to?->toDateString(),
            'source' => $row->source,
            'sample_provided' => $row->sample_provided,
            'status' => $row->status,
            'remarks' => $row->remarks,
        ];
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function choices(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->orderBy('market_name')
            ->orderBy('generic_name')
            ->orderBy('concentration')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'label' => $product->catalogLabel(),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, User $actor): CompanyProduct
    {
        $this->assertBrand($company, (string) $data['brand_name']);

        $row = CompanyProduct::query()->create([
            'company_id' => $company->id,
            'status' => 'pending',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            ...$this->fields($data),
        ]);

        $this->logger->log(
            'created',
            'Added product '.$row->brand_name,
            'company_product',
            $row->id,
            $actor,
            null,
            $this->present($row),
        );

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CompanyProduct $row, array $data, User $actor): CompanyProduct
    {
        $this->assertBrand($row->company, (string) $data['brand_name'], $row->id);
        $old = $this->present($row);
        $row->fill([
            ...$this->fields($data),
            'updated_by' => $actor->id,
        ]);
        $row->save();

        $this->logger->log(
            'updated',
            'Updated product '.$row->brand_name,
            'company_product',
            $row->id,
            $actor,
            $old,
            $this->present($row),
        );

        return $row->refresh();
    }

    public function delete(CompanyProduct $row, string $reason, User $actor): void
    {
        $old = $this->present($row);
        $row->updated_by = $actor->id;
        $row->save();
        $row->delete();

        $this->logger->log(
            'deleted',
            'Removed product '.$row->brand_name,
            'company_product',
            $row->id,
            $actor,
            $old,
            ['reason' => $reason],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        return [
            'product_id' => $data['product_id'],
            'brand_name' => $data['brand_name'],
            'dpp_registration_no' => $data['dpp_registration_no'] ?? null,
            'dpp_valid_to' => $data['dpp_valid_to'] ?? null,
            'source' => $data['source'],
            'sample_provided' => (bool) $data['sample_provided'],
            'remarks' => $data['remarks'] ?? null,
        ];
    }

    private function assertBrand(?Company $company, string $brand, ?int $ignoreId = null): void
    {
        if (! $company instanceof Company) {
            throw ValidationException::withMessages([
                'brand_name' => ['This brand name already exists for this company.'],
            ]);
        }

        $taken = CompanyProduct::withTrashed()
            ->where('company_id', $company->id)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereRaw('lower(brand_name) = ?', [mb_strtolower($brand)])
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'brand_name' => ['This brand name already exists for this company.'],
            ]);
        }
    }
}
