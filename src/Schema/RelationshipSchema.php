<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Schema;

class RelationshipSchema
{
    /**
     * @param  class-string|null  $resourceClass
     * @param  class-string  $relatedModelClass
     */
    public function __construct(
        public readonly string $name,
        public readonly string $relatedModelClass,
        public readonly ?string $resourceClass,
    ) {
    }
}
