<?php

namespace App\Services\Documents;

use App\Models\ApplicationChecklistItem;
use App\Models\Challan;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\LicenseApplication;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\SettingValue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentStore
{
    public const DISK = 'local';

    /**
     * @var array<string, string>
     */
    private const ALLOWED = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(private ActivityLogger $logger) {}

    public function maxBytes(): int
    {
        $setting = Setting::query()->where('key', 'max_upload_size_mb')->first();
        $megabytes = 10;

        if ($setting) {
            $value = SettingValue::typed($setting);

            if (is_int($value) && $value > 0) {
                $megabytes = $value;
            }
        }

        return $megabytes * 1024 * 1024;
    }

    /**
     * @return list<array{id: int, name: string, category: string, has_expiry: bool}>
     */
    public function typesForCompany(): array
    {
        return $this->typesFor('company');
    }

    /**
     * @return list<array{id: int, name: string, category: string, has_expiry: bool}>
     */
    public function typesFor(string $owner): array
    {
        return DocumentType::query()
            ->where('is_active', true)
            ->whereIn('applies_to', [$owner, 'any'])
            ->orderBy('name')
            ->get()
            ->map(fn (DocumentType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'category' => $type->category,
                'has_expiry' => $type->has_expiry,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Document $document): array
    {
        $document->loadMissing('documentType');

        return [
            'id' => $document->id,
            'documentable_type' => $document->documentable_type,
            'company_id' => $document->documentable_type === 'company' ? $document->documentable_id : null,
            'dealer_id' => $document->documentable_type === 'dealer' ? $document->documentable_id : null,
            'checklist_item_id' => $document->documentable_type === 'application_checklist_item' ? $document->documentable_id : null,
            'challan_id' => $document->documentable_type === 'challan' ? $document->documentable_id : null,
            'document_type_id' => $document->document_type_id,
            'type_name' => $document->documentType?->name,
            'category' => $document->documentType?->category,
            'title' => $document->title,
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'issue_date' => $document->issue_date?->toDateString(),
            'expiry_date' => $document->expiry_date?->toDateString(),
            'attested_by' => $document->attested_by,
            'version_no' => $document->version_no,
            'uploaded_via' => $document->uploaded_via,
            'verification_status' => $document->verification_status,
            'current' => $document->replaced_by_id === null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, UploadedFile $file, array $data, User $actor): Document
    {
        return $this->store('company', $company->id, $file, $data, $actor, null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForDealer(Dealer $dealer, UploadedFile $file, array $data, User $actor): Document
    {
        return $this->store('dealer', $dealer->id, $file, $data, $actor, null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForChecklistItem(int $itemId, UploadedFile $file, array $data, User $actor): Document
    {
        return $this->store('application_checklist_item', $itemId, $file, $data, $actor, null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForChallan(int $challanId, UploadedFile $file, array $data, User $actor): Document
    {
        return $this->store('challan', $challanId, $file, $data, $actor, null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function replace(Document $previous, UploadedFile $file, array $data, User $actor): Document
    {
        if ($previous->replaced_by_id !== null) {
            throw ValidationException::withMessages([
                'file' => ['Replace the current version of this document.'],
            ]);
        }

        return $this->store($previous->documentable_type, $previous->documentable_id, $file, $data, $actor, $previous);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Document $document, array $data, User $actor): Document
    {
        $old = $this->present($document);
        $document->fill([
            'document_type_id' => $data['document_type_id'],
            'title' => $data['title'],
            'issue_date' => $data['issue_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'attested_by' => $data['attested_by'],
        ]);
        $document->save();

        $this->logger->log(
            'updated',
            'Updated document '.$document->title,
            'document',
            $document->id,
            $actor,
            $old,
            $this->present($document),
        );

        return $document->refresh();
    }

    public function verify(Document $document, User $actor): Document
    {
        if ($document->replaced_by_id !== null) {
            throw ValidationException::withMessages([
                'file' => ['Only the current version can be verified.'],
            ]);
        }

        if ($document->verification_status === 'verified') {
            throw ValidationException::withMessages([
                'verification_status' => ['This document is already verified.'],
            ]);
        }

        $old = $this->present($document);
        $document->fill([
            'verification_status' => 'verified',
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ]);
        $document->save();

        $this->logger->log(
            'approved',
            'Verified document '.$document->title,
            'document',
            $document->id,
            $actor,
            $old,
            $this->present($document),
        );

        return $document->refresh();
    }

    /**
     * @return array{url: string, expires_at: string}
     */
    public function downloadUrl(Document $document, User $actor): array
    {
        $expires = now()->addMinutes(5);
        $url = Storage::disk(self::DISK)->temporaryUrl($document->file_path, $expires);

        $this->logger->log(
            'downloaded',
            'Opened document '.$document->title,
            'document',
            $document->id,
            $actor,
        );

        return [
            'url' => $url,
            'expires_at' => $expires->toIso8601String(),
        ];
    }

    /**
     * @return list<Document>
     */
    public function history(Document $document): array
    {
        $latest = $document;

        while ($latest->replaced_by_id !== null) {
            $next = Document::query()->find($latest->replaced_by_id);

            if (! $next instanceof Document) {
                break;
            }

            $latest = $next;
        }

        $rows = [];
        $cursor = $latest;

        while ($cursor instanceof Document) {
            $rows[] = $cursor;
            $cursor = Document::query()->where('replaced_by_id', $cursor->id)->first();
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function store(string $ownerType, int $ownerId, UploadedFile $file, array $data, User $actor, ?Document $previous): Document
    {
        $mime = $this->mime($file);

        if (! isset(self::ALLOWED[$mime])) {
            throw ValidationException::withMessages([
                'file' => ['This file type is not allowed. Upload a PDF, JPG, or PNG.'],
            ]);
        }

        if ($file->getSize() === false || $file->getSize() < 1) {
            throw ValidationException::withMessages([
                'file' => ['Choose a file.'],
            ]);
        }

        if ($file->getSize() > $this->maxBytes()) {
            $megabytes = (int) ($this->maxBytes() / 1024 / 1024);

            throw ValidationException::withMessages([
                'file' => ["This file is larger than the maximum of {$megabytes} MB."],
            ]);
        }

        $hash = hash_file('sha256', $file->getRealPath());
        $warnings = $actor->user_type === 'company' ? [] : $this->duplicateWarnings($hash, $ownerType, $ownerId);

        if ($warnings !== [] && empty($data['confirm_warnings'])) {
            throw new DocumentWarningException($warnings);
        }

        $extension = self::ALLOWED[$mime];
        $path = 'documents/'.$ownerType.'/'.$ownerId.'/'.Str::uuid()->toString().'.'.$extension;
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false || Storage::disk(self::DISK)->put($path, $contents) === false) {
            throw ValidationException::withMessages([
                'file' => ['The file could not be stored.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($ownerType, $ownerId, $file, $data, $actor, $previous, $mime, $hash, $path, $warnings) {
                $document = Document::query()->create([
                    'documentable_type' => $ownerType,
                    'documentable_id' => $ownerId,
                    'document_type_id' => $data['document_type_id'],
                    'title' => $data['title'],
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mime,
                    'size_bytes' => $file->getSize(),
                    'file_hash' => $hash,
                    'issue_date' => $data['issue_date'] ?? null,
                    'expiry_date' => $data['expiry_date'] ?? null,
                    'attested_by' => $data['attested_by'],
                    'version_no' => $previous ? $previous->version_no + 1 : 1,
                    'uploaded_by' => $actor->id,
                    'uploaded_via' => $actor->user_type === 'company' ? 'portal' : 'office',
                    'verification_status' => 'pending',
                ]);

                if ($previous instanceof Document) {
                    $previous->replaced_by_id = $document->id;
                    $previous->save();
                }

                if ($warnings !== []) {
                    $this->logger->log(
                        'warning_overridden',
                        'Warning confirmed: '.$data['warning_reason'],
                        'document',
                        $document->id,
                        $actor,
                        null,
                        [
                            'warnings' => $warnings,
                            'warning_reason' => $data['warning_reason'],
                        ],
                    );
                }

                $this->logger->log(
                    'created',
                    ($previous ? 'Replaced document ' : 'Uploaded document ').$document->title,
                    'document',
                    $document->id,
                    $actor,
                    null,
                    $this->present($document),
                );

                return $document;
            });
        } catch (\Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }
    }

    private function mime(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if (! is_string($path) || ! is_file($path)) {
            return '';
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($detected) ? $detected : '';
    }

    /**
     * @return list<string>
     */
    private function duplicateWarnings(string $hash, string $ownerType, int $ownerId): array
    {
        $matches = Document::query()
            ->where('file_hash', $hash)
            ->where(function ($query) use ($ownerType, $ownerId) {
                $query->where('documentable_type', '!=', $ownerType)
                    ->orWhere('documentable_id', '!=', $ownerId);
            })
            ->get();

        $warnings = [];

        foreach ($matches as $match) {
            $warnings[] = 'This file is already attached to '.$this->ownerName($match).'.';
        }

        return array_values(array_unique($warnings));
    }

    private function ownerName(Document $document): string
    {
        if ($document->documentable_type === 'company') {
            return Company::query()->find($document->documentable_id)?->name ?? 'another company';
        }

        if ($document->documentable_type === 'dealer') {
            return Dealer::query()->find($document->documentable_id)?->shop_name ?? 'another dealer';
        }

        if ($document->documentable_type === 'application_checklist_item') {
            $item = ApplicationChecklistItem::query()->find($document->documentable_id);
            $application = $item ? LicenseApplication::query()->find($item->application_id) : null;

            return $application instanceof LicenseApplication
                ? $application->applicantName()
                : 'another application';
        }

        if ($document->documentable_type === 'challan') {
            $challan = Challan::query()->find($document->documentable_id);
            $application = $challan ? LicenseApplication::query()->find($challan->application_id) : null;

            return $application instanceof LicenseApplication
                ? $application->applicantName()
                : 'another application';
        }

        return 'another record';
    }
}
