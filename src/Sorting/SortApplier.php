<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Sorting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SortApplier
{
    /**
     * @param  array<string>  $allowedSortFields
     */
    public static function apply(
        Builder $query,
        array $allowedSortFields,
        Request $request,
        ?string $defaultSort = null,
    ): void {
        $sortParam = $request->query('sort');
        $usesDefaultSort = ! is_string($sortParam) || $sortParam === '';

        if ($usesDefaultSort) {
            $sortParam = $defaultSort;
        }

        if ($sortParam === null || $sortParam === '') {
            return;
        }

        $fields = explode(',', $sortParam);

        foreach ($fields as $field) {
            $field = trim($field);

            if ($field === '' || $field === '-') {
                continue;
            }

            $direction = 'asc';

            if (str_starts_with($field, '-')) {
                $direction = 'desc';
                $field = substr($field, 1);
            }

            if ($field === '') {
                continue;
            }

            if (! $usesDefaultSort && ! in_array($field, $allowedSortFields, true)) {
                continue;
            }

            $qualifiedColumn = $query->getModel()->qualifyColumn($field);
            $query->orderBy($qualifiedColumn, $direction);
        }
    }
}
