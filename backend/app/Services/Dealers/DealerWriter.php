<?php

namespace App\Services\Dealers;

use App\Models\Dealer;
use App\Models\District;
use App\Models\Tehsil;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealerWriter
{
    public function __construct(
        private DealerName $names,
        private DealerCode $codes,
        private ActivityLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Dealer
    {
        $district = $this->district($data, $actor);
        $this->tehsil($data, $district);
        $warnings = $this->names->warnings((string) $data['shop_name'], (string) $data['business_address'], $district->id);
        $this->guardWarnings($warnings, $data);

        return DB::transaction(function () use ($data, $actor, $district, $warnings) {
            $dealer = new Dealer;
            $this->fill($dealer, $data, $district);
            $dealer->dealer_code = $this->codes->next($district);
            $dealer->status = 'unlicensed';
            $dealer->created_by = $actor->id;
            $dealer->updated_by = $actor->id;
            $dealer->save();

            $this->logWarning($dealer, $actor, $warnings, $data);
            $this->logger->log(
                'created',
                'Created dealer '.$dealer->shop_name,
                'dealer',
                $dealer->id,
                $actor,
                null,
                $this->snapshot($dealer),
            );

            return $dealer->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Dealer $dealer, array $data, User $actor): Dealer
    {
        $district = $this->district($data, $actor);
        $this->tehsil($data, $district);
        $warnings = $this->names->warnings((string) $data['shop_name'], (string) $data['business_address'], $district->id, $dealer->id);
        $this->guardWarnings($warnings, $data);

        return DB::transaction(function () use ($dealer, $data, $actor, $district, $warnings) {
            $before = $this->snapshot($dealer);
            $this->fill($dealer, $data, $district);
            $dealer->updated_by = $actor->id;
            $dealer->save();

            $this->logWarning($dealer, $actor, $warnings, $data);
            $this->logger->log(
                'updated',
                'Updated dealer '.$dealer->shop_name,
                'dealer',
                $dealer->id,
                $actor,
                $before,
                $this->snapshot($dealer->refresh()),
            );

            return $dealer;
        });
    }

    public function delete(Dealer $dealer, string $reason, User $actor): void
    {
        DB::transaction(function () use ($dealer, $reason, $actor) {
            $dealer->updated_by = $actor->id;
            $dealer->save();
            $dealer->delete();
            $this->logger->log(
                'deleted',
                $reason,
                'dealer',
                $dealer->id,
                $actor,
                null,
                ['reason' => $reason],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Dealer $dealer, array $data, District $district): void
    {
        $dealer->fill([
            'shop_name' => $data['shop_name'],
            'normalized_name' => $this->names->normalize((string) $data['shop_name']),
            'district_id' => $district->id,
            'tehsil_id' => $data['tehsil_id'] ?? null,
            'business_address' => $data['business_address'],
            'gps_lat' => $data['gps_lat'] ?? null,
            'gps_lng' => $data['gps_lng'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function district(array $data, User $actor): District
    {
        $district = District::query()->whereKey($data['district_id'])->where('is_active', true)->first();

        if (! $district instanceof District) {
            throw ValidationException::withMessages([
                'district_id' => ['Choose a district.'],
            ]);
        }

        if ($actor->hasRole('District Officer') && ! $actor->districts()->whereKey($district->id)->exists()) {
            throw ValidationException::withMessages([
                'district_id' => ['Choose a district assigned to you.'],
            ]);
        }

        return $district;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tehsil(array $data, District $district): void
    {
        if (empty($data['tehsil_id'])) {
            return;
        }

        $valid = Tehsil::query()
            ->whereKey($data['tehsil_id'])
            ->where('district_id', $district->id)
            ->where('is_active', true)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'tehsil_id' => ['Choose a tehsil in this district.'],
            ]);
        }
    }

    /**
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $data
     */
    private function guardWarnings(array $warnings, array $data): void
    {
        if ($warnings !== [] && empty($data['confirm_warnings'])) {
            throw new DealerWarningException($warnings);
        }
    }

    /**
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $data
     */
    private function logWarning(Dealer $dealer, User $actor, array $warnings, array $data): void
    {
        if ($warnings === []) {
            return;
        }

        $this->logger->log(
            'warning_overridden',
            'Warning confirmed: '.$data['warning_reason'],
            'dealer',
            $dealer->id,
            $actor,
            null,
            [
                'warnings' => $warnings,
                'warning_reason' => $data['warning_reason'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Dealer $dealer): array
    {
        return [
            'dealer_code' => $dealer->dealer_code,
            'shop_name' => $dealer->shop_name,
            'district_id' => $dealer->district_id,
            'tehsil_id' => $dealer->tehsil_id,
            'business_address' => $dealer->business_address,
            'mobile' => $dealer->mobile,
            'email' => $dealer->email,
            'status' => $dealer->status,
        ];
    }
}
