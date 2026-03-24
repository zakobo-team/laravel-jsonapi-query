<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zakobo\JsonApiQuery\Http\JsonApiResponse;

class JsonApiResponseTest extends TestCase
{
    private const CONTENT_TYPE = 'application/vnd.api+json';

    // =========================================================================
    // meta()
    // =========================================================================

    #[Test]
    public function meta_returns_200_with_meta_object(): void
    {
        $response = JsonApiResponse::meta(['message' => 'Success', 'count' => 3]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::CONTENT_TYPE, $response->headers->get('Content-Type'));

        $body = json_decode($response->getContent(), true);
        $this->assertSame(['meta' => ['message' => 'Success', 'count' => 3]], $body);
    }

    #[Test]
    public function meta_with_custom_status_code(): void
    {
        $response = JsonApiResponse::meta(['accepted' => true], 202);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame(self::CONTENT_TYPE, $response->headers->get('Content-Type'));

        $body = json_decode($response->getContent(), true);
        $this->assertSame(['meta' => ['accepted' => true]], $body);
    }

    // =========================================================================
    // noContent()
    // =========================================================================

    #[Test]
    public function no_content_returns_204_with_empty_body(): void
    {
        $response = JsonApiResponse::noContent();

        $this->assertSame(204, $response->getStatusCode());
        $this->assertEmpty($response->getContent());
    }

    // =========================================================================
    // error()
    // =========================================================================

    #[Test]
    public function error_returns_single_error_object_with_status_as_string(): void
    {
        $response = JsonApiResponse::error('Not Found', 404);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(self::CONTENT_TYPE, $response->headers->get('Content-Type'));

        $body = json_decode($response->getContent(), true);
        $this->assertCount(1, $body['errors']);
        $this->assertSame('404', $body['errors'][0]['status']);
        $this->assertSame('Not Found', $body['errors'][0]['title']);
        $this->assertArrayNotHasKey('detail', $body['errors'][0]);
    }

    #[Test]
    public function error_with_detail_included(): void
    {
        $response = JsonApiResponse::error('Validation Error', 422, 'The title field is required.');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(self::CONTENT_TYPE, $response->headers->get('Content-Type'));

        $body = json_decode($response->getContent(), true);
        $this->assertSame('Validation Error', $body['errors'][0]['title']);
        $this->assertSame('The title field is required.', $body['errors'][0]['detail']);
        $this->assertSame('422', $body['errors'][0]['status']);
    }

    // =========================================================================
    // errors()
    // =========================================================================

    #[Test]
    public function errors_returns_multiple_error_objects(): void
    {
        $errors = [
            ['status' => '422', 'title' => 'Validation Error', 'detail' => 'Title is required.', 'source' => ['pointer' => '/data/attributes/title']],
            ['status' => '422', 'title' => 'Validation Error', 'detail' => 'Slug is required.', 'source' => ['pointer' => '/data/attributes/slug']],
        ];

        $response = JsonApiResponse::errors($errors, 422);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(self::CONTENT_TYPE, $response->headers->get('Content-Type'));

        $body = json_decode($response->getContent(), true);
        $this->assertCount(2, $body['errors']);
        $this->assertSame('/data/attributes/title', $body['errors'][0]['source']['pointer']);
        $this->assertSame('/data/attributes/slug', $body['errors'][1]['source']['pointer']);
    }

    // =========================================================================
    // Content-Type consistency
    // =========================================================================

    #[Test]
    public function all_json_responses_have_correct_content_type(): void
    {
        $meta = JsonApiResponse::meta(['ok' => true]);
        $error = JsonApiResponse::error('Oops', 500);
        $errors = JsonApiResponse::errors([['status' => '400', 'title' => 'Bad']], 400);

        $this->assertSame(self::CONTENT_TYPE, $meta->headers->get('Content-Type'));
        $this->assertSame(self::CONTENT_TYPE, $error->headers->get('Content-Type'));
        $this->assertSame(self::CONTENT_TYPE, $errors->headers->get('Content-Type'));
    }
}
