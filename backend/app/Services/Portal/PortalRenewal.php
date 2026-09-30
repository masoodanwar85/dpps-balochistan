<?php

namespace App\Services\Portal;

use App\Models\ApplicationChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\Company;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Applications\ApplicationChecklist;
use App\Services\Applications\ApplicationFees;
use App\Services\Applications\ApplicationNumber;
use App\Services\Applications\ApplicationStages;
use App\Services\Documents\DocumentStore;
use App\Services\Notifications\InAppNotifications;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PortalRenewal
{
    public const DECLARATION = 'I certify that the information and documents in this application are correct.';

    public function __construct(
        private PortalHome $home,
        private ApplicationNumber $numbers,
        private ApplicationStages $stages,
        private ApplicationFees $fees,
        private ApplicationChecklist $checklist,
        private DocumentStore $documents,
        private ActivityLogger $logger,
        private InAppNotifications $notifications,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(Company $company): array
    {
        $dashboard = $this->home->dashboard($company);
        $draft = $this->openDraft($company);

        return [
            'renewal' => $dashboard['renewal'],
            'license' => $dashboard['license'],
            'verified_technical_staff' => $dashboard['verified_technical_staff'],
            'minimum_technical_staff' => $dashboard['minimum_technical_staff'],
            'declaration' => self::DECLARATION,
            'draft' => $draft instanceof LicenseApplication ? $this->draft($draft) : null,
            'document_types' => $this->documents->typesForCompany(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function start(Company $company, User $actor): array
    {
        $existing = $this->openDraft($company);

        if ($existing instanceof LicenseApplication) {
            return $this->draft($existing);
        }

        $license = $this->currentLicense($company);
        $this->assertWindow($license);
        $this->assertNoOtherOpen($company);

        $template = ChecklistTemplate::query()
            ->where('entity_type', 'company')
            ->where('application_type', 'renewal')
            ->where('status', 'published')
            ->first();

        if (! $template instanceof ChecklistTemplate) {
            throw ValidationException::withMessages([
                'checklist_template_id' => ['No published checklist for this application.'],
            ]);
        }

        $application = DB::transaction(function () use ($company, $actor, $license, $template) {
            $this->assertNoOtherOpen($company);

            $application = LicenseApplication::query()->create([
                'application_no' => $this->numbers->next('C'),
                'licensable_type' => 'company',
                'licensable_id' => $company->id,
                'application_type' => 'renewal',
                'checklist_template_id' => $template->id,
                'previous_license_id' => $license?->id,
                'submitted_via' => 'portal',
                'submitted_by_user_id' => $actor->id,
                'submitted_at' => null,
                'status' => 'draft',
                'late_days' => $this->lateDays($license),
                'fee_amount' => 0,
                'penalty_total' => 0,
                'total_payable' => 0,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($template->items()->orderBy('sort_order')->get() as $item) {
                $application->checklistItems()->create([
                    'checklist_item_id' => $item->id,
                    'title_snapshot' => $item->title,
                    'annex_code_snapshot' => $item->annex_code,
                    'status' => 'pending',
                ]);
            }

            $this->logger->log(
                'created',
                'Started renewal draft '.$application->application_no,
                'license_application',
                $application->id,
                $actor,
                null,
                ['status' => 'draft', 'submitted_via' => 'portal'],
            );

            return $application;
        });

        return $this->draft($application->refresh());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upload(Company $company, int $itemId, UploadedFile $file, array $data, User $actor): void
    {
        $application = $this->ownOpen($company);
        $item = ApplicationChecklistItem::query()
            ->with('source')
            ->where('application_id', $application->id)
            ->whereKey($itemId)
            ->first();

        if (! $item instanceof ApplicationChecklistItem) {
            abort(404);
        }

        if (! $item->source?->portal_uploadable) {
            throw ValidationException::withMessages([
                'file' => ['This item cannot be uploaded through the portal.'],
            ]);
        }

        $this->checklist->upload($application, $item, $file, $data, $actor);

        if (array_key_exists('page_count', $data) && $data['page_count'] !== null) {
            $item->page_count = (int) $data['page_count'];
            $item->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(Company $company, array $data, User $actor): LicenseApplication
    {
        $application = $this->openDraft($company);

        if (! $application instanceof LicenseApplication) {
            throw ValidationException::withMessages([
                'status' => ['Start the renewal before submitting it.'],
            ]);
        }

        $license = $this->currentLicense($company);
        $this->assertWindow($license);
        $application->status = 'submitted';
        $application->submitted_at = now();
        $application->total_pages = (int) $data['total_pages'];
        $application->late_days = $this->lateDays($license);
        $application->updated_by = $actor->id;
        $application->save();
        $this->stages->start($application, $actor);

        $this->logger->log(
            'updated',
            'Submitted renewal '.$application->application_no,
            'license_application',
            $application->id,
            $actor,
            ['status' => 'draft'],
            [
                'status' => 'submitted',
                'total_pages' => $application->total_pages,
                'declarant_name' => $data['declarant_name'],
                'declarant_cnic' => $data['declarant_cnic'],
            ],
        );
        $this->notifications->portalSubmitted(
            'Portal renewal submitted',
            $company->name.' submitted renewal '.$application->application_no.'.',
            ['application_id' => $application->id, 'company_id' => $company->id],
        );

        return $application->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function draft(LicenseApplication $application): array
    {
        $quote = $this->fees->quote($application);
        $presented = $this->home->application($application);
        $presented['fee'] = $quote;
        $presented['fee_note'] = 'Penalties, if any, will be determined by the Directorate.';

        return $presented;
    }

    private function openDraft(Company $company): ?LicenseApplication
    {
        $row = LicenseApplication::query()
            ->where('licensable_type', 'company')
            ->where('licensable_id', $company->id)
            ->where('status', 'draft')
            ->first();

        return $row instanceof LicenseApplication ? $row : null;
    }

    private function ownOpen(Company $company): LicenseApplication
    {
        $row = LicenseApplication::query()
            ->where('licensable_type', 'company')
            ->where('licensable_id', $company->id)
            ->whereNotIn('status', ['issued', 'rejected', 'withdrawn'])
            ->orderByDesc('id')
            ->first();

        if (! $row instanceof LicenseApplication) {
            abort(404);
        }

        return $row;
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

    private function assertWindow(?License $license): void
    {
        if (! $license instanceof License) {
            throw ValidationException::withMessages([
                'application_type' => ['A renewal cannot be submitted before the renewal window opens.'],
            ]);
        }

        $days = ApplicationStages::settingDays('renewal_window_days', 60);
        $opensOn = $license->valid_to->copy()->startOfDay()->subDays($days);

        if (Carbon::today()->lt($opensOn)) {
            throw ValidationException::withMessages([
                'application_type' => ['A renewal cannot be submitted before the renewal window opens.'],
            ]);
        }
    }

    private function assertNoOtherOpen(Company $company): void
    {
        $open = LicenseApplication::query()
            ->where('licensable_type', 'company')
            ->where('licensable_id', $company->id)
            ->whereNotIn('status', ['issued', 'rejected', 'withdrawn', 'draft'])
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'licensable_id' => ['This company already has an open application.'],
            ]);
        }
    }

    private function lateDays(?License $license): int
    {
        if (! $license instanceof License) {
            return 0;
        }

        $expiry = $license->valid_to->copy()->startOfDay();

        if (Carbon::today()->lte($expiry)) {
            return 0;
        }

        return (int) $expiry->diffInDays(Carbon::today());
    }
}
