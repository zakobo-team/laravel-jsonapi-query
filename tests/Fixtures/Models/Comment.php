<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\CommentResource;

/**
 * @property int $id
 * @property int $post_id
 * @property string $author
 * @property string $body
 * @property Carbon|null $created_at
 */
#[UseResource(CommentResource::class)]
class Comment extends Model
{
    protected $guarded = [];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
