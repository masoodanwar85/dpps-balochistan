<?php

namespace App\Services\Dealers;

use App\Models\Dealer;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DealerDirectory
{
    /**
     * @return Builder<Dealer>
     */
    public function query(Request $request): Builder
    {
        $query = Dealer::query()
            ->select('dealers.*')
            ->with(['district', 'tehsil'])
            ->withExpiry()
            ->visibleTo($request->user());

        ListQuery::search($request, $query, ['dealers.dealer_code', 'dealers.shop_name']);

        $status = (string) $request->input('filter.status', '');
        $statuses = ['unlicensed', 'active', 'expiring', 'expired', 'suspended', 'cancelled'];

        if (in_array($status, $statuses, true)) {
            $query->where('dealers.status', $status);
        }

        if ($request->filled('filter.district_id')) {
            $query->where('dealers.district_id', $request->integer('filter.district_id'));
        }

        if ($request->filled('filter.tehsil_id')) {
            $query->where('dealers.tehsil_id', $request->integer('filter.tehsil_id'));
        }

        return $query;
    }
}
