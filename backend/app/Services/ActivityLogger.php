<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Str;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        string $description,
        string $subjectType,
        int $subjectId,
        ?User $actor,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $channel = 'web',
    ): ActivityLog {
        $request = request();
        $batchUuid = $request->attributes->get('activity_batch_uuid');

        if (! is_string($batchUuid) || $batchUuid === '') {
            $batchUuid = (string) Str::uuid();
            $request->attributes->set('activity_batch_uuid', $batchUuid);
        }

        $userAgent = trim((string) $request->userAgent());

        return ActivityLog::query()->create([
            'user_id' => $actor?->id,
            'user_type' => $actor->user_type ?? 'system',
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => mb_substr($description, 0, 255),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => mb_substr((string) ($request->ip() ?: '0.0.0.0'), 0, 45),
            'user_agent' => mb_substr($userAgent !== '' ? $userAgent : 'unknown', 0, 255),
            'channel' => $channel,
            'route' => mb_substr('/'.$request->path(), 0, 150),
            'batch_uuid' => $batchUuid,
            'created_at' => now(),
        ]);
    }
}
