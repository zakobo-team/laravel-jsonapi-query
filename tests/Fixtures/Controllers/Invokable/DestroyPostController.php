<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Controllers\Invokable;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Zakobo\JsonApiQuery\Http\Concerns\HandlesJsonApiDestroy;
use Zakobo\JsonApiQuery\Http\Contracts\JsonApiController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;

class DestroyPostController extends Controller implements JsonApiController
{
    use HandlesJsonApiDestroy;

    public function getResource(): string
    {
        return PostResource::class;
    }

    public function getModel(): string
    {
        return Post::class;
    }

    public function __invoke(Request $request, mixed $id): mixed
    {
        return $this->destroy($request, $id);
    }
}
