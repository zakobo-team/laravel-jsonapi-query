<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

class UnpaginatedPostResource extends PostResource
{
    public bool $allowUnpaginated = true;
}
