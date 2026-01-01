<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HandlesSearch
{
    /**
     * Apply fuzzy search filters and a relevance score to the query.
     *
     * @param  Builder  $query
     * @param  string|null  $search
     * @param  array<int,string>  $columns  Fully-qualified column names or SQL expressions to search against.
     * @param  string  $scoreColumn
     */
    protected function applyFuzzySearch(Builder $query, ?string $search, array $columns, string $scoreColumn = 'search_score'): void
    {
        $terms = $this->normalizedSearchTerms($search);

        if ($terms === [] || $columns === []) {
            return;
        }

        // Require every term to match at least one of the searchable columns.
        $query->where(function ($outer) use ($terms, $columns) {
            foreach ($terms as $term) {
                $outer->where(function ($inner) use ($columns, $term) {
                    $like = '%'.$term.'%';
                    foreach ($columns as $column) {
                        $inner->orWhereRaw('LOWER('.$column.') LIKE ?', [$like]);
                    }
                });
            }
        });

        [$expression, $bindings] = $this->buildRelevanceExpression($terms, $columns);

        $query->selectRaw('('.$expression.') as '.$scoreColumn, $bindings)
            ->orderByDesc($scoreColumn);
    }

    /**
     * @return array<int,string>
     */
    protected function normalizedSearchTerms(?string $search): array
    {
        $raw = trim((string) $search);

        if ($raw === '') {
            return [];
        }

        return collect(preg_split('/\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($term) => Str::lower($term))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int,string>  $terms
     * @param  array<int,string>  $columns
     * @return array{string,array<int,string>}
     */
    protected function buildRelevanceExpression(array $terms, array $columns): array
    {
        $parts = [];
        $bindings = [];

        foreach ($terms as $term) {
            foreach ($columns as $column) {
                $parts[] = "CASE
                    WHEN LOWER($column) = ? THEN 6
                    WHEN LOWER($column) LIKE ? THEN 3
                    WHEN LOWER($column) LIKE ? THEN 1
                    ELSE 0
                END";

                $bindings[] = $term;
                $bindings[] = $term.'%';
                $bindings[] = '%'.$term.'%';
            }
        }

        return [implode(' + ', $parts), $bindings];
    }
}
