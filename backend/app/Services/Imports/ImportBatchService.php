<?php

namespace App\Services\Imports;

use App\Models\ImportBatch;
use App\Models\ImportException;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportBatchService
{
    public function __construct(
        private CompanyImport $companies,
        private DealerImport $dealers,
        private ActivityLogger $logger,
    ) {}

    public function store(UploadedFile $file, string $type, string $mode, User $actor): ImportBatch
    {
        $batch = ImportBatch::query()->create([
            'file_name' => $file->getClientOriginalName(),
            'sheet_name' => $type === 'dealers' ? 'By District' : 'Company Details',
            'imported_by' => $actor->id,
            'started_at' => now(),
            'total_rows' => 0,
            'success_rows' => 0,
            'exception_rows' => 0,
            'status' => 'running',
        ]);
        $file->storeAs('imports', $batch->id.'.xlsx', 'local');

        try {
            $plan = $this->plan($batch, []);
        } catch (Throwable $exception) {
            $batch->forceFill(['status' => 'failed', 'finished_at' => now()])->save();

            throw ValidationException::withMessages([
                'file' => [$exception->getMessage()],
            ]);
        }

        foreach ($plan['exceptions'] as $exception) {
            $batch->exceptions()->create([
                ...$exception,
                'resolution_status' => 'open',
            ]);
        }

        $batch->forceFill([
            'total_rows' => $plan['total'],
            'success_rows' => $plan['ready'],
            'exception_rows' => count($plan['exceptions']),
            'status' => 'completed',
        ])->save();
        $this->logger->log('created', 'Dry run of '.$batch->file_name, 'import_batch', $batch->id, $actor);

        if ($mode === 'run' && $plan['exceptions'] === []) {
            return $this->commit($batch->fresh() ?? $batch, $actor);
        }

        return $batch->fresh() ?? $batch;
    }

    public function commit(ImportBatch $batch, User $actor): ImportBatch
    {
        if ($batch->committed()) {
            throw ValidationException::withMessages([
                'batch' => ['This import has already been written.'],
            ]);
        }

        if ($batch->exceptions()->where('resolution_status', 'open')->exists()) {
            throw ValidationException::withMessages([
                'batch' => ['Resolve every open exception before running this import.'],
            ]);
        }

        $plan = $this->plan($batch, $this->resolutions($batch));

        if ($plan['exceptions'] !== []) {
            throw ValidationException::withMessages([
                'batch' => [$plan['exceptions'][0]['issue_details']],
            ]);
        }

        DB::transaction(function () use ($batch, $plan, $actor) {
            $written = $batch->type() === 'dealers'
                ? $this->dealers->commit($plan['records'], $actor)
                : $this->companies->commit($plan['records'], $actor);
            $batch->forceFill([
                'success_rows' => $written,
                'finished_at' => now(),
                'status' => 'completed',
            ])->save();
            $this->logger->log('created', 'Imported '.$written.' rows from '.$batch->file_name, 'import_batch', $batch->id, $actor);
        });

        return $batch->fresh() ?? $batch;
    }

    /**
     * @param  array{resolution: string, target_id?: int|null, fields?: array<string, string>, comment?: string|null}  $data
     */
    public function resolve(ImportException $exception, array $data, User $actor): ImportException
    {
        if ($exception->batch?->committed()) {
            throw ValidationException::withMessages([
                'resolution' => ['This import has already been written.'],
            ]);
        }

        $decision = $data['resolution'];
        $exception->forceFill([
            'resolution_status' => match ($decision) {
                'skip' => 'skipped',
                'merge' => 'merged',
                default => 'fixed',
            },
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
            'notes' => json_encode([
                'decision' => $decision,
                'target_id' => $data['target_id'] ?? null,
                'fields' => $data['fields'] ?? [],
                'comment' => $data['comment'] ?? null,
            ]),
        ])->save();

        return $exception->refresh();
    }

    /**
     * @param  list<array{decision: string, fields: array<string, string>, target_id: ?int, row: int, issue_type: string, issue_details: string}>  $resolutions
     * @return array{total: int, ready: int, exceptions: list<array<string, mixed>>, records: list<array<string, mixed>>}
     */
    private function plan(ImportBatch $batch, array $resolutions): array
    {
        $path = Storage::disk('local')->path('imports/'.$batch->id.'.xlsx');

        return $batch->type() === 'dealers'
            ? $this->dealers->prepare($path, $resolutions)
            : $this->companies->prepare($path, $resolutions);
    }

    /**
     * @return list<array{decision: string, fields: array<string, string>, target_id: ?int, row: int, issue_type: string, issue_details: string}>
     */
    private function resolutions(ImportBatch $batch): array
    {
        return $batch->exceptions->map(function (ImportException $exception) {
            $notes = json_decode((string) $exception->notes, true);

            return [
                'row' => $exception->row_no,
                'issue_type' => $exception->issue_type,
                'issue_details' => $exception->issue_details,
                'decision' => is_array($notes) ? (string) ($notes['decision'] ?? 'skip') : 'skip',
                'fields' => is_array($notes) && is_array($notes['fields'] ?? null) ? $notes['fields'] : [],
                'target_id' => is_array($notes) && isset($notes['target_id']) ? (int) $notes['target_id'] : null,
            ];
        })->all();
    }
}
