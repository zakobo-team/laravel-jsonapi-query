<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

trait DetectsSoftDeletes
{
    protected function modelUsesSoftDeletes(Builder $query): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($query->getModel()));
    }
}
