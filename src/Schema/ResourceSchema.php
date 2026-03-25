<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Schema;

class ResourceSchema
{
    /**
     * @param  class-string  $resourceClass
     * @param  class-string  $modelClass
     * @param  array<string, AttributeSchema>  $attributes
     * @param  array<string, RelationshipSchema>  $relationships
     * @param  array<string, class-string>  $additionalFilters
     * @param  array<string, class-string>  $additionalSorts
     */
    public function __construct(
        public readonly string $resourceClass,
        public readonly string $modelClass,
        public readonly array $attributes,
        public readonly array $relationships,
        public readonly array $additionalFilters,
        public readonly array $additionalSorts,
        public readonly array $excludedFromFilter,
        public readonly array $excludedFromSorting,
        public readonly ?string $defaultSort,
        public readonly ?int $defaultPageSize,
        public readonly ?int $maxPageSize,
    ) {
    }

    public function attribute(string $name): ?AttributeSchema
    {
        return $this->attributes[$name] ?? null;
    }

    public function relationship(string $name): ?RelationshipSchema
    {
        return $this->relationships[$name] ?? null;
    }

    public function hasAutoFilterableAttribute(string $name): bool
    {
        $attribute = $this->attribute($name);

        return $attribute !== null
            && $attribute->autoQueryable
            && ! in_array($name, $this->excludedFromFilter, true);
    }

    public function hasAutoSortableAttribute(string $name): bool
    {
        $attribute = $this->attribute($name);

        return $attribute !== null
            && $attribute->autoQueryable
            && ! in_array($name, $this->excludedFromSorting, true);
    }
}
