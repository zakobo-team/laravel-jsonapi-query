<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Controllers\Invokable;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Zakobo\JsonApiQuery\Http\Concerns\HandlesJsonApiUpdate;
use Zakobo\JsonApiQuery\Http\Contracts\JsonApiController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;

class UpdatePostController extends Controller implements JsonApiController
{
    use HandlesJsonApiUpdate;

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
        return $this->update($request, $id);
    }
}
