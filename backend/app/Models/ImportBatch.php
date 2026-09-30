<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'file_name',
        'sheet_name',
        'imported_by',
        'started_at',
        'finished_at',
        'total_rows',
        'success_rows',
        'exception_rows',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * @return HasMany<ImportException, $this>
     */
    public function exceptions(): HasMany
    {
        return $this->hasMany(ImportException::class, 'batch_id');
    }

    public function committed(): bool
    {
        return $this->finished_at !== null && $this->status === 'completed';
    }

    public function type(): string
    {
        return $this->sheet_name === 'By District' ? 'dealers' : 'companies';
    }
}
