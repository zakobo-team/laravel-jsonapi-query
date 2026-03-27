<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostResource;

#[UseResource(PostResource::class)]
class Post extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->where('name', 'Alice');
    }

    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function meta(): HasOne
    {
        return $this->hasOne(PostMeta::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withPivot('approved');
    }

    public function polymorphicTags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'loggable');
    }

    public function scopePopular($query): void
    {
        $query->where('votes', '>=', 100);
    }

    public function scopeTitle($query, string $title): void
    {
        $query->where('title', 'like', "%{$title}%");
    }

    public function scopeUser($query, int|string $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function scopeMinVotes($query, int $minVotes): void
    {
        $query->where('votes', '>=', $minVotes);
    }

    public function scopeOrderByLatestComment($query, string $direction): void
    {
        $query->orderBy(
            Comment::select('created_at')
                ->whereColumn('comments.post_id', 'posts.id')
                ->latest()
                ->limit(1),
            $direction,
        );
    }
}
