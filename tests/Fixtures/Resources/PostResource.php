<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Resources;

use Zakobo\JsonApiQuery\Filters\Scope;
use Zakobo\JsonApiQuery\Filters\WithTrashed;
use Zakobo\JsonApiQuery\JsonApiQueryResource;

class PostResource extends JsonApiQueryResource
{
    public $attributes = ['title', 'slug', 'votes', 'published'];

    public $relationships = ['comments', 'tags', 'user', 'meta'];

    public array $excludedFromFilter = ['computed_score'];

    public array $excludedFromSorting = ['computed_score'];

    public array $additionalFilters = [
        'with-trashed' => WithTrashed::class,
        'popular' => Scope::class,
    ];

    public array $scopedBy = [];

    public ?string $defaultSort = '-created_at';

    public ?int $defaultPageSize = 15;

    public ?int $maxPageSize = 50;
}
