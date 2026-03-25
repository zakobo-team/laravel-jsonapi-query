<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\JsonApiQueryResource;

class TagResource extends JsonApiQueryResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'name',
        ];
    }
}
