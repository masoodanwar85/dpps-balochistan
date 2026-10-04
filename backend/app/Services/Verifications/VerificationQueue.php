<?php

namespace App\Services\Verifications;

use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\CompanyProduct;
use App\Models\Dealer;
use App\Models\Document;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Companies\CompanyPeople;
use App\Services\Documents\DocumentStore;
use App\Services\Notifications\InAppNotifications;
use App\Services\Persons\PersonRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class VerificationQueue
{
    public function __construct(
        private ActivityLogger $logger,
        private CompanyPeople $people,
        private DocumentStore $documents,
        private PersonRules $rules,
        private InAppNotifications $notifications,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>, counts: array{staff: int, documents: int, products: int}}
     */
    public function list(User $actor, string $type): array
    {
        $counts = [
            'staff' => $actor->can('staff.verify') ? $this->staff($actor)->count() : 0,
            'documents' => $actor->can('documents.verify') ? $this->documentQuery($actor)->count() : 0,
            'products' => $actor->can('products.verify') ? $this->productQuery($actor)->count() : 0,
        ];

        $rows = match ($type) {
            'document' => $actor->can('documents.verify') ? $this->documentRows($actor) : [],
            'product' => $actor->can('products.verify') ? $this->productRows($actor) : [],
            default => $actor->can('staff.verify') ? $this->staffRows($actor) : [],
        };

        return ['rows' => $rows, 'counts' => $counts];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $actor, string $type, int $id): array
    {
        $this->assertType($actor, $type);

        return match ($type) {
            'document' => $this->documentDetail($this->findDocument($actor, $id)),
            'product' => $this->productDetail($this->findProduct($actor, $id)),
            default => $this->staffDetail($this->findStaff($actor, $id)),
        };
    }

    public function approve(User $actor, string $type, int $id): void
    {
        $this->assertType($actor, $type);

        if ($type === 'staff') {
            $row = $this->findStaff($actor, $id);
            $this->people->verify($row, $actor);
            $this->notifications->submissionDecided($row->company_id, 'Staff verified', 'Technical staff '.$row->person?->full_name.' was verified.');

            return;
        }

        if ($type === 'document') {
            $row = $this->findDocument($actor, $id);
            $this->documents->verify($row, $actor);
            $this->notifyDocument($row, 'Document verified', 'Document '.$row->title.' was verified.');

            return;
        }

        $row = $this->findProduct($actor, $id);
        $this->assertPendingProduct($row);
        $old = $row->status;
        $row->status = 'approved';
        $row->updated_by = $actor->id;
        $row->save();
        $this->logger->log('approved', 'Approved product '.$row->brand_name, 'company_product', $row->id, $actor, ['status' => $old], ['status' => 'approved']);
        $this->notifications->submissionDecided($row->company_id, 'Product approved', 'Product '.$row->brand_name.' was approved.');
    }

    public function reject(User $actor, string $type, int $id, string $reason): void
    {
        $this->assertType($actor, $type);

        if ($type === 'staff') {
            $row = $this->findStaff($actor, $id);
            $this->assertPendingStaff($row);
            $old = $row->verification_status;
            $row->fill([
                'verification_status' => 'rejected',
                'rejection_reason' => $reason,
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'updated_by' => $actor->id,
            ]);
            $row->save();
            $this->logger->log('rejected', 'Rejected technical staff '.$row->person?->full_name, 'company_person', $row->id, $actor, ['verification_status' => $old], ['verification_status' => 'rejected', 'rejection_reason' => $reason]);
            $this->notifications->submissionDecided($row->company_id, 'Staff rejected', 'Technical staff '.$row->person?->full_name.' was rejected.');

            return;
        }

        if ($type === 'document') {
            $row = $this->findDocument($actor, $id);
            $this->assertPendingDocument($row);
            $old = $row->verification_status;
            $row->fill([
                'verification_status' => 'rejected',
                'rejection_reason' => $reason,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]);
            $row->save();
            $this->logger->log('rejected', 'Rejected document '.$row->title, 'document', $row->id, $actor, ['verification_status' => $old], ['verification_status' => 'rejected', 'rejection_reason' => $reason]);
            $this->notifyDocument($row, 'Document rejected', 'Document '.$row->title.' was rejected.');

            return;
        }

        $row = $this->findProduct($actor, $id);
        $this->assertPendingProduct($row);
        $old = $row->status;
        $row->status = 'withdrawn';
        $row->remarks = $reason;
        $row->updated_by = $actor->id;
        $row->save();
        $this->logger->log('rejected', 'Rejected product '.$row->brand_name, 'company_product', $row->id, $actor, ['status' => $old], ['status' => 'withdrawn', 'remarks' => $reason]);
        $this->notifications->submissionDecided($row->company_id, 'Product rejected', 'Product '.$row->brand_name.' was rejected.');
    }

    private function assertType(User $actor, string $type): void
    {
        $permission = match ($type) {
            'document' => 'documents.verify',
            'product' => 'products.verify',
            'staff' => 'staff.verify',
            default => '',
        };

        if ($permission === '' || ! $actor->can($permission)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    /**
     * @return Builder<CompanyPerson>
     */
    private function staff(User $actor): Builder
    {
        return CompanyPerson::query()
            ->where('verification_status', 'pending')
            ->where('role', 'technical_staff')
            ->whereNull('end_date')
            ->whereIn('company_id', Company::query()->visibleTo($actor)->select('id'));
    }

    /**
     * @return Builder<Document>
     */
    private function documentQuery(User $actor): Builder
    {
        $companies = Company::query()->visibleTo($actor)->select('id');
        $dealers = Dealer::query()->visibleTo($actor)->select('id');

        return Document::query()
            ->where('verification_status', 'pending')
            ->whereNull('replaced_by_id')
            ->where(function (Builder $query) use ($companies, $dealers) {
                $query->where(function (Builder $inner) use ($companies) {
                    $inner->where('documentable_type', 'company')->whereIn('documentable_id', $companies);
                })->orWhere(function (Builder $inner) use ($dealers) {
                    $inner->where('documentable_type', 'dealer')->whereIn('documentable_id', $dealers);
                });
            });
    }

    /**
     * @return Builder<CompanyProduct>
     */
    private function productQuery(User $actor): Builder
    {
        return CompanyProduct::query()
            ->where('status', 'pending')
            ->whereIn('company_id', Company::query()->visibleTo($actor)->select('id'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staffRows(User $actor): array
    {
        return $this->staff($actor)->with(['company', 'person'])->orderBy('id')->limit(100)->get()
            ->map(fn (CompanyPerson $row) => [
                'id' => $row->id,
                'type' => 'staff',
                'applicant' => $row->company?->name ?? 'Company',
                'item' => 'New staff: '.($row->person?->full_name ?? 'Person'),
                'submitted_at' => $row->created_at?->toDateString(),
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documentRows(User $actor): array
    {
        return $this->documentQuery($actor)->orderBy('id')->limit(100)->get()
            ->map(fn (Document $row) => [
                'id' => $row->id,
                'type' => 'document',
                'applicant' => $this->ownerName($row),
                'item' => $row->title,
                'submitted_at' => $row->created_at?->toDateString(),
            ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productRows(User $actor): array
    {
        return $this->productQuery($actor)->with('company')->orderBy('id')->limit(100)->get()
            ->map(fn (CompanyProduct $row) => [
                'id' => $row->id,
                'type' => 'product',
                'applicant' => $row->company?->name ?? 'Company',
                'item' => $row->brand_name,
                'submitted_at' => $row->created_at?->toDateString(),
            ])->all();
    }

    private function findStaff(User $actor, int $id): CompanyPerson
    {
        $row = $this->staff($actor)->with(['company', 'person'])->whereKey($id)->first();

        if (! $row instanceof CompanyPerson) {
            abort(404);
        }

        return $row;
    }

    private function findDocument(User $actor, int $id): Document
    {
        $row = $this->documentQuery($actor)->whereKey($id)->first();

        if (! $row instanceof Document) {
            abort(404);
        }

        return $row;
    }

    private function findProduct(User $actor, int $id): CompanyProduct
    {
        $row = $this->productQuery($actor)->with(['company', 'product'])->whereKey($id)->first();

        if (! $row instanceof CompanyProduct) {
            abort(404);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function staffDetail(CompanyPerson $row): array
    {
        $conflicts = [];

        if ($row->person) {
            $conflicts = collect($this->rules->conflicts($row->person))
                ->where('for', 'technical_staff')
                ->filter(function (array $conflict) use ($row) {
                    return ! str_contains($conflict['message'], (string) $row->company?->name);
                })
                ->pluck('message')
                ->unique()
                ->values()
                ->all();
        }

        return [
            'id' => $row->id,
            'type' => 'staff',
            'applicant' => $row->company?->name,
            'item' => $row->person?->full_name,
            'submitted_at' => $row->created_at?->toDateString(),
            'fields' => [
                ['label' => 'CNIC', 'value' => $row->person?->cnic],
                ['label' => 'Mobile', 'value' => $row->person?->mobile],
                ['label' => 'Since', 'value' => $row->start_date?->toDateString()],
                ['label' => 'Source', 'value' => $row->source],
            ],
            'files' => [],
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentDetail(Document $row): array
    {
        $row->loadMissing('documentType');

        return [
            'id' => $row->id,
            'type' => 'document',
            'applicant' => $this->ownerName($row),
            'item' => $row->title,
            'submitted_at' => $row->created_at?->toDateString(),
            'fields' => [
                ['label' => 'Type', 'value' => $row->documentType?->name],
                ['label' => 'Issue date', 'value' => $row->issue_date?->toDateString()],
                ['label' => 'Expiry', 'value' => $row->expiry_date?->toDateString()],
                ['label' => 'Submitted via', 'value' => $row->uploaded_via],
            ],
            'files' => [[
                'id' => $row->id,
                'title' => $row->title,
            ]],
            'conflicts' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productDetail(CompanyProduct $row): array
    {
        return [
            'id' => $row->id,
            'type' => 'product',
            'applicant' => $row->company?->name,
            'item' => $row->brand_name,
            'submitted_at' => $row->created_at?->toDateString(),
            'fields' => [
                ['label' => 'Product', 'value' => $row->product?->catalogLabel()],
                ['label' => 'Source', 'value' => $row->source],
                ['label' => 'DPP registration', 'value' => $row->dpp_registration_no],
                ['label' => 'Sample provided', 'value' => $row->sample_provided ? 'Yes' : 'No'],
            ],
            'files' => [],
            'conflicts' => $row->product_id === null ? ['This product is not mapped to the product master.'] : [],
        ];
    }

    private function assertPendingStaff(CompanyPerson $row): void
    {
        if ($row->verification_status !== 'pending') {
            throw ValidationException::withMessages([
                'verification_status' => ['This item has already been decided.'],
            ]);
        }
    }

    private function assertPendingDocument(Document $row): void
    {
        if ($row->verification_status !== 'pending') {
            throw ValidationException::withMessages([
                'verification_status' => ['This item has already been decided.'],
            ]);
        }
    }

    private function assertPendingProduct(CompanyProduct $row): void
    {
        if ($row->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['This item has already been decided.'],
            ]);
        }
    }

    private function notifyDocument(Document $row, string $title, string $body): void
    {
        if ($row->documentable_type === 'company') {
            $this->notifications->submissionDecided((int) $row->documentable_id, $title, $body);
        }
    }

    private function ownerName(Document $row): string
    {
        if ($row->documentable_type === 'dealer') {
            return Dealer::withTrashed()->find($row->documentable_id)?->shop_name ?? 'Dealer';
        }

        return Company::withTrashed()->find($row->documentable_id)?->name ?? 'Company';
    }
}
