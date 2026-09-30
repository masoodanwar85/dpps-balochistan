<?php

namespace App\Services\Catalog;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class CatalogWriter
{
    public function __construct(private ActivityLogger $activity) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Model $model, array $attributes, User $actor, string $subjectType, string $description): Model
    {
        $model->fill($attributes)->save();

        $this->activity->log(
            'created',
            $description,
            $subjectType,
            (int) $model->getKey(),
            $actor,
            null,
            $this->snapshot($model),
        );

        return $model->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes, User $actor, string $subjectType, string $description): Model
    {
        $before = $this->snapshot($model);
        $model->fill($attributes);

        if ($model->isDirty()) {
            $model->save();
            $this->activity->log(
                'updated',
                $description,
                $subjectType,
                (int) $model->getKey(),
                $actor,
                $before,
                $this->snapshot($model),
            );
        }

        return $model->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Model $model): array
    {
        return $model->only($model->getFillable());
    }
}
