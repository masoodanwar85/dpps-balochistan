<?php

namespace App\Services\Licenses;

use App\Models\Company;
use App\Models\Dealer;
use App\Models\License;
use App\Models\LicenseStatusHistory;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Validation\ValidationException;

class LicenseStatusChanges
{
    public function __construct(private ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function change(License $license, string $action, array $data, User $actor): License
    {
        $from = $license->status;
        $allowed = match ($action) {
            'suspend' => ['active', 'expired'],
            'cancel' => ['active', 'expired', 'suspended'],
            'restore' => ['suspended', 'cancelled'],
            default => [],
        };

        if (! in_array($from, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ['This license cannot be changed from its current status.'],
            ]);
        }

        $to = $action === 'restore'
            ? ($license->valid_to->copy()->startOfDay()->gte(now()->startOfDay()) ? 'active' : 'expired')
            : ($action === 'suspend' ? 'suspended' : 'cancelled');

        $license->status = $to;
        $license->updated_by = $actor->id;
        $license->save();
        LicenseStatusHistory::query()->create([
            'license_id' => $license->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $data['reason'],
            'order_no' => $data['order_no'],
            'effective_date' => $data['effective_date'],
            'changed_by' => $actor->id,
        ]);
        $this->party($license, $to, $actor);
        $verb = match ($action) {
            'suspend' => 'Suspended',
            'cancel' => 'Cancelled',
            default => 'Restored',
        };
        $this->logger->log(
            $action === 'restore' ? 'restored' : ($action === 'suspend' ? 'suspended' : 'cancelled'),
            $verb.' license '.$license->license_no,
            'license',
            $license->id,
            $actor,
            ['status' => $from],
            ['status' => $to, 'reason' => $data['reason'], 'order_no' => $data['order_no']],
        );

        return $license->refresh();
    }

    private function party(License $license, string $status, User $actor): void
    {
        $party = $license->licensable_type === 'company'
            ? Company::query()->find($license->licensable_id)
            : Dealer::query()->find($license->licensable_id);

        if ($party === null) {
            return;
        }

        $party->status = match ($status) {
            'suspended' => 'suspended',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            default => 'active',
        };
        $party->updated_by = $actor->id;
        $party->save();
    }
}
