<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Schema;

class AttributeSchema
{
    public function __construct(
        public readonly string $name,
        public readonly bool $autoQueryable,
    ) {
    }
}
