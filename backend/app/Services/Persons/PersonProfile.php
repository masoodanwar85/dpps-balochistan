<?php

namespace App\Services\Persons;

use App\Models\ActivityLog;
use App\Models\Person;
use App\Models\Qualification;

class PersonProfile
{
    public function __construct(
        private PersonRules $rules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Person $person, bool $withChoices): array
    {
        $person->load(['qualifications.qualification', 'companyPeople']);

        $verified = $person->companyPeople->contains(
            fn ($assignment) => $this->rules->countsAsVerifiedStaff($person, $assignment)
        );

        return [
            'id' => $person->id,
            'cnic' => $person->cnic,
            'cnic_display' => $this->rules->displayCnic($person->cnic),
            'cnic_pending' => $person->cnic_pending,
            'full_name' => $person->full_name,
            'father_name' => $person->father_name,
            'gender' => $person->gender,
            'date_of_birth' => $person->date_of_birth?->toDateString(),
            'mobile' => $person->mobile,
            'alt_mobile' => $person->alt_mobile,
            'email' => $person->email,
            'address' => $person->address,
            'photo_path' => $person->photo_path,
            'roles' => $this->rules->roles($person),
            'blocks' => $this->rules->conflicts($person),
            'counts_as_verified_staff' => $verified,
            'blocks_license' => $this->rules->blocksLicense($person),
            'qualifications' => $person->qualifications->map(fn ($row) => [
                'id' => $row->id,
                'qualification_id' => $row->qualification_id,
                'qualification_name' => $row->qualification?->name,
                'institution' => $row->institution,
                'passing_year' => $row->passing_year,
            ])->values()->all(),
            'qualification_choices' => $withChoices ? $this->choices($person) : [],
            'activity' => $this->activity($person),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function choices(Person $person): array
    {
        $currentIds = $person->qualifications->pluck('qualification_id');

        return Qualification::query()
            ->where(function ($query) use ($currentIds) {
                $query->where('is_active', true);

                if ($currentIds->isNotEmpty()) {
                    $query->orWhereIn('id', $currentIds);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Qualification $qualification) => [
                'id' => $qualification->id,
                'name' => $qualification->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, action: string, description: string, created_at: string}>
     */
    private function activity(Person $person): array
    {
        return ActivityLog::query()
            ->where('subject_type', 'person')
            ->where('subject_id', $person->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'created_at' => $log->created_at?->toDateTimeString(),
            ])
            ->all();
    }
}
