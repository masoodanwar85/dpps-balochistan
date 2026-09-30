<?php

namespace App\Services\Companies;

use App\Models\Company;
use App\Models\Setting;
use App\Support\ListQuery;
use App\Support\SettingValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CompanyDirectory
{
    /**
     * @return Builder<Company>
     */
    public function query(Request $request): Builder
    {
        $query = Company::query()
            ->select('companies.*')
            ->with('province')
            ->withExpiry()
            ->visibleTo($request->user());

        ListQuery::search($request, $query, ['companies.company_code', 'companies.name', 'companies.ntn']);

        $status = (string) $request->input('filter.status', '');
        $statuses = ['unlicensed', 'active', 'expiring', 'expired', 'suspended', 'cancelled'];

        if (in_array($status, $statuses, true)) {
            $query->where('companies.status', $status);
        }

        $pcpa = $request->input('filter.pcpa_member');

        if ($pcpa === true || $pcpa === 1 || $pcpa === '1' || $pcpa === 'true') {
            $query->where('companies.pcpa_member', true);
        } elseif ($pcpa === false || $pcpa === 0 || $pcpa === '0' || $pcpa === 'false') {
            $query->where('companies.pcpa_member', false);
        }

        $expiry = (string) $request->input('filter.expiry', '');
        $expirySql = "(select valid_to from licenses where licensable_type = 'company' and licensable_id = companies.id and deleted_at is null and status <> 'superseded' order by valid_to desc limit 1)";

        if ($expiry === 'expired') {
            $query->whereRaw($expirySql.' < ?', [Carbon::today()->toDateString()]);
        } elseif ($expiry === 'expiring') {
            $days = $this->amberDays();
            $query->whereRaw($expirySql.' >= ? and '.$expirySql.' <= ?', [
                Carbon::today()->toDateString(),
                Carbon::today()->addDays($days)->toDateString(),
            ]);
        }

        return $query;
    }

    public function amberDays(): int
    {
        $setting = Setting::query()->where('key', 'alert_amber_days')->first();

        if (! $setting instanceof Setting) {
            return 90;
        }

        $days = SettingValue::typed($setting);

        return is_int($days) && $days >= 1 ? $days : 90;
    }
}
