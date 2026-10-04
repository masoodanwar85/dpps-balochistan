<?php

namespace App\Services\Settings;

use App\Models\FeeStructure;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeStructures
{
    /**
     * @var list<array{entity_type: string, fee_type: string, label: string}>
     */
    public const EDITABLE = [
        ['entity_type' => 'company', 'fee_type' => 'registration', 'label' => 'Company registration'],
        ['entity_type' => 'company', 'fee_type' => 'renewal', 'label' => 'Company renewal'],
        ['entity_type' => 'dealer', 'fee_type' => 'registration', 'label' => 'Dealer registration'],
        ['entity_type' => 'dealer', 'fee_type' => 'renewal', 'label' => 'Dealer renewal'],
    ];

    public function __construct(private ActivityLogger $logger) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return collect(self::EDITABLE)
            ->map(function (array $slot) {
                $current = $this->current($slot['entity_type'], $slot['fee_type']);

                return [
                    'entity_type' => $slot['entity_type'],
                    'fee_type' => $slot['fee_type'],
                    'label' => $slot['label'],
                    'current' => $current ? $this->present($current) : null,
                ];
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function update(array $rows, User $actor): array
    {
        DB::transaction(function () use ($rows, $actor): void {
            foreach ($rows as $index => $row) {
                $this->apply($row, $actor, $index);
            }
        });

        return $this->list();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function apply(array $row, User $actor, int $index): void
    {
        $entityType = (string) $row['entity_type'];
        $feeType = (string) $row['fee_type'];
        $amount = number_format((float) $row['amount'], 2, '.', '');
        $effectiveFrom = Carbon::parse((string) $row['effective_from'])->startOfDay();
        $notes = trim((string) ($row['notes'] ?? ''));
        $current = $this->current($entityType, $feeType);

        if ($current instanceof FeeStructure
            && number_format((float) $current->amount, 2, '.', '') === $amount
        ) {
            return;
        }

        if ($current instanceof FeeStructure) {
            if ($effectiveFrom->lte($current->effective_from?->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    "fees.{$index}.effective_from" => ['Choose a date after the current rate starts ('.$current->effective_from?->toDateString().').'],
                ]);
            }

            $closeTo = $effectiveFrom->copy()->subDay();
            $before = $this->present($current);
            $current->effective_to = $closeTo->toDateString();
            $current->save();

            $this->logger->log(
                'updated',
                'Closed fee period for '.$entityType.' '.$feeType.'.',
                'fee_structure',
                (int) $current->id,
                $actor,
                $before,
                $this->present($current->refresh()),
            );
        }

        $created = FeeStructure::query()->create([
            'entity_type' => $entityType,
            'fee_type' => $feeType,
            'amount' => $amount,
            'effective_from' => $effectiveFrom->toDateString(),
            'effective_to' => null,
            'notes' => $notes !== '' ? $notes : 'Set from Settings › Fees.',
        ]);

        $this->logger->log(
            'created',
            'Set '.$entityType.' '.$feeType.' fee to '.$amount.'.',
            'fee_structure',
            (int) $created->id,
            $actor,
            null,
            $this->present($created),
        );
    }

    private function current(string $entityType, string $feeType): ?FeeStructure
    {
        return FeeStructure::query()
            ->where('entity_type', $entityType)
            ->where('fee_type', $feeType)
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(FeeStructure $row): array
    {
        return [
            'id' => $row->id,
            'entity_type' => $row->entity_type,
            'fee_type' => $row->fee_type,
            'amount' => number_format((float) $row->amount, 2, '.', ''),
            'effective_from' => $row->effective_from?->toDateString(),
            'effective_to' => $row->effective_to?->toDateString(),
            'notes' => $row->notes,
        ];
    }
}
