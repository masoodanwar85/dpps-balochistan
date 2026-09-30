<?php

namespace App\Services\Dealers;

use App\Models\Dealer;
use App\Models\DealerOwner;
use App\Models\Person;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Persons\PersonRules;
use App\Services\Persons\PersonWarningException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealerOwners
{
    public function __construct(
        private PersonRules $rules,
        private ActivityLogger $logger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(DealerOwner $owner): array
    {
        $owner->loadMissing('person');
        $person = $owner->person;

        return [
            'id' => $owner->id,
            'dealer_id' => $owner->dealer_id,
            'person_id' => $owner->person_id,
            'full_name' => $person?->full_name,
            'cnic' => $person?->cnic,
            'cnic_display' => $this->rules->displayCnic($person?->cnic),
            'cnic_pending' => (bool) $person?->cnic_pending,
            'mobile' => $person?->mobile,
            'start_date' => $owner->start_date?->toDateString(),
            'end_date' => $owner->end_date?->toDateString(),
            'other_shops' => $person ? $this->otherShops($person, $owner->dealer_id) : [],
        ];
    }

    /**
     * @return array{found: bool, person: ?array<string, mixed>, blocks: list<array{rule: string, message: string}>, other_shops: list<string>}
     */
    public function preview(Dealer $dealer, string $cnic): array
    {
        $person = Person::query()->where('cnic', $cnic)->first();

        if (! $person) {
            return [
                'found' => false,
                'person' => null,
                'blocks' => [],
                'other_shops' => [],
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
            'blocks' => $this->blocks($dealer, $person),
            'other_shops' => $this->otherShops($person, $dealer->id),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Dealer $dealer, array $data, User $actor): DealerOwner
    {
        $person = Person::query()->where('cnic', $data['cnic'])->first();

        if ($person) {
            $blocks = $this->blocks($dealer, $person);

            if ($blocks !== []) {
                throw ValidationException::withMessages([
                    'cnic' => array_column($blocks, 'message'),
                ]);
            }
        } elseif (trim((string) ($data['full_name'] ?? '')) === '' || trim((string) ($data['mobile'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'full_name' => ['Name and mobile are required for a new person.'],
            ]);
        }

        $this->assertDates((string) $data['start_date'], null);
        $warnings = $person instanceof Person ? [] : $this->mobileWarnings((string) $data['mobile']);

        if ($warnings !== [] && empty($data['confirm_warnings'])) {
            throw new PersonWarningException($warnings);
        }

        return DB::transaction(function () use ($dealer, $data, $actor, $person, $warnings) {
            if (! $person instanceof Person) {
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

            $owner = DealerOwner::query()->create([
                'dealer_id' => $dealer->id,
                'person_id' => $person->id,
                'start_date' => $data['start_date'],
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->logger->log(
                'created',
                'Added owner '.$person->full_name,
                'dealer_owner',
                $owner->id,
                $actor,
                null,
                $this->present($owner),
            );

            return $owner;
        });
    }

    public function end(DealerOwner $owner, string $endDate, User $actor): DealerOwner
    {
        if ($owner->end_date !== null) {
            throw ValidationException::withMessages([
                'end_date' => ['This ownership has already ended.'],
            ]);
        }

        $this->assertDates($owner->start_date?->toDateString() ?? '', $endDate, 'end_date');
        $before = $this->present($owner);
        $owner->end_date = $endDate;
        $owner->updated_by = $actor->id;
        $owner->save();

        $this->logger->log(
            'updated',
            'Ended ownership of '.($owner->person?->full_name ?? 'owner'),
            'dealer_owner',
            $owner->id,
            $actor,
            $before,
            $this->present($owner->refresh()),
        );

        return $owner;
    }

    /**
     * @return list<array{rule: string, message: string}>
     */
    private function blocks(Dealer $dealer, Person $person): array
    {
        $blocks = [];

        foreach ($this->rules->conflicts($person) as $conflict) {
            if ($conflict['for'] === 'dealer_owner') {
                $blocks[] = [
                    'rule' => $conflict['rule'],
                    'message' => $conflict['message'],
                ];
            }
        }

        $already = DealerOwner::query()
            ->where('dealer_id', $dealer->id)
            ->where('person_id', $person->id)
            ->whereNull('end_date')
            ->exists();

        if ($already) {
            $blocks[] = [
                'rule' => 'owner',
                'message' => 'This person is already an owner of this shop.',
            ];
        }

        return $blocks;
    }

    /**
     * @return list<string>
     */
    private function otherShops(Person $person, int $dealerId): array
    {
        return DealerOwner::query()
            ->with('dealer')
            ->where('person_id', $person->id)
            ->where('dealer_id', '!=', $dealerId)
            ->whereNull('end_date')
            ->get()
            ->map(fn (DealerOwner $owner) => $owner->dealer?->shop_name)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function mobileWarnings(string $mobile): array
    {
        $number = trim($mobile);

        if ($number === '') {
            return [];
        }

        $other = Person::query()
            ->where(function ($query) use ($number) {
                $query->where('mobile', $number)->orWhere('alt_mobile', $number);
            })
            ->first();

        if (! $other) {
            return [];
        }

        return ['This mobile number already belongs to '.$other->full_name.'.'];
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
}
