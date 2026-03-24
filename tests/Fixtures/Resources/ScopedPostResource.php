<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Zakobo\JsonApiQuery\JsonApiQueryResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Scopes\NeverAppliesScope;
use Zakobo\JsonApiQuery\Tests\Fixtures\Scopes\TestAreaScope;

class ScopedPostResource extends JsonApiQueryResource
{
    public $attributes = ['title', 'slug', 'votes', 'published'];

    public $relationships = ['comments', 'tags', 'user', 'meta'];

    public array $scopedBy = [
        TestAreaScope::class,
        NeverAppliesScope::class,
    ];

    public ?string $defaultSort = '-created_at';
}
