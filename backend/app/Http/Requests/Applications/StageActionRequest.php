<?php

namespace App\Http\Requests\Applications;

use Illuminate\Foundation\Http\FormRequest;

class StageActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('applications.create')
            || $user->can('applications.process')
            || $user->can('challans.verify')
            || $user->can('licenses.issue');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $skip = str_ends_with($this->path(), '/skip');

        return [
            'remarks' => [$skip ? 'required' : 'nullable', 'string', 'min:3', 'max:2000'],
        ];
    }
}
