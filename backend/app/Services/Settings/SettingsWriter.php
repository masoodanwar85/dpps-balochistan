<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\SettingValue;
use Illuminate\Support\Collection;

class SettingsWriter
{
    public function __construct(private ActivityLogger $activity) {}

    /**
     * @param  array<string, mixed>  $values
     * @param  Collection<string, Setting>  $settings
     */
    public function update(array $values, Collection $settings, User $actor): void
    {
        foreach ($values as $key => $value) {
            $setting = $settings->get($key);

            if ($setting === null) {
                continue;
            }

            $stored = SettingValue::store($setting, $value);

            if ($setting->value === $stored) {
                continue;
            }

            $previous = $setting->value;
            $setting->value = $stored;
            $setting->save();

            $this->activity->log(
                'updated',
                'Updated setting '.$setting->key.'.',
                'setting',
                (int) $setting->id,
                $actor,
                ['value' => $previous],
                ['value' => $stored],
            );
        }
    }
}
