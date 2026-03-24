<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature\Filters;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Filters\WherePivot;
use Zakobo\JsonApiQuery\Filters\WherePivotIn;
use Zakobo\JsonApiQuery\Filters\WherePivotNotIn;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Tag;
use Zakobo\JsonApiQuery\Tests\TestCase;

class WherePivotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_filters_by_pivot_attribute(): void
    {
        $post = Post::create(['title' => 'Test Post', 'slug' => 'test']);
        $approvedTag = Tag::create(['name' => 'Approved Tag']);
        $unapprovedTag = Tag::create(['name' => 'Unapproved Tag']);

        $post->tags()->attach($approvedTag, ['approved' => true]);
        $post->tags()->attach($unapprovedTag, ['approved' => false]);

        $filter = WherePivot::make('approved');
        $relation = $post->tags();
        $filter->applyToRelation($relation, true);

        $results = $relation->get();

        $this->assertCount(1, $results);
        $this->assertSame('Approved Tag', $results->first()->name);
    }

    #[Test]
    public function it_filters_pivot_in_with_array(): void
    {
        $post = Post::create(['title' => 'Test', 'slug' => 'test']);
        $tag1 = Tag::create(['name' => 'Tag 1']);
        $tag2 = Tag::create(['name' => 'Tag 2']);
        $tag3 = Tag::create(['name' => 'Tag 3']);

        $post->tags()->attach($tag1, ['approved' => true]);
        $post->tags()->attach($tag2, ['approved' => false]);
        $post->tags()->attach($tag3, ['approved' => true]);

        $filter = WherePivotIn::make('approved');
        $relation = $post->tags();
        $filter->applyToRelation($relation, [true]);

        $results = $relation->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['Tag 1', 'Tag 3'], $results->pluck('name')->all());
    }

    #[Test]
    public function it_excludes_with_pivot_not_in(): void
    {
        $post = Post::create(['title' => 'Test', 'slug' => 'test']);
        $tag1 = Tag::create(['name' => 'Tag 1']);
        $tag2 = Tag::create(['name' => 'Tag 2']);

        $post->tags()->attach($tag1, ['approved' => true]);
        $post->tags()->attach($tag2, ['approved' => false]);

        $filter = WherePivotNotIn::make('approved');
        $relation = $post->tags();
        $filter->applyToRelation($relation, [true]);

        $results = $relation->get();

        $this->assertCount(1, $results);
        $this->assertSame('Tag 2', $results->first()->name);
    }

    #[Test]
    public function it_returns_empty_when_no_pivot_match(): void
    {
        $post = Post::create(['title' => 'Test', 'slug' => 'test']);
        $tag = Tag::create(['name' => 'Tag']);
        $post->tags()->attach($tag, ['approved' => false]);

        $filter = WherePivot::make('approved');
        $relation = $post->tags();
        $filter->applyToRelation($relation, true);

        $results = $relation->get();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function it_returns_empty_when_relationship_is_empty(): void
    {
        $post = Post::create(['title' => 'Test', 'slug' => 'test']);

        $filter = WherePivot::make('approved');
        $relation = $post->tags();
        $filter->applyToRelation($relation, true);

        $results = $relation->get();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function it_deserializes_boolean_on_pivot(): void
    {
        $post = Post::create(['title' => 'Test', 'slug' => 'test']);
        $tag = Tag::create(['name' => 'Approved']);
        $post->tags()->attach($tag, ['approved' => true]);

        $filter = WherePivot::make('approved')->asBoolean();
        $relation = $post->tags();
        $filter->applyToRelation($relation, '1');

        $results = $relation->get();

        $this->assertCount(1, $results);
        $this->assertSame('Approved', $results->first()->name);
    }
}
