<?php

namespace App\Http\Requests\Dealers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveDealerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->isMethod('post') ? 'dealers.create' : 'dealers.update';

        return $this->user()?->can($permission) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['shop_name', 'business_address', 'mobile', 'email', 'warning_reason'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $merged[$field] = trim($this->input($field));
            }
        }

        foreach (['tehsil_id', 'gps_lat', 'gps_lng', 'mobile', 'email', 'warning_reason'] as $field) {
            $value = $merged[$field] ?? $this->input($field);

            if ($value === '') {
                $merged[$field] = null;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'max:250'],
            'district_id' => ['required', 'integer'],
            'tehsil_id' => ['nullable', 'integer'],
            'business_address' => ['required', 'string', 'max:2000'],
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'confirm_warnings' => ['sometimes', 'boolean'],
            'warning_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('confirm_warnings') && mb_strlen(trim((string) $this->input('warning_reason'))) < 3) {
                $validator->errors()->add('warning_reason', 'A reason is required to confirm this warning.');
            }
        });
    }
}
