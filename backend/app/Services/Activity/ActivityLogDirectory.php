<?php

namespace App\Services\Activity;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ActivityLogDirectory
{
    /**
     * @var list<string>
     */
    public const ACTIONS = [
        'created',
        'updated',
        'deleted',
        'restored',
        'login',
        'logout',
        'login_failed',
        'viewed',
        'downloaded',
        'exported',
        'verified',
        'rejected',
        'approved',
        'issued',
        'suspended',
        'cancelled',
        'penalty_entered',
        'penalty_waived',
        'warning_overridden',
        'settings_changed',
        'template_published',
    ];

    /**
     * @var list<string>
     */
    private const HIDDEN = [
        'password',
        'password_confirmation',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @var list<string>
     */
    private const REASON_KEYS = [
        'warning_reason',
        'rejection_reason',
        'end_reason',
        'reason',
    ];

    /**
     * @return Builder<ActivityLog>
     */
    public function query(Request $request): Builder
    {
        $query = ActivityLog::query()->with('user:id,name');
        $userId = (string) $request->query('user_id', '');

        if ($userId === 'system') {
            $query->whereNull('user_id');
        } elseif ($userId !== '' && ctype_digit($userId)) {
            $query->where('user_id', (int) $userId);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->value());
        }

        if ($request->filled('module')) {
            $query->where('subject_type', $request->string('module')->value());
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->string('from')->value());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->string('to')->value());
        }

        return $query;
    }

    /**
     * @return array{users: list<array{id: int, name: string}>, actions: list<string>, modules: list<string>, has_system: bool}
     */
    public function options(): array
    {
        $userIds = ActivityLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id');
        $usedActions = ActivityLog::query()->distinct()->orderBy('action')->pluck('action')->all();
        $modules = ActivityLog::query()->distinct()->orderBy('subject_type')->pluck('subject_type')->all();

        return [
            'users' => User::query()
                ->whereIn('id', $userIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->values()
                ->all(),
            'actions' => array_values(array_unique([...self::ACTIONS, ...$usedActions])),
            'modules' => array_values($modules),
            'has_system' => ActivityLog::query()->whereNull('user_id')->exists(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ActivityLog $log): array
    {
        $old = $this->withoutHidden($log->old_values ?? []);
        $new = $this->withoutHidden($log->new_values ?? []);
        $reason = $this->reason($new);

        return [
            'id' => $log->id,
            'created_at' => $log->created_at?->toIso8601String(),
            'user_id' => $log->user_id,
            'user_name' => $log->user?->name ?? ($log->user_type === 'system' ? 'System' : 'Unknown'),
            'action' => $log->action,
            'record' => $log->description,
            'module' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'ip_address' => $log->ip_address,
            'old_values' => $this->withoutReasons($old),
            'new_values' => $this->withoutReasons($new),
            'changes' => $this->changes($old, $new),
            'reason' => $reason,
        ];
    }

    /**
     * @param  Collection<int, ActivityLog>  $logs
     * @return Collection<int, list<string>>
     */
    public function exportRows(Collection $logs): Collection
    {
        return $logs->map(function (ActivityLog $log) {
            $row = $this->present($log);

            return [
                $log->created_at?->timezone((string) config('app.timezone'))->format('d-m-Y H:i') ?? '',
                (string) $row['user_name'],
                (string) $row['action'],
                (string) $row['record'],
                (string) $row['module'],
                (string) $row['ip_address'],
                $this->joinChanges($row['changes'], 'old'),
                $this->joinChanges($row['changes'], 'new'),
                (string) ($row['reason'] ?? ''),
            ];
        })->values();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutHidden(array $values): array
    {
        foreach (self::HIDDEN as $key) {
            unset($values[$key]);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function reason(array $values): ?string
    {
        foreach (self::REASON_KEYS as $key) {
            $value = $values[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutReasons(array $values): array
    {
        unset($values['warnings']);

        foreach (self::REASON_KEYS as $key) {
            unset($values[$key]);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return list<array{field: string, old: ?string, new: ?string}>
     */
    private function changes(array $old, array $new): array
    {
        $old = $this->withoutReasons($old);
        $new = $this->withoutReasons($new);
        $fields = array_values(array_unique([...array_keys($old), ...array_keys($new)]));
        $rows = [];

        foreach ($fields as $field) {
            $before = array_key_exists($field, $old) ? $old[$field] : null;
            $after = array_key_exists($field, $new) ? $new[$field] : null;

            if ($before === $after) {
                continue;
            }

            $rows[] = [
                'field' => $field,
                'old' => $this->displayValue($before),
                'new' => $this->displayValue($after),
            ];
        }

        return $rows;
    }

    private function displayValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * @param  list<array{field: string, old: ?string, new: ?string}>  $changes
     */
    private function joinChanges(array $changes, string $side): string
    {
        $lines = [];

        foreach ($changes as $change) {
            $value = $change[$side] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $lines[] = $change['field'].': '.$value;
        }

        return implode("\n", $lines);
    }
}
