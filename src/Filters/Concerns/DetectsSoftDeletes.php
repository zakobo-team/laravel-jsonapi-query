<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;

trait DetectsSoftDeletes
{
    protected function modelUsesSoftDeletes(Builder $query): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($query->getModel()), true);
    }

    protected function removeSoftDeletingScope(Builder $query): void
    {
        $query->withoutGlobalScope(SoftDeletingScope::class);
    }

    protected function deletedAtColumn(Builder $query): string
    {
        /** @var object{getQualifiedDeletedAtColumn: callable(): string}|object $model */
        $model = $query->getModel();

        return $model->getQualifiedDeletedAtColumn();
    }
}
