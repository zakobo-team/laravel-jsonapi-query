<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Zakobo\JsonApiQuery\JsonApiQueryResource;

class CommentResource extends JsonApiQueryResource
{
    public $attributes = ['author', 'body'];

    public $relationships = ['post'];
}
