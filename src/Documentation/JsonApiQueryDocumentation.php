<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Documentation;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final readonly class JsonApiQueryDocumentation
{
    /**
     * @param  class-string  $modelClass
     * @param  class-string<JsonApiResource>  $resourceClass
     * @param  list<string>  $filterFields
     * @param  list<string>  $sortFields
     * @param  list<string>  $includePaths
     * @param  list<string>  $includeFilterFields
     * @param  array<string, list<string>>  $fieldsets
     */
    public function __construct(
        public string $modelClass,
        public string $resourceClass,
        public string $resourceType,
        public array $filterFields,
        public array $sortFields,
        public array $includePaths,
        public array $includeFilterFields,
        public array $fieldsets,
        public int $defaultPageSize,
        public int $maxPageSize,
        public bool $allowUnpaginated,
    ) {}
}
