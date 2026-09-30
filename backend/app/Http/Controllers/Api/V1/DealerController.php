<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\DealersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealers\DeleteDealerRequest;
use App\Http\Requests\Dealers\SaveDealerRequest;
use App\Models\Dealer;
use App\Models\District;
use App\Models\License;
use App\Models\Tehsil;
use App\Services\ActivityLogger;
use App\Services\Dealers\DealerActivity;
use App\Services\Dealers\DealerDirectory;
use App\Services\Dealers\DealerName;
use App\Services\Dealers\DealerWarningException;
use App\Services\Dealers\DealerWriter;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DealerController extends Controller
{
    public function index(Request $request, DealerDirectory $directory): JsonResponse
    {
        if (! $request->query->has('per_page')) {
            $request->query->set('per_page', 25);
        }

        $page = ListQuery::paginate(
            $request,
            $directory->query($request),
            ['dealer_code', 'shop_name', 'status'],
            'shop_name',
        );

        return ApiResponse::success(
            $page->getCollection()->map(fn (Dealer $dealer) => $this->payload($dealer))->values(),
            [
                ...ListQuery::meta($page),
                'districts' => $this->districts($request),
            ],
        );
    }

    public function show(Dealer $dealer): JsonResponse
    {
        $dealer->load(['district', 'tehsil']);

        return ApiResponse::success([
            'dealer' => $this->payload($dealer),
            'license' => $this->license($dealer),
        ]);
    }

    public function checkDuplicate(Request $request, DealerName $names): JsonResponse
    {
        $shopName = trim((string) $request->input('shop_name', ''));
        $address = trim((string) $request->input('business_address', ''));
        $districtId = $request->integer('district_id');
        $ignoreId = $request->filled('ignore_id') ? $request->integer('ignore_id') : null;
        $warnings = ($shopName === '' && $address === '') || $districtId < 1
            ? []
            : $names->warnings($shopName, $address, $districtId, $ignoreId);

        return ApiResponse::success(['warnings' => $warnings]);
    }

    public function store(SaveDealerRequest $request, DealerWriter $writer): JsonResponse
    {
        try {
            $dealer = $writer->create($request->validated(), $request->user());
        } catch (DealerWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        $dealer->load(['district', 'tehsil']);

        return ApiResponse::success($this->payload($dealer), status: 201);
    }

    public function update(SaveDealerRequest $request, Dealer $dealer, DealerWriter $writer): JsonResponse
    {
        try {
            $dealer = $writer->update($dealer, $request->validated(), $request->user());
        } catch (DealerWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        $dealer->load(['district', 'tehsil']);

        return ApiResponse::success($this->payload($dealer));
    }

    public function destroy(DeleteDealerRequest $request, Dealer $dealer, DealerWriter $writer): JsonResponse
    {
        $writer->delete($dealer, $request->string('reason')->value(), $request->user());

        return ApiResponse::success(['deleted' => true]);
    }

    public function activity(Dealer $dealer, DealerActivity $activity): JsonResponse
    {
        $rows = $activity->query($dealer)->orderByDesc('id')->limit(100)->get();

        return ApiResponse::success($rows->map(fn ($log) => $activity->present($log))->values());
    }

    public function export(Request $request, DealerDirectory $directory, ActivityLogger $logger): BinaryFileResponse
    {
        abort_unless($request->user()?->can('dealers.view') ?? false, 403);

        $dealers = $directory->query($request)->orderBy('shop_name')->get();
        $logger->log(
            'exported',
            'Exported the dealers list.',
            'dealer',
            0,
            $request->user(),
            null,
            [
                'count' => $dealers->count(),
                'search' => $request->input('search'),
                'filter' => $request->input('filter'),
            ],
        );

        $rows = $dealers->map(fn (Dealer $dealer) => [
            $dealer->dealer_code,
            $dealer->shop_name,
            (string) ($dealer->district?->name ?? ''),
            $this->displayDate($dealer->getAttribute('expiry_date')),
            $dealer->status,
        ]);

        return Excel::download(new DealersExport($rows), 'dealers.xlsx');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Dealer $dealer): array
    {
        $dealer->loadMissing(['district', 'tehsil']);
        $expiry = $dealer->getAttribute('expiry_date');

        if ($expiry === null && ! array_key_exists('expiry_date', $dealer->getAttributes())) {
            $expiry = License::query()
                ->where('licensable_type', 'dealer')
                ->where('licensable_id', $dealer->id)
                ->where('status', '!=', 'superseded')
                ->orderByDesc('valid_to')
                ->value('valid_to');
        }

        return [
            'id' => $dealer->id,
            'dealer_code' => $dealer->dealer_code,
            'shop_name' => $dealer->shop_name,
            'district_id' => $dealer->district_id,
            'district_name' => $dealer->district?->name,
            'tehsil_id' => $dealer->tehsil_id,
            'tehsil_name' => $dealer->tehsil?->name,
            'business_address' => $dealer->business_address,
            'gps_lat' => $dealer->gps_lat,
            'gps_lng' => $dealer->gps_lng,
            'mobile' => $dealer->mobile,
            'email' => $dealer->email,
            'status' => $dealer->status,
            'expiry_date' => $expiry ? Carbon::parse($expiry)->toDateString() : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function license(Dealer $dealer): ?array
    {
        $license = License::query()
            ->where('licensable_type', 'dealer')
            ->where('licensable_id', $dealer->id)
            ->where('status', '!=', 'superseded')
            ->orderByDesc('valid_to')
            ->first();

        if (! $license) {
            return null;
        }

        $validTo = $license->valid_to?->copy()->startOfDay();

        return [
            'id' => $license->id,
            'license_no' => $license->license_no,
            'license_kind' => $license->license_kind,
            'status' => $license->status,
            'valid_from' => $license->valid_from?->toDateString(),
            'valid_to' => $license->valid_to?->toDateString(),
            'days_remaining' => $validTo ? (int) Carbon::today()->diffInDays($validTo, false) : null,
            'documents_status' => $license->documents_status,
        ];
    }

    /**
     * @return list<array{id: int, name: string, code: string, tehsils: list<array{id: int, name: string}>}>
     */
    private function districts(Request $request): array
    {
        $query = District::query()->where('is_active', true)->orderBy('name');
        $user = $request->user();

        if ($user?->hasRole('District Officer')) {
            $query->whereIn('id', $user->districts()->pluck('districts.id'));
        }

        return $query->with(['tehsils' => fn ($tehsils) => $tehsils->where('is_active', true)->orderBy('name')])
            ->get()
            ->map(fn (District $district) => [
                'id' => $district->id,
                'name' => $district->name,
                'code' => $district->code,
                'tehsils' => $district->tehsils->map(fn (Tehsil $tehsil) => [
                    'id' => $tehsil->id,
                    'name' => $tehsil->name,
                ])->values()->all(),
            ])
            ->all();
    }

    private function displayDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->format('d-m-Y');
    }
}
