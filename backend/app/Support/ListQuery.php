<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ListQuery
{
    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $columns
     */
    public static function search(Request $request, Builder $query, array $columns): void
    {
        if (! $request->filled('search') || $columns === []) {
            return;
        }

        $search = '%'.$request->string('search')->value().'%';

        $query->where(function (Builder $inner) use ($columns, $search) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $inner->where($column, 'like', $search);
                } else {
                    $inner->orWhere($column, 'like', $search);
                }
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public static function active(Request $request, Builder $query): void
    {
        if ($request->filled('filter.is_active')) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $sortable
     * @return LengthAwarePaginator<int, Model>
     */
    public static function paginate(Request $request, Builder $query, array $sortable, string $default): LengthAwarePaginator
    {
        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $sort = (string) $request->query('sort', $default);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $sortable, true)) {
            $column = $default;
            $direction = 'asc';
        }

        return $query->orderBy($column, $direction)->paginate($perPage);
    }

    /**
     * @param  LengthAwarePaginator<int, Model>  $page
     * @return array{current_page: int, per_page: int, total: int, last_page: int}
     */
    public static function meta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ];
    }
}
