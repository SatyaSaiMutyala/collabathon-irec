<?php

namespace App\Http\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared paging/sorting for every list endpoint.
 *
 * Two guards matter at 8k concurrent users:
 *  - `per_page` is capped, so no client can ask for the whole table.
 *  - `sort` is whitelisted per call site. Interpolating a user-supplied column into
 *    ORDER BY would be an injection hole and would also let callers sort on unindexed
 *    columns, which is a filesort across the table.
 */
trait HandlesListQueries
{
    /**
     * Methods rather than properties: a class redeclaring a trait property with a
     * different default is a fatal "incompatible definition" error in PHP 8.
     * Override these in the controller instead.
     */
    protected function defaultPerPage(): int
    {
        return 20;
    }

    protected function maxPerPage(): int
    {
        return 100;
    }

    protected function perPage(Request $request): int
    {
        $default = $this->defaultPerPage();
        $requested = (int) $request->query('per_page', $default);

        return max(1, min($requested ?: $default, $this->maxPerPage()));
    }

    /**
     * Apply ORDER BY from `sort`/`direction`, restricted to $sortable.
     *
     * @param  array<string,string>  $sortable  public name => real column
     */
    protected function applySort(Builder $query, Request $request, array $sortable, string $default = 'created_at'): Builder
    {
        $requested = (string) $request->query('sort', '');
        $column = $sortable[$requested] ?? $sortable[$default] ?? $default;

        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($column, $direction)
            // Tie-break so pages are stable when the sort column has duplicates —
            // without this a row can appear on two pages or be skipped entirely.
            ->orderBy($query->getModel()->getQualifiedKeyName(), 'desc');
    }

    /**
     * Paginate and preserve the current query string on the generated links.
     */
    protected function paginate(Builder $query, Request $request): LengthAwarePaginator
    {
        return $query->paginate($this->perPage($request))->withQueryString();
    }

    /**
     * Filters pulled straight off the query string, with blanks dropped so
     * `?status=` behaves as "no filter" rather than "status is empty string".
     *
     * @param  array<int,string>  $keys
     * @return array<string,mixed>
     */
    protected function filters(Request $request, array $keys): array
    {
        return collect($request->only($keys))
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();
    }
}
