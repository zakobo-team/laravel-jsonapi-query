<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Http\Contracts;

use Illuminate\Database\Eloquent\Model;

interface JsonApiRelationshipController extends JsonApiController
{
    /**
     * Get the parent Eloquent model class name.
     *
     * @return class-string<Model>
     */
    public function getParentModel(): string;

    /**
     * Get the route parameter name that contains the parent model.
     */
    public function getParentRouteParameter(): string;

    /**
     * Get the relationship method name on the parent model.
     */
    public function getParentRelationship(): string;
}
