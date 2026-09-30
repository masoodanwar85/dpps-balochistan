<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LicenseApplication extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'application_no',
        'licensable_type',
        'licensable_id',
        'application_type',
        'checklist_template_id',
        'previous_license_id',
        'submitted_via',
        'submitted_by_user_id',
        'submitted_at',
        'diary_no',
        'received_by',
        'received_at',
        'total_pages',
        'current_stage_id',
        'status',
        'late_days',
        'fee_amount',
        'penalty_total',
        'total_payable',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'received_at' => 'datetime',
            'fee_amount' => 'decimal:2',
            'penalty_total' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'late_days' => 'integer',
            'total_pages' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'current_stage_id');
    }

    public function stageLogs(): HasMany
    {
        return $this->hasMany(ApplicationStageLog::class, 'application_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ApplicationChecklistItem::class, 'application_id');
    }

    public function letters(): HasMany
    {
        return $this->hasMany(DeficiencyLetter::class, 'application_id');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(ApplicationPenalty::class, 'application_id');
    }

    public function challans(): HasMany
    {
        return $this->hasMany(Challan::class, 'application_id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['issued', 'rejected', 'withdrawn'], true);
    }

    public function applicantName(): string
    {
        if ($this->licensable_type === 'company') {
            return Company::withTrashed()->find($this->licensable_id)?->name ?? 'Company';
        }

        return Dealer::withTrashed()->find($this->licensable_id)?->shop_name ?? 'Dealer';
    }

    /**
     * @param  Builder<LicenseApplication>  $query
     * @return Builder<LicenseApplication>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User || ! $user->can('applications.view')) {
            return $query->whereRaw('0 = 1');
        }

        if ($user->hasRole('District Officer')) {
            $districtIds = $user->districts()->pluck('districts.id')->all();
            $dealerIds = Dealer::withTrashed()
                ->whereIn('district_id', $districtIds === [] ? [0] : $districtIds)
                ->pluck('id')
                ->all();

            return $query
                ->where('licensable_type', 'dealer')
                ->whereIn('licensable_id', $dealerIds === [] ? [0] : $dealerIds);
        }

        return $query;
    }

    public function resolveRouteBinding($value, $field = null): Model
    {
        $field ??= $this->getRouteKeyName();
        $user = Auth::user();

        return $this->newQuery()
            ->where($field, $value)
            ->visibleTo($user instanceof User ? $user : null)
            ->firstOrFail();
    }
}
