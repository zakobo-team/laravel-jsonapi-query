<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Controllers;

use Illuminate\Routing\Controller;
use Zakobo\JsonApiQuery\Http\Concerns\HandlesJsonApiRelationship;
use Zakobo\JsonApiQuery\Http\Contracts\JsonApiRelationshipController;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\CommentResource;

class PostCommentsController extends Controller implements JsonApiRelationshipController
{
    use HandlesJsonApiRelationship;

    public function getResource(): string
    {
        return CommentResource::class;
    }

    public function getModel(): string
    {
        return Comment::class;
    }

    public function getParentModel(): string
    {
        return Post::class;
    }

    public function getParentRouteParameter(): string
    {
        return 'post';
    }

    public function getParentRelationship(): string
    {
        return 'comments';
    }
}
