<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class JsonApiResponse
{
    private const CONTENT_TYPE = 'application/vnd.api+json';

    /**
     * Return a JSON:API response containing only a meta object.
     */
    public static function meta(array $meta, int $status = 200): JsonResponse
    {
        return new JsonResponse(
            data: ['meta' => $meta],
            status: $status,
            headers: ['Content-Type' => self::CONTENT_TYPE],
        );
    }

    /**
     * Return a 204 No Content response.
     */
    public static function noContent(): Response
    {
        return new Response('', 204);
    }

    /**
     * Return a JSON:API response with a single error object.
     */
    public static function error(string $title, int $status, ?string $detail = null): JsonResponse
    {
        $error = [
            'status' => (string) $status,
            'title' => $title,
        ];

        if ($detail !== null) {
            $error['detail'] = $detail;
        }

        return new JsonResponse(
            data: ['errors' => [$error]],
            status: $status,
            headers: ['Content-Type' => self::CONTENT_TYPE],
        );
    }

    /**
     * Return a JSON:API response with multiple error objects.
     */
    public static function errors(array $errors, int $status = 422): JsonResponse
    {
        return new JsonResponse(
            data: ['errors' => $errors],
            status: $status,
            headers: ['Content-Type' => self::CONTENT_TYPE],
        );
    }
}
