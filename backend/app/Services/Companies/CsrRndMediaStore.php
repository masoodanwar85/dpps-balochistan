<?php

namespace App\Services\Companies;

use App\Models\Company;
use App\Models\CompanyCsrRndFile;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\SettingValue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CsrRndMediaStore
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
     * @return list<array{value: string, label: string}>
     */
    public function kindsFor(Company $company): array
    {
        $kinds = [];

        if ($company->csr) {
            $kinds[] = ['value' => 'csr', 'label' => 'CSR'];
        }

        if ($company->rnd) {
            $kinds[] = ['value' => 'rnd', 'label' => 'R&D'];
        }

        return $kinds;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Company $company, ?string $kind = null): array
    {
        return CompanyCsrRndFile::query()
            ->where('company_id', $company->id)
            ->when($kind !== null && $kind !== '', fn ($query) => $query->where('kind', $kind))
            ->orderByDesc('id')
            ->get()
            ->map(fn (CompanyCsrRndFile $file) => $this->present($file))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(CompanyCsrRndFile $file): array
    {
        return [
            'id' => $file->id,
            'company_id' => $file->company_id,
            'kind' => $file->kind,
            'kind_label' => $file->kind === 'rnd' ? 'R&D' : 'CSR',
            'title' => $file->title,
            'original_name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
            'uploaded_via' => $file->uploaded_via,
            'created_at' => $file->created_at?->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, UploadedFile $file, array $data, User $actor): CompanyCsrRndFile
    {
        $kind = (string) $data['kind'];
        $this->assertKindAllowed($company, $kind);

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

        $extension = self::ALLOWED[$mime];
        $path = 'csr-rnd/'.$company->id.'/'.Str::uuid()->toString().'.'.$extension;
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false || Storage::disk(self::DISK)->put($path, $contents) === false) {
            throw ValidationException::withMessages([
                'file' => ['The file could not be stored.'],
            ]);
        }

        $row = CompanyCsrRndFile::query()->create([
            'company_id' => $company->id,
            'kind' => $kind,
            'title' => $data['title'],
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'file_hash' => hash_file('sha256', $file->getRealPath()) ?: '',
            'uploaded_by' => $actor->id,
            'uploaded_via' => $actor->user_type === 'company' ? 'portal' : 'office',
        ]);

        $this->logger->log(
            'created',
            'Uploaded CSR/R&D file '.$row->title,
            'company_csr_rnd_file',
            $row->id,
            $actor,
            null,
            $this->present($row),
        );

        return $row;
    }

    /**
     * @return array{url: string, expires_at: string}
     */
    public function downloadUrl(CompanyCsrRndFile $file, User $actor): array
    {
        $expires = now()->addMinutes(5);
        $url = Storage::disk(self::DISK)->temporaryUrl($file->file_path, $expires);

        $this->logger->log(
            'downloaded',
            'Opened CSR/R&D file '.$file->title,
            'company_csr_rnd_file',
            $file->id,
            $actor,
        );

        return [
            'url' => $url,
            'expires_at' => $expires->toIso8601String(),
        ];
    }

    public function assertAccessible(CompanyCsrRndFile $file, User $actor): void
    {
        if ($actor->user_type === 'company' && (int) $actor->company_id !== (int) $file->company_id) {
            abort(404);
        }
    }

    private function assertKindAllowed(Company $company, string $kind): void
    {
        if ($kind === 'csr' && ! $company->csr) {
            throw ValidationException::withMessages([
                'kind' => ['CSR is not enabled for this company.'],
            ]);
        }

        if ($kind === 'rnd' && ! $company->rnd) {
            throw ValidationException::withMessages([
                'kind' => ['R&D is not enabled for this company.'],
            ]);
        }

        if (! in_array($kind, ['csr', 'rnd'], true)) {
            throw ValidationException::withMessages([
                'kind' => ['Choose CSR or R&D.'],
            ]);
        }
    }

    private function mime(UploadedFile $file): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file->getRealPath());

        return is_string($mime) ? $mime : '';
    }
}
