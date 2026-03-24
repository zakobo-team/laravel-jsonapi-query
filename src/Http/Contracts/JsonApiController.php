<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Http\Contracts;

use Illuminate\Database\Eloquent\Model;
use Zakobo\JsonApiQuery\JsonApiQueryResource;

interface JsonApiController
{
    /**
     * Get the JSON:API resource class name.
     *
     * @return class-string<JsonApiQueryResource>
     */
    public function getResource(): string;

    /**
     * Get the Eloquent model class name.
     *
     * @return class-string<Model>
     */
    public function getModel(): string;
}
