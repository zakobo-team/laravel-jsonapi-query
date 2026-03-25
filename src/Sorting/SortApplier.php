<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Sorting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\Sorting\Contracts\Sort;

class SortApplier
{
    /**
     * @param  array<string>  $allowedSortFields
     * @param  array<string>  $allowedRelationshipSortFields
     * @param  array<Sort>  $additionalSorts
     */
    public function apply(
        Builder $query,
        array $allowedSortFields,
        Request $request,
        ?string $defaultSort = null,
        array $allowedRelationshipSortFields = [],
        array $additionalSorts = [],
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

            if (str_contains($field, '.')) {
                $this->applyRelationshipSort($query, $field, $direction, $allowedRelationshipSortFields, $usesDefaultSort);

                continue;
            }

            $additionalSort = $this->findAdditionalSort($field, $additionalSorts);

            if ($additionalSort !== null) {
                $additionalSort->apply($query, $direction);

                continue;
            }

            if (! $usesDefaultSort && ! in_array($field, $allowedSortFields, true)) {
                continue;
            }

            $qualifiedColumn = $query->getModel()->qualifyColumn($field);
            $query->orderBy($qualifiedColumn, $direction);
        }
    }

    /**
     * @param  array<string>  $allowedRelationshipSortFields
     */
    protected function applyRelationshipSort(
        Builder $query,
        string $field,
        string $direction,
        array $allowedRelationshipSortFields,
        bool $usesDefaultSort,
    ): void {
        if (! $usesDefaultSort && ! in_array($field, $allowedRelationshipSortFields, true)) {
            return;
        }

        $parts = explode('.', $field);

        if (count($parts) !== 2) {
            return;
        }

        [$relationshipName, $column] = $parts;

        $model = $query->getModel();

        if (! method_exists($model, $relationshipName)) {
            return;
        }

        $relation = Relation::noConstraints(fn () => $model->{$relationshipName}());

        if ($relation instanceof BelongsTo) {
            $subquery = clone $relation->getQuery();
            $subquery
                ->select($relation->getRelated()->qualifyColumn($column))
                ->whereColumn(
                    $relation->getQualifiedOwnerKeyName(),
                    $relation->getQualifiedForeignKeyName(),
                )
                ->limit(1);

            $query->orderBy($subquery, $direction);

            return;
        }

        if ($relation instanceof HasOne) {
            $subquery = clone $relation->getQuery();
            $subquery
                ->select($relation->getRelated()->qualifyColumn($column))
                ->whereColumn(
                    $relation->getQualifiedForeignKeyName(),
                    $relation->getQualifiedParentKeyName(),
                )
                ->limit(1);

            $query->orderBy($subquery, $direction);
        }
    }

    /**
     * @param  array<Sort>  $additionalSorts
     */
    protected function findAdditionalSort(string $key, array $additionalSorts): ?Sort
    {
        foreach ($additionalSorts as $sort) {
            if ($sort->key() === $key) {
                return $sort;
            }
        }

        return null;
    }
}
