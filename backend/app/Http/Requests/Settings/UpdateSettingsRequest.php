<?php

namespace App\Http\Requests\Settings;

use App\Models\Setting;
use App\Support\SettingValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $input = $this->input('settings');

            if (! is_array($input)) {
                return;
            }

            $known = Setting::query()->get()->keyBy('key');

            foreach ($input as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }

                $setting = $known->get($key);

                if ($setting === null) {
                    $validator->errors()->add('settings.'.$key, 'Unknown setting.');

                    continue;
                }

                if (! $setting->is_ui_editable) {
                    $validator->errors()->add('settings.'.$key, 'This setting cannot be changed from the screen.');

                    continue;
                }

                $message = SettingValue::error($setting, $value);

                if ($message !== null) {
                    $validator->errors()->add('settings.'.$key, $message);
                }
            }
        });
    }
}
