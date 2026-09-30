<?php

namespace App\Services\Portal;

use App\Models\ApplicationChecklistItem;
use App\Models\Company;
use App\Models\CompanyAsset;
use App\Models\CompanyPerson;
use App\Models\CompanyPremise;
use App\Models\DeficiencyLetter;
use App\Models\Document;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Services\Applications\ApplicationStages;
use App\Services\Companies\CompanyAssets;
use App\Services\Companies\CompanyPeople;
use App\Services\Companies\CompanyPremises;
use App\Services\Companies\CompanyProfile;
use App\Services\Documents\DocumentStore;
use Illuminate\Support\Carbon;

class PortalHome
{
    public const CONTACT_MESSAGE = 'To change any of this information, please contact the Directorate of Plant Protection: 081-9211868, dppb2018@gmail.com';

    public function __construct(
        private CompanyProfile $profile,
        private CompanyPeople $people,
        private CompanyPremises $premises,
        private CompanyAssets $assets,
        private DocumentStore $documents,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Company $company): array
    {
        $presented = $this->profile->present($company);
        $license = $this->currentLicense($company);
        $renewal = $this->renewal($company, $license);
        $verified = $this->profile->verifiedTechnicalStaff($company);
        $minimum = $this->profile->minimumTechnicalStaff();
        $pendingStaff = CompanyPerson::query()
            ->where('company_id', $company->id)
            ->where('role', 'technical_staff')
            ->whereNull('end_date')
            ->where('verification_status', 'pending')
            ->count();
        $pendingDocuments = Document::query()
            ->where('documentable_type', 'company')
            ->where('documentable_id', $company->id)
            ->whereNull('replaced_by_id')
            ->where('verification_status', 'pending')
            ->count();

        return [
            'company_name' => $company->name,
            'license' => $presented['license'],
            'renewal' => $renewal,
            'verified_technical_staff' => $verified,
            'minimum_technical_staff' => $minimum,
            'actions' => $this->actions($company, $license, $verified, $minimum, $pendingStaff, $pendingDocuments),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function company(Company $company): array
    {
        $presented = $this->profile->present($company);
        $leadership = CompanyPerson::query()
            ->with(['person'])
            ->where('company_id', $company->id)
            ->whereIn('role', ['ceo', 'director'])
            ->whereNull('end_date')
            ->orderBy('role')
            ->get()
            ->map(fn (CompanyPerson $row) => $this->people->present($row))
            ->values()
            ->all();

        return [
            'company' => $presented['company'],
            'license' => $presented['license'],
            'leadership' => $leadership,
            'premises' => CompanyPremise::query()->where('company_id', $company->id)->orderBy('id')->get()
                ->map(fn (CompanyPremise $row) => $this->premises->present($row))->values()->all(),
            'assets' => CompanyAsset::query()->where('company_id', $company->id)->orderBy('id')->get()
                ->map(fn (CompanyAsset $row) => $this->assets->present($row))->values()->all(),
            'contact_message' => self::CONTACT_MESSAGE,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function staff(Company $company): array
    {
        return CompanyPerson::query()
            ->with(['person.qualifications.qualification'])
            ->where('company_id', $company->id)
            ->where('role', 'technical_staff')
            ->orderByDesc('start_date')
            ->get()
            ->map(function (CompanyPerson $row) {
                $presented = $this->people->present($row);
                $presented['rejection_reason'] = $row->rejection_reason;

                return $presented;
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function documents(Company $company): array
    {
        return Document::query()
            ->with('documentType')
            ->where('documentable_type', 'company')
            ->where('documentable_id', $company->id)
            ->whereNull('replaced_by_id')
            ->orderByDesc('id')
            ->get()
            ->map(function (Document $row) {
                $presented = $this->documents->present($row);
                $presented['rejection_reason'] = $row->rejection_reason;

                return $presented;
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function applications(Company $company): array
    {
        return LicenseApplication::query()
            ->with('currentStage')
            ->where('licensable_type', 'company')
            ->where('licensable_id', $company->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (LicenseApplication $row) => $this->application($row))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function application(LicenseApplication $row): array
    {
        $letters = DeficiencyLetter::query()
            ->withCount('items')
            ->where('application_id', $row->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (DeficiencyLetter $letter) => [
                'id' => $letter->id,
                'letter_no' => $letter->letter_no,
                'status' => $letter->status,
                'reply_due_date' => $letter->reply_due_date?->toDateString(),
                'item_count' => $letter->items_count,
            ])
            ->all();

        $items = ApplicationChecklistItem::query()
            ->with('source')
            ->where('application_id', $row->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ApplicationChecklistItem $item) => [
                'id' => $item->id,
                'annex' => $item->annex_code_snapshot,
                'title' => $item->title_snapshot,
                'status' => $item->status,
                'page_count' => $item->page_count,
                'officer_remarks' => $item->officer_remarks,
                'portal_uploadable' => (bool) $item->source?->portal_uploadable,
                'requires_upload' => (bool) $item->source?->requires_upload,
                'requires_validity_dates' => (bool) $item->source?->requires_validity_dates,
            ])
            ->all();

        return [
            'id' => $row->id,
            'application_no' => $row->application_no,
            'application_type' => $row->application_type,
            'status' => $row->status,
            'submitted_via' => $row->submitted_via,
            'submitted_at' => $row->submitted_at?->toDateString(),
            'current_stage' => $row->currentStage?->name,
            'fee_amount' => number_format((float) $row->fee_amount, 2, '.', ''),
            'total_payable' => number_format((float) $row->total_payable, 2, '.', ''),
            'letters' => $letters,
            'items' => $items,
        ];
    }

    private function currentLicense(Company $company): ?License
    {
        $license = License::query()
            ->where('licensable_type', 'company')
            ->where('licensable_id', $company->id)
            ->where('status', '!=', 'superseded')
            ->orderByDesc('valid_to')
            ->first();

        return $license instanceof License ? $license : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function renewal(Company $company, ?License $license): array
    {
        $open = LicenseApplication::query()
            ->where('licensable_type', 'company')
            ->where('licensable_id', $company->id)
            ->whereNotIn('status', ['issued', 'rejected', 'withdrawn'])
            ->first();
        $days = ApplicationStages::settingDays('renewal_window_days', 60);
        $opensOn = $license?->valid_to?->copy()->startOfDay()->subDays($days);
        $windowOpen = $opensOn instanceof Carbon && Carbon::today()->greaterThanOrEqualTo($opensOn);
        $blocked = null;

        if (! $license instanceof License) {
            $blocked = 'A renewal needs a current license.';
        } elseif (! $windowOpen) {
            $blocked = 'A renewal cannot be submitted before the renewal window opens.';
        } elseif ($open instanceof LicenseApplication && $open->status !== 'draft') {
            $blocked = 'This company already has an open application.';
        }

        return [
            'opens_on' => $opensOn?->toDateString(),
            'can_start' => $blocked === null,
            'has_draft' => $open instanceof LicenseApplication && $open->status === 'draft',
            'draft_id' => $open instanceof LicenseApplication && $open->status === 'draft' ? $open->id : null,
            'blocked_reason' => $blocked,
        ];
    }

    /**
     * @return list<array{kind: string, text: string}>
     */
    private function actions(Company $company, ?License $license, int $verified, int $minimum, int $pendingStaff, int $pendingDocuments): array
    {
        $actions = [];

        $letters = DeficiencyLetter::query()
            ->withCount('items')
            ->where('status', 'open')
            ->whereHas('application', function ($query) use ($company) {
                $query->where('licensable_type', 'company')->where('licensable_id', $company->id);
            })
            ->orderBy('id')
            ->get();

        foreach ($letters as $letter) {
            $due = $letter->reply_due_date?->format('d-m-Y');
            $count = (int) $letter->items_count;
            $items = $count === 1 ? '1 item' : $count.' items';
            $actions[] = [
                'kind' => 'deficiency',
                'text' => 'Deficiency letter '.$letter->letter_no.': '.$items.($due ? ' due '.$due : ''),
            ];
        }

        if ($license instanceof License) {
            $expiring = Document::query()
                ->where('documentable_type', 'company')
                ->where('documentable_id', $company->id)
                ->whereNull('replaced_by_id')
                ->where('verification_status', '!=', 'rejected')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', $license->valid_to->toDateString())
                ->orderBy('id')
                ->get();

            foreach ($expiring as $document) {
                $actions[] = [
                    'kind' => 'document',
                    'text' => $document->title.' expires before the license ends',
                ];
            }

            if ($license->documents_status === 'incomplete') {
                $missing = ApplicationChecklistItem::query()
                    ->where('application_id', $license->application_id)
                    ->whereHas('source', fn ($query) => $query->where('is_required', true))
                    ->whereNotIn('status', ['verified', 'not_applicable'])
                    ->count();
                $label = $missing === 1 ? '1 item' : $missing.' items';
                $actions[] = [
                    'kind' => 'incomplete',
                    'text' => 'Documents incomplete for the current license: '.$label,
                ];
            }
        }

        if ($verified < $minimum) {
            $actions[] = [
                'kind' => 'staff',
                'text' => 'Only '.$verified.' verified technical staff (minimum '.$minimum.')',
            ];
        }

        if ($pendingStaff > 0 || $pendingDocuments > 0) {
            $actions[] = [
                'kind' => 'waiting',
                'text' => 'Waiting for the Directorate: '.$pendingStaff.' staff, '.$pendingDocuments.' documents',
            ];
        }

        return $actions;
    }
}
