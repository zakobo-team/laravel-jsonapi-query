<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Models;

class RouteKeyPost extends Post
{
    protected $table = 'posts';

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
