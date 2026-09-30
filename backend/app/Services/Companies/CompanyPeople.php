<?php

namespace App\Services\Companies;

use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\Person;
use App\Models\PersonQualification;
use App\Models\Qualification;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Persons\PersonRules;
use App\Services\Persons\PersonWarningException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyPeople
{
    public function __construct(
        private PersonRules $rules,
        private ActivityLogger $logger,
        private CompanyProfile $profile,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(CompanyPerson $assignment): array
    {
        $assignment->loadMissing(['person.qualifications.qualification']);
        $person = $assignment->person;
        $qualification = $person?->qualifications->sortByDesc('id')->first();

        return [
            'id' => $assignment->id,
            'company_id' => $assignment->company_id,
            'person_id' => $assignment->person_id,
            'role' => $assignment->role,
            'role_label' => $this->rules->roleLabel($assignment->role),
            'designation' => $assignment->designation,
            'full_name' => $person?->full_name,
            'father_name' => $person?->father_name,
            'cnic' => $person?->cnic,
            'cnic_display' => $this->rules->displayCnic($person?->cnic),
            'cnic_pending' => (bool) $person?->cnic_pending,
            'mobile' => $person?->mobile,
            'email' => $person?->email,
            'qualification' => $qualification?->qualification?->name,
            'appointment_date' => $assignment->appointment_date?->toDateString(),
            'start_date' => $assignment->start_date?->toDateString(),
            'end_date' => $assignment->end_date?->toDateString(),
            'end_reason' => $assignment->end_reason,
            'verification_status' => $assignment->verification_status,
            'source' => $assignment->source,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function qualifications(): array
    {
        return Qualification::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Qualification $row) => [
                'id' => $row->id,
                'name' => $row->name,
            ])
            ->all();
    }

    /**
     * @return array{found: bool, person: ?array<string, mixed>, blocks: list<array{rule: string, message: string}>}
     */
    public function preview(string $cnic, string $role, User $actor): array
    {
        $person = Person::query()->where('cnic', $cnic)->first();

        if (! $person) {
            return [
                'found' => false,
                'person' => null,
                'blocks' => [],
            ];
        }

        return [
            'found' => true,
            'person' => [
                'id' => $person->id,
                'full_name' => $person->full_name,
                'father_name' => $person->father_name,
                'mobile' => $person->mobile,
                'email' => $person->email,
                'cnic_display' => $this->rules->displayCnic($person->cnic),
            ],
            'blocks' => $this->blocks($person, $role, $actor),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, User $actor): CompanyPerson
    {
        $person = Person::query()->where('cnic', $data['cnic'])->first();

        if ($person) {
            $blocks = $this->blocks($person, (string) $data['role'], $actor);

            if ($blocks !== []) {
                throw ValidationException::withMessages([
                    'cnic' => array_column($blocks, 'message'),
                ]);
            }
        } else {
            if (trim((string) ($data['full_name'] ?? '')) === '' || trim((string) ($data['mobile'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'full_name' => ['Name and mobile are required for a new person.'],
                ]);
            }

            $warnings = $this->mobileWarnings((string) $data['mobile']);

            if ($warnings !== [] && empty($data['confirm_warnings'])) {
                throw new PersonWarningException($warnings);
            }
        }

        $this->assertDates((string) $data['start_date'], null);
        $this->assertOpenRole($company, $person, (string) $data['role']);

        return DB::transaction(function () use ($company, $data, $actor, $person) {
            if (! $person instanceof Person) {
                $warnings = $this->mobileWarnings((string) $data['mobile']);
                $person = Person::query()->create([
                    'cnic' => $data['cnic'],
                    'cnic_pending' => false,
                    'full_name' => $data['full_name'],
                    'normalized_name' => $this->rules->normalizeName((string) $data['full_name']),
                    'father_name' => $data['father_name'] ?? null,
                    'mobile' => $data['mobile'],
                    'email' => $data['email'] ?? null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                if ($warnings !== []) {
                    $this->logger->log(
                        'warning_overridden',
                        'Warning confirmed: '.$data['warning_reason'],
                        'person',
                        $person->id,
                        $actor,
                        null,
                        [
                            'warnings' => $warnings,
                            'warning_reason' => $data['warning_reason'],
                        ],
                    );
                }

                $this->logger->log(
                    'created',
                    'Created person '.$person->full_name,
                    'person',
                    $person->id,
                    $actor,
                    null,
                    ['cnic' => $person->cnic, 'full_name' => $person->full_name],
                );
            }

            $this->addQualification($person, $data);

            $assignment = CompanyPerson::query()->create([
                'company_id' => $company->id,
                'person_id' => $person->id,
                'role' => $data['role'],
                'designation' => $data['designation'] ?? null,
                'appointment_date' => $data['appointment_date'] ?? null,
                'start_date' => $data['start_date'],
                'verification_status' => 'pending',
                'source' => $actor->user_type === 'company' ? 'portal' : 'office',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->logger->log(
                'created',
                'Added '.$this->rules->roleLabel($assignment->role).' '.$person->full_name,
                'company_person',
                $assignment->id,
                $actor,
                null,
                $this->snapshot($assignment),
            );

            return $assignment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CompanyPerson $assignment, array $data, User $actor): CompanyPerson
    {
        $end = $assignment->end_date?->toDateString();
        $this->assertDates((string) $data['start_date'], $end);

        $old = $this->snapshot($assignment);
        $assignment->fill([
            'designation' => $data['designation'] ?? null,
            'appointment_date' => $data['appointment_date'] ?? null,
            'start_date' => $data['start_date'],
            'updated_by' => $actor->id,
        ]);
        $assignment->save();

        $this->logger->log(
            'updated',
            'Updated '.$this->rules->roleLabel($assignment->role).' '.$assignment->person?->full_name,
            'company_person',
            $assignment->id,
            $actor,
            $old,
            $this->snapshot($assignment),
        );

        return $assignment->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{assignment: CompanyPerson, staff_note: ?string}
     */
    public function end(CompanyPerson $assignment, array $data, User $actor): array
    {
        if ($assignment->end_date !== null) {
            throw ValidationException::withMessages([
                'end_date' => ['Employment has already ended.'],
            ]);
        }

        $this->assertDates($assignment->start_date->toDateString(), (string) $data['end_date'], 'end_date');

        $old = $this->snapshot($assignment);
        $assignment->fill([
            'end_date' => $data['end_date'],
            'end_reason' => $data['end_reason'],
            'updated_by' => $actor->id,
        ]);
        $assignment->save();

        $this->logger->log(
            'updated',
            'Ended employment of '.$assignment->person?->full_name,
            'company_person',
            $assignment->id,
            $actor,
            $old,
            $this->snapshot($assignment),
        );

        $company = $assignment->company ?? Company::query()->findOrFail($assignment->company_id);

        return [
            'assignment' => $assignment->refresh(),
            'staff_note' => $assignment->role === 'technical_staff' ? $this->profile->staffNote($company) : null,
        ];
    }

    public function verify(CompanyPerson $assignment, User $actor): CompanyPerson
    {
        if ($assignment->role !== 'technical_staff') {
            throw ValidationException::withMessages([
                'role' => ['Only technical staff can be verified here.'],
            ]);
        }

        if ($assignment->end_date !== null) {
            throw ValidationException::withMessages([
                'end_date' => ['Employment has already ended.'],
            ]);
        }

        if ($assignment->verification_status === 'verified') {
            throw ValidationException::withMessages([
                'verification_status' => ['This person is already verified.'],
            ]);
        }

        $old = $this->snapshot($assignment);
        $assignment->fill([
            'verification_status' => 'verified',
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'updated_by' => $actor->id,
        ]);
        $assignment->save();

        $this->logger->log(
            'approved',
            'Verified technical staff '.$assignment->person?->full_name,
            'company_person',
            $assignment->id,
            $actor,
            $old,
            $this->snapshot($assignment),
        );

        return $assignment->refresh();
    }

    /**
     * @return list<array{rule: string, message: string}>
     */
    private function blocks(Person $person, string $role, User $actor): array
    {
        if ($role !== 'technical_staff') {
            return [];
        }

        $blocks = collect($this->rules->conflicts($person))
            ->where('for', 'technical_staff')
            ->map(fn (array $block) => [
                'rule' => $block['rule'],
                'message' => $block['message'],
            ])
            ->unique('message')
            ->values();

        if ($actor->user_type === 'company') {
            $blocks = $blocks->map(function (array $block) {
                if (in_array($block['rule'], ['R-01', 'R-02'], true)) {
                    $block['message'] = 'This person is registered with another company. Employment must be ended there first.';
                }

                return $block;
            })->unique('message')->values();
        }

        return $blocks->all();
    }

    /**
     * @return list<string>
     */
    private function mobileWarnings(string $mobile): array
    {
        $other = Person::query()
            ->where('mobile', $mobile)
            ->orWhere('alt_mobile', $mobile)
            ->first();

        if (! $other) {
            return [];
        }

        return ["This mobile number already belongs to {$other->full_name}."];
    }

    private function assertOpenRole(Company $company, ?Person $person, string $role): void
    {
        if (! $person instanceof Person) {
            return;
        }

        $open = CompanyPerson::query()
            ->where('company_id', $company->id)
            ->where('person_id', $person->id)
            ->where('role', $role)
            ->whereNull('end_date')
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'cnic' => ['This person already holds this role at this company.'],
            ]);
        }
    }

    private function assertDates(string $startDate, ?string $endDate, string $field = 'start_date'): void
    {
        $errors = $this->rules->employmentDateErrors($startDate, $endDate);

        if ($errors !== []) {
            throw ValidationException::withMessages([
                $field => $errors,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function addQualification(Person $person, array $data): void
    {
        if (empty($data['qualification_id'])) {
            return;
        }

        PersonQualification::query()->create([
            'person_id' => $person->id,
            'qualification_id' => $data['qualification_id'],
            'institution' => $data['institution'] ?? null,
            'passing_year' => $data['passing_year'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(CompanyPerson $assignment): array
    {
        return [
            'company_id' => $assignment->company_id,
            'person_id' => $assignment->person_id,
            'role' => $assignment->role,
            'designation' => $assignment->designation,
            'start_date' => $assignment->start_date?->toDateString(),
            'end_date' => $assignment->end_date?->toDateString(),
            'end_reason' => $assignment->end_reason,
            'verification_status' => $assignment->verification_status,
            'source' => $assignment->source,
        ];
    }
}
