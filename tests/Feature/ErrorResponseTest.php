<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Zakobo\JsonApiQuery\Exceptions\JsonApiExceptionRenderer;
use Zakobo\JsonApiQuery\Tests\Fixtures\Models\Post;
use Zakobo\JsonApiQuery\Tests\TestCase;

class ErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    private const CONTENT_TYPE = 'application/vnd.api+json';

    protected function defineRoutes($router): void
    {
        $router->get('/test/model-not-found', function () {
            throw (new ModelNotFoundException)->setModel('Post', [999]);
        });

        $router->get('/test/validation', function (Request $request) {
            $request->validate([
                'title' => 'required',
                'slug' => 'required|min:3',
            ]);
        });

        $router->get('/test/unauthenticated', function () {
            throw new AuthenticationException('Unauthenticated.');
        });

        $router->get('/test/forbidden', function () {
            throw new AuthorizationException('This action is unauthorized.');
        });

        $router->get('/test/access-denied', function () {
            throw new AccessDeniedHttpException('Access denied.');
        });

        $router->post('/test/method-not-allowed-target', function () {
            return 'ok';
        });

        $router->get('/test/generic-exception', function () {
            throw new \RuntimeException('Something went wrong');
        });

        $router->get('/test/not-found-http', function () {
            throw new NotFoundHttpException('Route not found.');
        });

        $router->get('/test/invalid-jsonapi-query', function (Request $request) {
            return Post::query()->jsonApiCollection($request);
        });
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Disable debug mode so generic exceptions return 500 without stack trace
        $app['config']->set('app.debug', false);
    }

    protected function resolveApplicationExceptionHandler($app): void
    {
        parent::resolveApplicationExceptionHandler($app);

        // Register the JSON:API exception renderer
        $app->make(ExceptionHandler::class)
            ->renderable(JsonApiExceptionRenderer::render());
    }

    // =========================================================================
    // ModelNotFoundException → 404
    // =========================================================================

    #[Test]
    public function model_not_found_returns_json_api_404(): void
    {
        $response = $this->getJson('/test/model-not-found');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', self::CONTENT_TYPE);
        $response->assertJsonStructure(['errors' => [['status', 'title', 'detail']]]);
        $response->assertJsonPath('errors.0.status', '404');
    }

    // =========================================================================
    // ValidationException → 422
    // =========================================================================

    #[Test]
    public function validation_error_returns_json_api_422_with_source_pointers(): void
    {
        $response = $this->getJson('/test/validation');

        $response->assertStatus(422);
        $response->assertHeader('Content-Type', self::CONTENT_TYPE);
        $response->assertJsonPath('errors.0.status', '422');
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/title');
    }

    #[Test]
    public function multiple_validation_errors_return_multiple_error_objects(): void
    {
        $response = $this->getJson('/test/validation');

        $response->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertGreaterThanOrEqual(2, count($errors));

        $pointers = array_column(array_column($errors, 'source'), 'pointer');
        $this->assertContains('/data/attributes/title', $pointers);
        $this->assertContains('/data/attributes/slug', $pointers);
    }

    // =========================================================================
    // AuthenticationException → 401
    // =========================================================================

    #[Test]
    public function unauthenticated_returns_json_api_401(): void
    {
        $response = $this->getJson('/test/unauthenticated');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', self::CONTENT_TYPE);
        $response->assertJsonPath('errors.0.status', '401');
    }

    // =========================================================================
    // AuthorizationException → 403
    // =========================================================================

    #[Test]
    public function forbidden_returns_json_api_403(): void
    {
        $response = $this->getJson('/test/forbidden');

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', self::CONTENT_TYPE);
        $response->assertJsonPath('errors.0.status', '403');
    }

    #[Test]
    public function access_denied_http_exception_returns_json_api_403(): void
    {
        $response = $this->getJson('/test/access-denied');

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', self::CONTENT_TYPE);
        $response->assertJsonPath('errors.0.status', '403');
    }

    // =========================================================================
    // MethodNotAllowedHttpException → 405
    // =========================================================================

    #[Test]
    public function method_not_allowed_returns_json_api_405(): void
    {
        // GET to a POST-only route
        $response = $this->getJson('/test/method-not-allowed-target');

        $response->assertStatus(405);
        $response->assertHeader('Content-Type', self::CONTENT_TYPE);
        $response->assertJsonPath('errors.0.status', '405');
    }

    // =========================================================================
    // NotFoundHttpException → 404
    // =========================================================================

    #[Test]
    public function not_found_http_exception_returns_json_api_404(): void
    {
        $response = $this->getJson('/test/not-found-http');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', self::CONTENT_TYPE);
        $response->assertJsonPath('errors.0.status', '404');
    }

    // =========================================================================
    // Generic Exception → 500
    // =========================================================================

    #[Test]
    public function generic_exception_returns_json_api_500(): void
    {
        $response = $this->getJson('/test/generic-exception');

        $response->assertStatus(500);
        $response->assertHeader('Content-Type', self::CONTENT_TYPE);
        $response->assertJsonPath('errors.0.status', '500');
    }

    // =========================================================================
    // Error status is STRING not integer
    // =========================================================================

    #[Test]
    public function error_status_is_always_a_string(): void
    {
        $response = $this->getJson('/test/model-not-found');

        $status = $response->json('errors.0.status');
        $this->assertIsString($status);
    }

    #[Test]
    public function invalid_json_api_query_returns_json_api_400_with_source_parameter(): void
    {
        Post::create(['title' => 'Test', 'slug' => 'test']);

        $response = $this->getJson('/test/invalid-jsonapi-query?filter[computed_score]=10');

        $response->assertStatus(400);
        $response->assertHeader('Content-Type', self::CONTENT_TYPE);
        $response->assertJsonPath('errors.0.status', '400');
        $response->assertJsonPath('errors.0.source.parameter', 'filter[computed_score]');
    }

    #[Test]
    public function invalid_include_filter_returns_json_api_400_with_source_parameter(): void
    {
        Post::create(['title' => 'Test', 'slug' => 'test']);

        $response = $this->getJson('/test/invalid-jsonapi-query?includeFilter[comments.author]=John');

        $response->assertStatus(400);
        $response->assertHeader('Content-Type', self::CONTENT_TYPE);
        $response->assertJsonPath('errors.0.status', '400');
        $response->assertJsonPath('errors.0.source.parameter', 'includeFilter[comments.author]');
    }
}
