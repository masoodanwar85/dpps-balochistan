<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dealers\CheckDealerOwnerRequest;
use App\Http\Requests\Dealers\EndDealerOwnerRequest;
use App\Http\Requests\Dealers\StoreDealerOwnerRequest;
use App\Models\Dealer;
use App\Models\DealerOwner;
use App\Services\Dealers\DealerOwners;
use App\Services\Persons\PersonWarningException;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DealerOwnerController extends Controller
{
    public function index(Dealer $dealer, DealerOwners $owners): JsonResponse
    {
        $rows = DealerOwner::query()
            ->with('person')
            ->where('dealer_id', $dealer->id)
            ->orderByRaw('end_date is null desc')
            ->orderByDesc('start_date')
            ->get();

        return ApiResponse::success($rows->map(fn (DealerOwner $owner) => $owners->present($owner))->values());
    }

    public function check(CheckDealerOwnerRequest $request, Dealer $dealer, DealerOwners $owners): JsonResponse
    {
        return ApiResponse::success($owners->preview($dealer, $request->string('cnic')->value()));
    }

    public function store(StoreDealerOwnerRequest $request, Dealer $dealer, DealerOwners $owners): JsonResponse
    {
        try {
            $owner = $owners->create($dealer, $request->validated(), $request->user());
        } catch (PersonWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        return ApiResponse::success($owners->present($owner), null, 201);
    }

    public function end(EndDealerOwnerRequest $request, DealerOwner $dealerOwner, DealerOwners $owners): JsonResponse
    {
        $owner = $owners->end($dealerOwner, $request->string('end_date')->value(), $request->user());

        return ApiResponse::success($owners->present($owner));
    }
}
