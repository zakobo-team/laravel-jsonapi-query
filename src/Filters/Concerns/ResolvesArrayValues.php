<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters\Concerns;

trait ResolvesArrayValues
{
    protected ?string $delimiter = null;

    public function delimiter(string $delimiter): static
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    protected function resolveValues(mixed $value): array
    {
        if (is_string($value) && $this->delimiter !== null) {
            $value = explode($this->delimiter, $value);
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        return array_map(fn ($v) => $this->deserialize($v), $value);
    }
}
