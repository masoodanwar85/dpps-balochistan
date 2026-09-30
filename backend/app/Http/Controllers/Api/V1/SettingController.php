<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\Settings\SettingsWriter;
use App\Support\ApiResponse;
use App\Support\NumberPattern;
use App\Support\SettingValue;
use Illuminate\Http\JsonResponse;

class SettingController
{
    public function __construct(private SettingsWriter $settings) {}

    public function index(): JsonResponse
    {
        $order = ['licensing' => 0, 'alerts' => 1, 'numbering' => 2, 'general' => 3];

        $settings = Setting::query()
            ->where('is_ui_editable', true)
            ->get()
            ->sortBy(fn (Setting $setting) => ($order[$setting->group] ?? 9) * 1000 + $setting->id)
            ->values();

        return ApiResponse::success($settings->map(fn (Setting $setting) => $this->payload($setting))->all());
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $known = Setting::query()->where('is_ui_editable', true)->get()->keyBy('key');
        $this->settings->update($request->validated('settings'), $known, $request->user());

        return $this->index();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Setting $setting): array
    {
        $value = SettingValue::typed($setting);

        return [
            'key' => $setting->key,
            'value' => $value,
            'data_type' => $setting->data_type,
            'group' => $setting->group,
            'label' => $setting->label,
            'description' => $setting->description,
            'preview' => str_ends_with($setting->key, '_pattern') && is_string($value)
                ? NumberPattern::preview($value)
                : null,
        ];
    }
}
