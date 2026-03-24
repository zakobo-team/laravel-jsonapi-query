<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters\Concerns;

use Closure;

trait DeserializesValue
{
    protected ?Closure $deserializer = null;

    public function deserializeUsing(Closure $deserializer): static
    {
        $this->deserializer = $deserializer;

        return $this;
    }

    public function asBoolean(): static
    {
        return $this->deserializeUsing(
            fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        );
    }

    protected function deserialize(mixed $value): mixed
    {
        if ($this->deserializer !== null) {
            return ($this->deserializer)($value);
        }

        return $value;
    }
}
