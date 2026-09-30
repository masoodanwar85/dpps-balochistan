<?php

namespace App\Services\Persons;

use App\Models\Person;
use App\Models\PersonQualification;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonWriter
{
    public function __construct(
        private PersonRules $rules,
        private ActivityLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Person $person, array $data, User $actor): Person
    {
        $warnings = $this->rules->mobileWarnings(
            $person,
            (string) $data['mobile'],
            $data['alt_mobile'] ?? null,
        );

        if ($warnings !== [] && empty($data['confirm_warnings'])) {
            throw new PersonWarningException($warnings);
        }

        return DB::transaction(function () use ($person, $data, $actor, $warnings) {
            if ($warnings !== []) {
                $reason = (string) $data['warning_reason'];
                $this->logger->log(
                    'warning_overridden',
                    'Warning confirmed: '.$reason,
                    'person',
                    $person->id,
                    $actor,
                    null,
                    [
                        'warnings' => $warnings,
                        'warning_reason' => $reason,
                    ],
                );
            }

            $old = $this->snapshot($person);
            $person->fill([
                'full_name' => $data['full_name'],
                'normalized_name' => $this->rules->normalizeName((string) $data['full_name']),
                'father_name' => $data['father_name'] ?? null,
                'gender' => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'mobile' => $data['mobile'],
                'alt_mobile' => $data['alt_mobile'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'cnic' => $data['cnic'] ?? null,
                'cnic_pending' => (bool) $data['cnic_pending'],
            ]);

            if (array_key_exists('photo_path', $data)) {
                $person->photo_path = $data['photo_path'];
            }

            $person->updated_by = $actor->id;
            $person->save();

            if (array_key_exists('qualifications', $data)) {
                $this->syncQualifications($person, $data['qualifications']);
            }

            $person->refresh();
            $this->logger->log(
                'updated',
                'Updated person '.$person->full_name,
                'person',
                $person->id,
                $actor,
                $old,
                $this->snapshot($person),
            );

            return $person;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncQualifications(Person $person, array $rows): void
    {
        $existing = $person->qualifications()->get()->keyBy('id');
        $kept = [];

        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $attributes = [
                'qualification_id' => $row['qualification_id'],
                'institution' => $row['institution'] ?? null,
                'passing_year' => $row['passing_year'] ?? null,
            ];

            if ($id > 0) {
                $record = $existing->get($id);

                if (! $record instanceof PersonQualification) {
                    throw ValidationException::withMessages([
                        'qualifications' => ['A qualification on this profile does not belong to this person.'],
                    ]);
                }

                $record->update($attributes);
                $kept[] = $record->id;

                continue;
            }

            $created = $person->qualifications()->create($attributes);
            $kept[] = $created->id;
        }

        $remove = $person->qualifications();

        if ($kept === []) {
            $remove->delete();

            return;
        }

        $remove->whereNotIn('id', $kept)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Person $person): array
    {
        $person->load('qualifications');

        return [
            'cnic' => $person->cnic,
            'cnic_pending' => $person->cnic_pending,
            'full_name' => $person->full_name,
            'normalized_name' => $person->normalized_name,
            'father_name' => $person->father_name,
            'gender' => $person->gender,
            'date_of_birth' => $person->date_of_birth?->toDateString(),
            'mobile' => $person->mobile,
            'alt_mobile' => $person->alt_mobile,
            'email' => $person->email,
            'address' => $person->address,
            'photo_path' => $person->photo_path,
            'qualifications' => $person->qualifications->map(fn (PersonQualification $row) => [
                'id' => $row->id,
                'qualification_id' => $row->qualification_id,
                'institution' => $row->institution,
                'passing_year' => $row->passing_year,
            ])->values()->all(),
        ];
    }
}
