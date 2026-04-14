<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Schema;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class RelationshipSchema
{
    /**
     * @param  class-string<JsonApiResource>|null  $resourceClass
     * @param  class-string  $relatedModelClass
     */
    public function __construct(
        public readonly string $name,
        public readonly string $relationMethodName,
        public readonly string $relatedModelClass,
        public readonly ?string $resourceClass,
    ) {}
}
