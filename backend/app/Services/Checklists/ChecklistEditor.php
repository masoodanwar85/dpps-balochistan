<?php

namespace App\Services\Checklists;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChecklistEditor
{
    public function __construct(private ActivityLogger $activity) {}

    public function startVersion(ChecklistTemplate $template, User $actor): ChecklistTemplate
    {
        if ($template->status !== 'published') {
            throw ValidationException::withMessages([
                'template' => ['A new version can be created only from the published template.'],
            ]);
        }

        $draftExists = ChecklistTemplate::query()
            ->where('entity_type', $template->entity_type)
            ->where('application_type', $template->application_type)
            ->where('status', 'draft')
            ->exists();

        if ($draftExists) {
            throw ValidationException::withMessages([
                'template' => ['A draft version already exists.'],
            ]);
        }

        return DB::transaction(function () use ($template, $actor) {
            $template->load('items');
            $version = (int) ChecklistTemplate::query()
                ->where('entity_type', $template->entity_type)
                ->where('application_type', $template->application_type)
                ->max('version_no') + 1;

            $draft = ChecklistTemplate::query()->create([
                'entity_type' => $template->entity_type,
                'application_type' => $template->application_type,
                'version_no' => $version,
                'name' => $template->name,
                'status' => 'draft',
                'published_at' => null,
                'published_by' => null,
            ]);

            foreach ($template->items as $item) {
                $copy = $item->replicate(['template_id']);
                $copy->template_id = $draft->id;
                $copy->save();
            }

            $this->activity->log(
                'created',
                'Created checklist draft v'.$version.'.',
                'checklist_template',
                (int) $draft->id,
                $actor,
                null,
                ['version_no' => $version, 'copied_from' => $template->id],
            );

            return $draft->refresh();
        });
    }

    public function publish(ChecklistTemplate $template, User $actor): ChecklistTemplate
    {
        if ($template->status !== 'draft') {
            throw ValidationException::withMessages([
                'template' => ['Only a draft checklist can be published.'],
            ]);
        }

        if ($template->items()->count() < 1) {
            throw ValidationException::withMessages([
                'template' => ['Add at least one item before publishing.'],
            ]);
        }

        return DB::transaction(function () use ($template, $actor) {
            $current = ChecklistTemplate::query()
                ->where('entity_type', $template->entity_type)
                ->where('application_type', $template->application_type)
                ->where('status', 'published')
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                $current->status = 'archived';
                $current->save();
                $this->activity->log(
                    'updated',
                    'Archived checklist v'.$current->version_no.'.',
                    'checklist_template',
                    (int) $current->id,
                    $actor,
                    ['status' => 'published'],
                    ['status' => 'archived'],
                );
            }

            $template->status = 'published';
            $template->published_at = now();
            $template->published_by = $actor->id;
            $template->save();

            $this->activity->log(
                'updated',
                'Published checklist v'.$template->version_no.'.',
                'checklist_template',
                (int) $template->id,
                $actor,
                ['status' => 'draft'],
                ['status' => 'published'],
            );

            return $template->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createItem(ChecklistTemplate $template, array $attributes, User $actor): ChecklistItem
    {
        $this->assertDraft($template);
        $attributes['template_id'] = $template->id;
        $attributes['sort_order'] = (int) $template->items()->max('sort_order') + 1;

        $item = ChecklistItem::query()->create($attributes);

        $this->activity->log(
            'created',
            'Created checklist item '.$item->annex_code.'.',
            'checklist_item',
            (int) $item->id,
            $actor,
            null,
            $this->itemSnapshot($item),
        );

        return $item;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateItem(ChecklistTemplate $template, ChecklistItem $item, array $attributes, User $actor): ChecklistItem
    {
        $this->assertDraft($template);
        $before = $this->itemSnapshot($item);
        $item->fill($attributes);

        if ($item->isDirty()) {
            $item->save();
            $this->activity->log(
                'updated',
                'Updated checklist item '.$item->annex_code.'.',
                'checklist_item',
                (int) $item->id,
                $actor,
                $before,
                $this->itemSnapshot($item),
            );
        }

        return $item->refresh();
    }

    public function deleteItem(ChecklistTemplate $template, ChecklistItem $item, User $actor): void
    {
        $this->assertDraft($template);
        $snapshot = $this->itemSnapshot($item);
        $id = (int) $item->id;
        $annex = $item->annex_code;
        $item->delete();

        $this->activity->log(
            'deleted',
            'Removed checklist item '.$annex.'.',
            'checklist_item',
            $id,
            $actor,
            $snapshot,
            null,
        );
    }

    /**
     * @param  list<int>  $itemIds
     */
    public function reorder(ChecklistTemplate $template, array $itemIds, User $actor): void
    {
        $this->assertDraft($template);

        $owned = $template->items()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        $given = collect($itemIds)->map(fn ($id) => (int) $id)->sort()->values();

        if ($owned->all() !== $given->all()) {
            throw ValidationException::withMessages([
                'item_ids' => ['Include every item of this draft once.'],
            ]);
        }

        DB::transaction(function () use ($template, $itemIds, $actor) {
            foreach (array_values($itemIds) as $index => $id) {
                ChecklistItem::query()->where('id', $id)->update(['sort_order' => $index + 1]);
            }

            $this->activity->log(
                'updated',
                'Reordered checklist items.',
                'checklist_template',
                (int) $template->id,
                $actor,
                null,
                ['item_ids' => array_values($itemIds)],
            );
        });
    }

    public function copyFrom(ChecklistTemplate $template, ChecklistTemplate $source, User $actor): ChecklistTemplate
    {
        $this->assertDraft($template);

        if ($source->id === $template->id) {
            throw ValidationException::withMessages([
                'source_template_id' => ['Choose a different template.'],
            ]);
        }

        DB::transaction(function () use ($template, $source, $actor) {
            $source->load('items');
            $sort = (int) $template->items()->max('sort_order');

            foreach ($source->items as $item) {
                $sort++;
                $copy = $item->replicate(['template_id']);
                $copy->template_id = $template->id;
                $copy->sort_order = $sort;
                $copy->save();
            }

            $this->activity->log(
                'updated',
                'Copied items from '.$source->name.' v'.$source->version_no.'.',
                'checklist_template',
                (int) $template->id,
                $actor,
                null,
                ['source_template_id' => $source->id, 'copied' => $source->items->count()],
            );
        });

        return $template->refresh();
    }

    private function assertDraft(ChecklistTemplate $template): void
    {
        if ($template->status !== 'draft') {
            throw ValidationException::withMessages([
                'template' => ['A published checklist cannot be edited. Create a new version.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function itemSnapshot(ChecklistItem $item): array
    {
        return $item->only($item->getFillable());
    }
}
