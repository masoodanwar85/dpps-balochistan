<?php

namespace App\Services\Licenses;

use App\Models\Company;
use App\Models\Dealer;
use App\Models\License;
use App\Models\LicenseStatusHistory;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Statuses\StatusRefresh;
use App\Support\SettingValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PreviousLicense
{
    public function __construct(
        private ActivityLogger $logger,
        private StatusRefresh $statuses,
    ) {}

    public function latestEndDate(): Carbon
    {
        return now()->endOfYear()->startOfDay();
    }

    public function periodMonths(string $type): int
    {
        $key = $type === 'dealer' ? 'dealer_license_period_months' : 'company_license_period_months';
        $setting = Setting::query()->where('key', $key)->first();
        $value = $setting instanceof Setting ? SettingValue::typed($setting) : 12;

        return is_int($value) && $value > 0 ? $value : 12;
    }

    public function validFrom(Carbon $validTo, string $type): Carbon
    {
        $end = $validTo->copy()->startOfDay();
        $from = $end->copy()->subMonths($this->periodMonths($type))->addDay();

        if ($from->greaterThanOrEqualTo($end)) {
            return $end->copy()->subDay();
        }

        return $from;
    }

    /**
     * @return array{latest_end_date: string, period_months: array{company: int, dealer: int}, valid_from: ?string}
     */
    public function preview(string $type, ?string $validTo): array
    {
        $type = $type === 'dealer' ? 'dealer' : 'company';
        $data = [
            'latest_end_date' => $this->latestEndDate()->toDateString(),
            'period_months' => [
                'company' => $this->periodMonths('company'),
                'dealer' => $this->periodMonths('dealer'),
            ],
            'valid_from' => null,
        ];

        if ($validTo === null || trim($validTo) === '') {
            return $data;
        }

        try {
            $end = Carbon::parse($validTo)->startOfDay();
        } catch (\Throwable) {
            return $data;
        }

        if ($end->gt($this->latestEndDate())) {
            return $data;
        }

        $data['valid_from'] = $this->validFrom($end, $type)->toDateString();

        return $data;
    }

    /**
     * @param  array{licensable_type: string, licensable_id: int, license_no: string, license_kind: string, valid_to: string}  $input
     */
    public function store(array $input, User $actor): License
    {
        $type = $input['licensable_type'];
        $party = $this->party($type, (int) $input['licensable_id'], $actor);

        if ($party === null) {
            throw ValidationException::withMessages([
                'licensable_id' => $type === 'dealer' ? 'Choose a dealer.' : 'Choose a company.',
            ]);
        }

        $validTo = Carbon::parse($input['valid_to'])->startOfDay();

        if ($validTo->gt($this->latestEndDate())) {
            throw ValidationException::withMessages([
                'valid_to' => 'The end date must be '.$this->latestEndDate()->format('d-m-Y').' or earlier.',
            ]);
        }

        $number = trim($input['license_no']);

        if (License::withTrashed()->where('license_no', $number)->exists()) {
            throw ValidationException::withMessages([
                'license_no' => 'This license number is already on file.',
            ]);
        }

        return DB::transaction(function () use ($type, $party, $validTo, $number, $input, $actor) {
            $validFrom = $this->validFrom($validTo, $type);
            $kind = $input['license_kind'];
            $license = License::query()->create([
                'license_no' => $number,
                'licensable_type' => $type,
                'licensable_id' => $party->id,
                'application_id' => null,
                'license_kind' => $kind,
                'renewal_count' => $kind === 'renewal' ? 1 : 0,
                'valid_from' => $validFrom->toDateString(),
                'valid_to' => $validTo->toDateString(),
                'issued_at' => $validFrom,
                'issued_by' => $actor->id,
                'status' => $validTo->lt(now()->startOfDay()) ? 'expired' : 'active',
                'verification_token' => Str::random(40),
                'documents_status' => 'not_applicable',
                'issued_with_enforcement' => false,
                'is_legacy' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->logger->log(
                'created',
                'Recorded previous license '.$license->license_no,
                'license',
                $license->id,
                $actor,
            );
            $this->closeEarlierGrants($license, $actor);
            $this->statuses->refreshParty($type, (int) $party->id, $actor);

            $saved = $license->fresh();

            return $saved instanceof License ? $saved : $license;
        });
    }

    private function party(string $type, int $id, User $actor): Company|Dealer|null
    {
        if ($type === 'dealer') {
            $dealer = Dealer::query()->visibleTo($actor)->find($id);

            return $dealer instanceof Dealer ? $dealer : null;
        }

        $company = Company::query()->visibleTo($actor)->find($id);

        return $company instanceof Company ? $company : null;
    }

    private function closeEarlierGrants(License $license, User $actor): void
    {
        $later = License::query()
            ->where('licensable_type', $license->licensable_type)
            ->where('licensable_id', $license->licensable_id)
            ->whereKeyNot($license->id)
            ->whereNotIn('status', ['superseded', 'cancelled'])
            ->whereDate('valid_to', '>', $license->valid_to->toDateString())
            ->orderByDesc('valid_to')
            ->first();

        if ($later instanceof License) {
            if ($license->status === 'active') {
                $this->supersede($license, $later->license_no, $actor);
            }

            return;
        }

        $earlier = License::query()
            ->where('licensable_type', $license->licensable_type)
            ->where('licensable_id', $license->licensable_id)
            ->whereKeyNot($license->id)
            ->whereNotIn('status', ['superseded', 'cancelled'])
            ->whereDate('valid_to', '<', $license->valid_to->toDateString())
            ->get();

        foreach ($earlier as $previous) {
            $this->supersede($previous, $license->license_no, $actor);
        }
    }

    private function supersede(License $license, string $number, User $actor): void
    {
        $from = $license->status;
        $license->status = 'superseded';
        $license->updated_by = $actor->id;
        $license->save();
        LicenseStatusHistory::query()->create([
            'license_id' => $license->id,
            'from_status' => $from,
            'to_status' => 'superseded',
            'reason' => 'Superseded by '.$number.'.',
            'effective_date' => now()->toDateString(),
            'changed_by' => $actor->id,
        ]);
        $this->logger->log(
            'updated',
            'Superseded by '.$number,
            'license',
            $license->id,
            $actor,
            ['status' => $from],
            ['status' => 'superseded'],
        );
    }
}
