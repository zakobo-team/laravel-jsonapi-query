<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Exceptions;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class JsonApiExceptionRenderer
{
    private const CONTENT_TYPE = 'application/vnd.api+json';

    /**
     * Return a closure suitable for registering with Laravel's exception handler.
     *
     * Usage in bootstrap/app.php:
     *   $exceptions->renderable(JsonApiExceptionRenderer::render());
     */
    public static function render(): Closure
    {
        return function (Throwable $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => self::renderValidation($e),
                $e instanceof ModelNotFoundException => self::renderError('Not Found', 404, $e->getMessage()),
                $e instanceof AuthenticationException => self::renderError('Unauthenticated', 401, $e->getMessage()),
                $e instanceof AuthorizationException => self::renderError('Forbidden', 403, $e->getMessage()),
                $e instanceof HttpException => self::renderError(
                    self::titleForStatus($e->getStatusCode()),
                    $e->getStatusCode(),
                    $e->getMessage(),
                ),
                default => self::renderError('Server Error', 500, config('app.debug') ? $e->getMessage() : 'An internal server error occurred.'),
            };
        };
    }

    private static function renderValidation(ValidationException $e): JsonResponse
    {
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'status' => '422',
                    'title' => 'Validation Error',
                    'detail' => $message,
                    'source' => [
                        'pointer' => self::fieldToPointer($field),
                    ],
                ];
            }
        }

        return self::jsonResponse(['errors' => $errors], 422);
    }

    private static function renderError(string $title, int $status, string $detail): JsonResponse
    {
        $error = [
            'status' => (string) $status,
            'title' => $title,
            'detail' => $detail,
        ];

        return self::jsonResponse(['errors' => [$error]], $status);
    }

    private static function jsonResponse(array $data, int $status): JsonResponse
    {
        return new JsonResponse(
            data: $data,
            status: $status,
            headers: ['Content-Type' => self::CONTENT_TYPE],
        );
    }

    /**
     * Convert a validation field name to a JSON:API source pointer.
     *
     * Fields like "title" become "/data/attributes/title".
     * Nested fields like "data.attributes.title" are preserved as "/data/attributes/title".
     */
    private static function fieldToPointer(string $field): string
    {
        // If the field already starts with "data.", convert dots to slashes
        if (str_starts_with($field, 'data.')) {
            return '/'.str_replace('.', '/', $field);
        }

        // Otherwise, assume it's an attribute name
        return '/data/attributes/'.str_replace('.', '/', $field);
    }

    private static function titleForStatus(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthenticated',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Server Error',
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }
}
