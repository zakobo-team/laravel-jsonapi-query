<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\UseResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PlainPostResource;

#[UseResource(PlainPostResource::class)]
class PlainResourcePost extends Post
{
    protected $table = 'posts';
}
