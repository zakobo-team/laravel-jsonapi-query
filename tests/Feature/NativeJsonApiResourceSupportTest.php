<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Exceptions\UnsupportedFilterFieldException;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Comment;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\ConfigurablePlainPostResource;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PlainPostResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class NativeJsonApiResourceSupportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function apply_json_api_works_with_plain_laravel_json_api_resource(): void
    {
        Post::create(['title' => 'Alpha', 'slug' => 'alpha', 'votes' => 50]);
        Post::create(['title' => 'Bravo', 'slug' => 'bravo', 'votes' => 200]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['votes' => ['gt' => '100']],
        ]);

        $results = Post::query()
            ->applyJsonApi(PlainPostResource::class, $request)
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('Bravo', $results->first()->title);
    }

    #[Test]
    public function configurable_plain_resource_can_define_default_sort_and_additional_sort(): void
    {
        $postOld = Post::create(['title' => 'Old', 'slug' => 'old']);
        Comment::create(['post_id' => $postOld->id, 'author' => 'A', 'body' => 'x', 'created_at' => '2024-01-01']);

        $postNew = Post::create(['title' => 'New', 'slug' => 'new']);
        Comment::create(['post_id' => $postNew->id, 'author' => 'B', 'body' => 'y', 'created_at' => '2024-06-01']);

        $request = Request::create('/posts', 'GET');

        $results = Post::query()
            ->applyJsonApi(ConfigurablePlainPostResource::class, $request)
            ->get();

        $this->assertSame('New', $results->first()->title);
        $this->assertSame('Old', $results->last()->title);
    }

    #[Test]
    public function configurable_plain_resource_can_exclude_auto_filterable_fields(): void
    {
        Post::create(['title' => 'Alpha', 'slug' => 'alpha', 'votes' => 50]);

        $request = Request::create('/posts', 'GET', [
            'filter' => ['created_at' => '2024-01-01 00:00:00'],
        ]);

        $this->expectException(UnsupportedFilterFieldException::class);

        Post::query()->applyJsonApi(ConfigurablePlainPostResource::class, $request)->get();
    }

    #[Test]
    public function configurable_plain_resource_uses_its_pagination_defaults_in_collection_macro(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            Post::create(['title' => "Post {$i}", 'slug' => "post-{$i}"]);
        }

        $request = Request::create('/posts', 'GET');

        $response = Post::query()->jsonApiCollection(ConfigurablePlainPostResource::class, $request);

        $this->assertCount(5, $response->resource->items());
    }
}
