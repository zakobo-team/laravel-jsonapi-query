<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

abstract class JsonApiQueryResource extends JsonApiResource
{
    /**
     * Attributes excluded from auto-generated filters.
     *
     * @var array<int, string>
     */
    public array $excludedFromFilter = [];

    /**
     * Attributes excluded from auto-generated sorting.
     *
     * @var array<int, string>
     */
    public array $excludedFromSorting = [];

    /**
     * Additional filters beyond auto-generated (scopes, soft deletes, custom).
     *
     * @var array<string, class-string>
     */
    public array $additionalFilters = [];

    /**
     * Default sort when no ?sort= parameter is provided.
     */
    public ?string $defaultSort = null;

    /**
     * Default page size (per-resource override of global config).
     */
    public ?int $defaultPageSize = null;

    /**
     * Maximum page size (per-resource override of global config).
     */
    public ?int $maxPageSize = null;

    /**
     * Additional sorts beyond auto-generated (scopes, custom).
     *
     * @var array<string, class-string>
     */
    public array $additionalSorts = [];

    /**
     * Create an instance solely for reading query configuration.
     */
    public static function queryConfig(): static
    {
        return new static(null);
    }

    /**
     * Get the attribute names that are filterable.
     * Returns $attributes minus $excludedFromFilter.
     *
     * @return array<int, string>
     */
    public function filterableAttributes(): array
    {
        return array_values(array_diff(
            $this->normalizedAttributeNames(),
            $this->excludedFromFilter,
        ));
    }

    /**
     * Get the attribute names that are sortable.
     * Returns $attributes minus $excludedFromSorting.
     *
     * @return array<int, string>
     */
    public function sortableAttributes(): array
    {
        return array_values(array_diff(
            $this->normalizedAttributeNames(),
            $this->excludedFromSorting,
        ));
    }

    /**
     * Get the relationship names that can be used for WhereHas filtering.
     *
     * @return array<int, string>
     */
    public function filterableRelationships(): array
    {
        return $this->normalizedRelationshipNames();
    }

    /**
     * @return array<int, string>
     */
    protected function normalizedAttributeNames(): array
    {
        $attributes = $this->toAttributes(Request::create('/'));

        if (! is_array($attributes)) {
            return [];
        }

        $names = [];

        foreach ($attributes as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $names[] = $value;
            }

            if (is_string($key)) {
                $names[] = $key;
            }
        }

        return $names;
    }

    /**
     * @return array<int, string>
     */
    protected function normalizedRelationshipNames(): array
    {
        $relationships = $this->toRelationships(Request::create('/'));

        if (! is_array($relationships)) {
            return [];
        }

        $names = [];

        foreach ($relationships as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $names[] = $value;
            }

            if (is_string($key)) {
                $names[] = $key;
            }
        }

        return $names;
    }
}
