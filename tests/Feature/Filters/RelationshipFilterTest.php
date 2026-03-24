<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Filters;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Filters\Has;
use Zakobo\JsonApiQuery\Filters\Where;
use Zakobo\JsonApiQuery\Filters\WhereDoesntHave;
use Zakobo\JsonApiQuery\Filters\WhereHas;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Activity;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Country;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Image;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\PostMeta;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Tag;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\User;
use Zakobo\JsonApiQuery\Tests\TestCase;

class RelationshipFilterTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Has filter — HasMany
    // =========================================================================

    #[Test]
    public function has_filters_has_many_with_truthy_value(): void
    {
        $postWithComments = Post::create(['title' => 'With', 'slug' => 'with']);
        Comment::create(['post_id' => $postWithComments->id, 'author' => 'John', 'body' => 'Nice']);

        Post::create(['title' => 'Without', 'slug' => 'without']);

        $filter = Has::make('comments');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 'true'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('With', $results->first()->title);
    }

    #[Test]
    public function has_filters_has_many_with_falsy_value(): void
    {
        $postWithComments = Post::create(['title' => 'With', 'slug' => 'with']);
        Comment::create(['post_id' => $postWithComments->id, 'author' => 'John', 'body' => 'Nice']);

        Post::create(['title' => 'Without', 'slug' => 'without']);

        $filter = Has::make('comments');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, 'false'))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Without', $results->first()->title);
    }

    // =========================================================================
    // Has filter — HasOne
    // =========================================================================

    #[Test]
    public function has_filters_has_one(): void
    {
        $postWithMeta = Post::create(['title' => 'With Meta', 'slug' => 'with-meta']);
        PostMeta::create(['post_id' => $postWithMeta->id, 'seo_title' => 'SEO']);

        Post::create(['title' => 'Without Meta', 'slug' => 'without-meta']);

        $filter = Has::make('meta');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('With Meta', $results->first()->title);
    }

    // =========================================================================
    // Has filter — BelongsTo
    // =========================================================================

    #[Test]
    public function has_filters_belongs_to(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@test.com']);
        Post::create(['title' => 'With User', 'slug' => 'with-user', 'user_id' => $user->id]);
        Post::create(['title' => 'Without User', 'slug' => 'without-user', 'user_id' => null]);

        $filter = Has::make('user');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('With User', $results->first()->title);
    }

    // =========================================================================
    // Has filter — BelongsToMany
    // =========================================================================

    #[Test]
    public function has_filters_belongs_to_many(): void
    {
        $postWithTags = Post::create(['title' => 'Tagged', 'slug' => 'tagged']);
        $tag = Tag::create(['name' => 'PHP']);
        $postWithTags->tags()->attach($tag);

        Post::create(['title' => 'Untagged', 'slug' => 'untagged']);

        $filter = Has::make('tags');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Tagged', $results->first()->title);
    }

    // =========================================================================
    // Has filter — HasManyThrough
    // =========================================================================

    #[Test]
    public function has_filters_has_many_through(): void
    {
        $countryWithPosts = Country::create(['name' => 'Denmark']);
        $user = User::create(['name' => 'John', 'email' => 'john@test.com', 'country_id' => $countryWithPosts->id]);
        Post::create(['title' => 'DK Post', 'slug' => 'dk-post', 'user_id' => $user->id]);

        Country::create(['name' => 'Empty Country']);

        $filter = Has::make('posts');

        $results = Country::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Denmark', $results->first()->name);
    }

    // =========================================================================
    // Has filter — HasOneThrough
    // =========================================================================

    #[Test]
    public function has_filters_has_one_through(): void
    {
        $userWithMeta = User::create(['name' => 'John', 'email' => 'john@test.com']);
        $post = Post::create(['title' => 'Post', 'slug' => 'post', 'user_id' => $userWithMeta->id]);
        PostMeta::create(['post_id' => $post->id, 'seo_title' => 'SEO']);

        User::create(['name' => 'Jane', 'email' => 'jane@test.com']);

        $filter = Has::make('latestPostMeta');

        $results = User::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('John', $results->first()->name);
    }

    // =========================================================================
    // Has filter — MorphOne
    // =========================================================================

    #[Test]
    public function has_filters_morph_one(): void
    {
        $postWithImage = Post::create(['title' => 'With Image', 'slug' => 'with-image']);
        Image::create(['url' => 'image.jpg', 'imageable_type' => Post::class, 'imageable_id' => $postWithImage->id]);

        Post::create(['title' => 'Without Image', 'slug' => 'without-image']);

        $filter = Has::make('image');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('With Image', $results->first()->title);
    }

    // =========================================================================
    // Has filter — MorphMany
    // =========================================================================

    #[Test]
    public function has_filters_morph_many(): void
    {
        $postWithActivities = Post::create(['title' => 'Active', 'slug' => 'active']);
        Activity::create(['action' => 'viewed', 'loggable_type' => Post::class, 'loggable_id' => $postWithActivities->id]);

        Post::create(['title' => 'Inactive', 'slug' => 'inactive']);

        $filter = Has::make('activities');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Active', $results->first()->title);
    }

    // =========================================================================
    // Has filter — MorphToMany
    // =========================================================================

    #[Test]
    public function has_filters_morph_to_many(): void
    {
        $postWithTags = Post::create(['title' => 'Tagged', 'slug' => 'tagged']);
        $tag = Tag::create(['name' => 'Laravel']);
        $postWithTags->polymorphicTags()->attach($tag);

        Post::create(['title' => 'Untagged', 'slug' => 'untagged']);

        $filter = Has::make('polymorphicTags');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Tagged', $results->first()->title);
    }

    // =========================================================================
    // Has filter — MorphTo
    // =========================================================================

    #[Test]
    public function has_filters_morph_to(): void
    {
        $post = Post::create(['title' => 'Post', 'slug' => 'post']);
        $user = User::create(['name' => 'John', 'email' => 'john@test.com']);
        Image::create(['url' => 'post-image.jpg', 'imageable_type' => Post::class, 'imageable_id' => $post->id]);
        Image::create(['url' => 'user-image.jpg', 'imageable_type' => User::class, 'imageable_id' => $user->id]);
        Image::create(['url' => 'orphan.jpg', 'imageable_type' => User::class, 'imageable_id' => 999]);

        $filter = Has::make('imageable');

        $results = Image::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['post-image.jpg', 'user-image.jpg'], $results->pluck('url')->all());
    }

    // =========================================================================
    // WhereHas filter — HasMany
    // =========================================================================

    #[Test]
    public function where_has_filters_has_many_with_sub_filters(): void
    {
        $post1 = Post::create(['title' => 'Post 1', 'slug' => 'post-1']);
        Comment::create(['post_id' => $post1->id, 'author' => 'John', 'body' => 'Great']);

        $post2 = Post::create(['title' => 'Post 2', 'slug' => 'post-2']);
        Comment::create(['post_id' => $post2->id, 'author' => 'Jane', 'body' => 'Nice']);

        $filter = WhereHas::make('comments')->withFilters([
            Where::make('author'),
        ]);

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['author' => 'John']))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Post 1', $results->first()->title);
    }

    #[Test]
    public function where_has_filters_has_many_with_multiple_sub_filters(): void
    {
        $post1 = Post::create(['title' => 'Post 1', 'slug' => 'post-1']);
        Comment::create(['post_id' => $post1->id, 'author' => 'John', 'body' => 'Great']);

        $post2 = Post::create(['title' => 'Post 2', 'slug' => 'post-2']);
        Comment::create(['post_id' => $post2->id, 'author' => 'John', 'body' => 'Bad']);

        $post3 = Post::create(['title' => 'Post 3', 'slug' => 'post-3']);
        Comment::create(['post_id' => $post3->id, 'author' => 'Jane', 'body' => 'Great']);

        $filter = WhereHas::make('comments')->withFilters([
            Where::make('author'),
            Where::make('body'),
        ]);

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['author' => 'John', 'body' => 'Great']))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Post 1', $results->first()->title);
    }

    // =========================================================================
    // WhereHas filter — HasOne
    // =========================================================================

    #[Test]
    public function where_has_filters_has_one_with_sub_filters(): void
    {
        $post1 = Post::create(['title' => 'Post 1', 'slug' => 'post-1']);
        PostMeta::create(['post_id' => $post1->id, 'seo_title' => 'Optimized']);

        $post2 = Post::create(['title' => 'Post 2', 'slug' => 'post-2']);
        PostMeta::create(['post_id' => $post2->id, 'seo_title' => 'Default']);

        $filter = WhereHas::make('meta')->withFilters([
            Where::make('seo_title'),
        ]);

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['seo_title' => 'Optimized']))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Post 1', $results->first()->title);
    }

    // =========================================================================
    // WhereHas filter — BelongsTo
    // =========================================================================

    #[Test]
    public function where_has_filters_belongs_to_with_sub_filters(): void
    {
        $john = User::create(['name' => 'John', 'email' => 'john@test.com']);
        $jane = User::create(['name' => 'Jane', 'email' => 'jane@test.com']);

        Post::create(['title' => 'Johns Post', 'slug' => 'johns', 'user_id' => $john->id]);
        Post::create(['title' => 'Janes Post', 'slug' => 'janes', 'user_id' => $jane->id]);

        $filter = WhereHas::make('user')->withFilters([
            Where::make('name'),
        ]);

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['name' => 'John']))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Johns Post', $results->first()->title);
    }

    // =========================================================================
    // WhereHas filter — BelongsToMany
    // =========================================================================

    #[Test]
    public function where_has_filters_belongs_to_many_with_sub_filters(): void
    {
        $post1 = Post::create(['title' => 'PHP Post', 'slug' => 'php']);
        $post2 = Post::create(['title' => 'JS Post', 'slug' => 'js']);

        $phpTag = Tag::create(['name' => 'PHP']);
        $jsTag = Tag::create(['name' => 'JavaScript']);

        $post1->tags()->attach($phpTag);
        $post2->tags()->attach($jsTag);

        $filter = WhereHas::make('tags')->withFilters([
            Where::make('name'),
        ]);

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['name' => 'PHP']))->get();

        $this->assertCount(1, $results);
        $this->assertSame('PHP Post', $results->first()->title);
    }

    // =========================================================================
    // WhereHas filter — MorphOne
    // =========================================================================

    #[Test]
    public function where_has_filters_morph_one_with_sub_filters(): void
    {
        $post1 = Post::create(['title' => 'Post 1', 'slug' => 'post-1']);
        Image::create(['url' => 'hero.jpg', 'imageable_type' => Post::class, 'imageable_id' => $post1->id]);

        $post2 = Post::create(['title' => 'Post 2', 'slug' => 'post-2']);
        Image::create(['url' => 'thumb.jpg', 'imageable_type' => Post::class, 'imageable_id' => $post2->id]);

        $filter = WhereHas::make('image')->withFilters([
            Where::make('url'),
        ]);

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['url' => 'hero.jpg']))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Post 1', $results->first()->title);
    }

    // =========================================================================
    // WhereHas filter — MorphMany
    // =========================================================================

    #[Test]
    public function where_has_filters_morph_many_with_sub_filters(): void
    {
        $post1 = Post::create(['title' => 'Post 1', 'slug' => 'post-1']);
        Activity::create(['action' => 'viewed', 'loggable_type' => Post::class, 'loggable_id' => $post1->id]);

        $post2 = Post::create(['title' => 'Post 2', 'slug' => 'post-2']);
        Activity::create(['action' => 'shared', 'loggable_type' => Post::class, 'loggable_id' => $post2->id]);

        $filter = WhereHas::make('activities')->withFilters([
            Where::make('action'),
        ]);

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['action' => 'viewed']))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Post 1', $results->first()->title);
    }

    // =========================================================================
    // WhereHas filter — MorphToMany
    // =========================================================================

    #[Test]
    public function where_has_filters_morph_to_many_with_sub_filters(): void
    {
        $post1 = Post::create(['title' => 'Post 1', 'slug' => 'post-1']);
        $post2 = Post::create(['title' => 'Post 2', 'slug' => 'post-2']);

        $laravelTag = Tag::create(['name' => 'Laravel']);
        $vueTag = Tag::create(['name' => 'Vue']);

        $post1->polymorphicTags()->attach($laravelTag);
        $post2->polymorphicTags()->attach($vueTag);

        $filter = WhereHas::make('polymorphicTags')->withFilters([
            Where::make('name'),
        ]);

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['name' => 'Laravel']))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Post 1', $results->first()->title);
    }

    // =========================================================================
    // WhereDoesntHave filter — HasMany
    // =========================================================================

    #[Test]
    public function where_doesnt_have_excludes_matching_relationship(): void
    {
        $post1 = Post::create(['title' => 'Post 1', 'slug' => 'post-1']);
        Comment::create(['post_id' => $post1->id, 'author' => 'John', 'body' => 'Great']);

        $post2 = Post::create(['title' => 'Post 2', 'slug' => 'post-2']);
        Comment::create(['post_id' => $post2->id, 'author' => 'Jane', 'body' => 'Nice']);

        $filter = WhereDoesntHave::make('comments')->withFilters([
            Where::make('author'),
        ]);

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['author' => 'John']))->get();

        $this->assertCount(1, $results);
        $this->assertSame('Post 2', $results->first()->title);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    #[Test]
    public function has_returns_all_when_all_have_relation(): void
    {
        $post1 = Post::create(['title' => 'Post 1', 'slug' => 'post-1']);
        Comment::create(['post_id' => $post1->id, 'author' => 'John', 'body' => 'A']);

        $post2 = Post::create(['title' => 'Post 2', 'slug' => 'post-2']);
        Comment::create(['post_id' => $post2->id, 'author' => 'Jane', 'body' => 'B']);

        $filter = Has::make('comments');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(2, $results);
    }

    #[Test]
    public function has_returns_empty_when_none_have_relation(): void
    {
        Post::create(['title' => 'Post 1', 'slug' => 'post-1']);
        Post::create(['title' => 'Post 2', 'slug' => 'post-2']);

        $filter = Has::make('comments');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, true))->get();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function where_has_with_empty_sub_filter_array_returns_all_with_relation(): void
    {
        $post1 = Post::create(['title' => 'Post 1', 'slug' => 'post-1']);
        Comment::create(['post_id' => $post1->id, 'author' => 'John', 'body' => 'A']);

        Post::create(['title' => 'Post 2', 'slug' => 'post-2']);

        $filter = WhereHas::make('comments');

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, []))->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function where_has_with_non_matching_sub_filter_returns_empty(): void
    {
        $post = Post::create(['title' => 'Post', 'slug' => 'post']);
        Comment::create(['post_id' => $post->id, 'author' => 'John', 'body' => 'A']);

        $filter = WhereHas::make('comments')->withFilters([
            Where::make('author'),
        ]);

        $results = Post::query()->tap(fn ($q) => $filter->apply($q, ['author' => 'NonExistent']))->get();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function has_filters_morph_to_doesnt_have(): void
    {
        $post = Post::create(['title' => 'Post', 'slug' => 'post']);
        Image::create(['url' => 'attached.jpg', 'imageable_type' => Post::class, 'imageable_id' => $post->id]);
        Image::create(['url' => 'orphan.jpg', 'imageable_type' => User::class, 'imageable_id' => 999]);

        $filter = Has::make('imageable');

        $results = Image::query()->tap(fn ($q) => $filter->apply($q, false))->get();

        $this->assertCount(1, $results);
        $this->assertSame('orphan.jpg', $results->first()->url);
    }
}
