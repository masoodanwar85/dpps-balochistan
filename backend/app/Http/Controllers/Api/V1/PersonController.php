<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Persons\UpdatePersonRequest;
use App\Models\Person;
use App\Services\Persons\PersonProfile;
use App\Services\Persons\PersonRules;
use App\Services\Persons\PersonWarningException;
use App\Services\Persons\PersonWriter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function lookup(Request $request, PersonRules $rules, PersonProfile $profile): JsonResponse
    {
        $digits = $rules->digits((string) $request->query('cnic', ''));

        if (! preg_match('/^\d{13}$/', $digits)) {
            return ApiResponse::error([
                'cnic' => ['CNIC must be exactly 13 digits.'],
            ], 422);
        }

        $person = Person::query()->where('cnic', $digits)->first();

        if (! $person) {
            return ApiResponse::success([
                'found' => false,
                'person' => null,
            ]);
        }

        return ApiResponse::success([
            'found' => true,
            'person' => $profile->present($person, $request->user()?->can('persons.update') ?? false),
        ]);
    }

    public function show(Request $request, Person $person, PersonProfile $profile): JsonResponse
    {
        return ApiResponse::success(
            $profile->present($person, $request->user()?->can('persons.update') ?? false)
        );
    }

    public function update(
        UpdatePersonRequest $request,
        Person $person,
        PersonWriter $writer,
        PersonProfile $profile,
    ): JsonResponse {
        try {
            $person = $writer->update($person, $request->validated(), $request->user());
        } catch (PersonWarningException $exception) {
            return ApiResponse::warnings($exception->warnings);
        }

        return ApiResponse::success(
            $profile->present($person, true)
        );
    }
}
