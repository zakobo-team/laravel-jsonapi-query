<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

class UnpaginatedConfigurablePlainPostResource extends ConfigurablePlainPostResource
{
    public function __construct($resource)
    {
        parent::__construct($resource);

        $this->allowUnpaginated = true;
    }
}
