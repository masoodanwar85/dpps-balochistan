<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Document extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'document_type_id',
        'title',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'file_hash',
        'issue_date',
        'expiry_date',
        'attested_by',
        'version_no',
        'replaced_by_id',
        'uploaded_by',
        'uploaded_via',
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'verified_at' => 'datetime',
            'size_bytes' => 'integer',
            'version_no' => 'integer',
        ];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function resolveRouteBinding($value, $field = null): Model
    {
        $row = $this->newQuery()->where($field ?? $this->getRouteKeyName(), $value)->first();

        if (! $row instanceof self || ! in_array($row->documentable_type, ['company', 'dealer', 'application_checklist_item', 'challan'], true)) {
            abort(404);
        }

        $user = Auth::user();
        $actor = $user instanceof User ? $user : null;

        if ($row->documentable_type === 'application_checklist_item') {
            $item = ApplicationChecklistItem::query()->find($row->documentable_id);
            $visible = $item instanceof ApplicationChecklistItem
                && LicenseApplication::query()->visibleTo($actor)->whereKey($item->application_id)->exists();

            if (! $visible) {
                abort(404);
            }

            return $row;
        }

        if ($row->documentable_type === 'challan') {
            $challan = Challan::query()->find($row->documentable_id);
            $visible = $challan instanceof Challan
                && LicenseApplication::query()->visibleTo($actor)->whereKey($challan->application_id)->exists();

            if (! $visible) {
                abort(404);
            }

            return $row;
        }

        $visible = $row->documentable_type === 'dealer'
            ? Dealer::query()->visibleTo($actor)->whereKey($row->documentable_id)->exists()
            : Company::query()->visibleTo($actor)->whereKey($row->documentable_id)->exists();

        if (! $visible) {
            abort(404);
        }

        return $row;
    }
}
