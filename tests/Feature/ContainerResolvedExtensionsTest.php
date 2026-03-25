<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\Fixtures\Resources\PostContainerResolvedExtensionsResource;
use Zakobo\JsonApiQuery\Tests\TestCase;

class ContainerResolvedExtensionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_additional_filters_from_the_container_without_static_make(): void
    {
        Post::create(['title' => 'Alpha', 'slug' => 'alpha']);
        Post::create(['title' => 'Bravo', 'slug' => 'bravo']);

        $request = Request::create('/posts', 'GET', ['filter' => ['title-match' => 'Bravo']]);

        $result = Post::query()->jsonApiCollection(PostContainerResolvedExtensionsResource::class, $request);
        $data = json_decode($result->toResponse($request)->getContent(), true);

        $this->assertCount(1, $data['data']);
        $this->assertSame('Bravo', $data['data'][0]['attributes']['title']);
    }

    #[Test]
    public function it_resolves_additional_sorts_from_the_container_without_static_make(): void
    {
        Post::create(['title' => 'A', 'slug' => 'a']);
        Post::create(['title' => 'Long title', 'slug' => 'long-title']);
        Post::create(['title' => 'Mid', 'slug' => 'mid']);

        $request = Request::create('/posts', 'GET', ['sort' => '-title-length']);

        $result = Post::query()->jsonApiCollection(PostContainerResolvedExtensionsResource::class, $request);
        $data = json_decode($result->toResponse($request)->getContent(), true);

        $titles = array_column(array_column($data['data'], 'attributes'), 'title');

        $this->assertSame(['Long title', 'Mid', 'A'], $titles);
    }
}
