<?php

namespace App\Services\Dealers;

use App\Models\Dealer;
use App\Services\Companies\CompanyName;

class DealerName
{
    public function __construct(private CompanyName $names) {}

    public function normalize(string $name): string
    {
        return $this->names->normalize($name);
    }

    public function normalizeAddress(string $address): string
    {
        $address = mb_strtolower($address);
        $address = preg_replace('/[^a-z0-9\s]/', '', $address) ?? $address;
        $address = preg_replace('/\s+/', ' ', trim($address)) ?? trim($address);

        return $address;
    }

    /**
     * @return list<string>
     */
    public function warnings(string $shopName, string $address, int $districtId, ?int $ignoreId = null): array
    {
        $normalized = $this->normalize($shopName);
        $normalizedAddress = $this->normalizeAddress($address);
        $warnings = [];

        $dealers = Dealer::query()
            ->where('district_id', $districtId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get(['id', 'dealer_code', 'shop_name', 'normalized_name', 'business_address']);

        foreach ($dealers as $dealer) {
            if ($normalized !== '' && $normalized === (string) $dealer->normalized_name) {
                $warnings[] = 'A shop named "'.$dealer->shop_name.'" ('.$dealer->dealer_code.') already exists in this district.';
            }

            if ($normalizedAddress !== '' && $normalizedAddress === $this->normalizeAddress((string) $dealer->business_address)) {
                $warnings[] = 'This address is already used by "'.$dealer->shop_name.'" ('.$dealer->dealer_code.') in this district.';
            }
        }

        return array_values(array_unique($warnings));
    }
}
