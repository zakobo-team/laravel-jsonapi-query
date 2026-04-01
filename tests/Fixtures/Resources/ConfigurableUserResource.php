<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\Filters\WhereIdIn;
use Zakobo\JsonApiQuery\JsonApiQueryResource;

class ConfigurableUserResource extends JsonApiQueryResource
{
    public function __construct($resource)
    {
        parent::__construct($resource);

        $this->additionalFilters = [
            'id' => WhereIdIn::class,
        ];
    }

    public function toAttributes(Request $request): array
    {
        return [
            'name',
            'email',
        ];
    }
}
