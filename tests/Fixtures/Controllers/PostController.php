<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Controllers;

use Illuminate\Routing\Controller;
use Zakobo\JsonApiQuery\Http\Concerns\HandlesJsonApi;
use Zakobo\JsonApiQuery\Http\Contracts\JsonApiController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;

class PostController extends Controller implements JsonApiController
{
    use HandlesJsonApi;

    public function getResource(): string
    {
        return PostResource::class;
    }

    public function getModel(): string
    {
        return Post::class;
    }
}
