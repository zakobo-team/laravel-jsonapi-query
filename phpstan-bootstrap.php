<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

Builder::macro('applyJsonApi', function (...$arguments): Builder {
    /** @var Builder $this */
    return $this;
});

Builder::macro('jsonApiCollection', function (...$arguments): AnonymousResourceCollection {
    throw new LogicException('This bootstrap file only exists to teach PHPStan about query macros.');
});
