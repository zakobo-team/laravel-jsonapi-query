<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    protected $guarded = [];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->withPivot('approved');
    }

    public function taggablePosts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    public function taggableUsers(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'taggable');
    }
}
