<?php

namespace App\Http\Requests\Companies;

use App\Services\Companies\CsrRndMediaStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCsrRndFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.upload') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) ceil(app(CsrRndMediaStore::class)->maxBytes() / 1024);

        return [
            'kind' => ['required', Rule::in(['csr', 'rnd'])],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:'.$maxKilobytes],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $megabytes = (int) (app(CsrRndMediaStore::class)->maxBytes() / 1024 / 1024);

        return [
            'file.max' => "This file is larger than the maximum of {$megabytes} MB.",
        ];
    }
}
