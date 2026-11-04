<?php

declare(strict_types=1);

namespace Contenir\Audience\Field;

use function array_key_exists;

/**
 * How one of a service's fields is filled: from a field of the form or record, changed by its
 * transforms in order, or with a fixed value.
 *
 * @api
 */
final readonly class FieldRule
{
    /**
     * @param list<Transform> $transforms
     */
    public function __construct(
        public string $remote,
        public ?string $local,
        public array $transforms = [],
        public string|int|float|bool|null $constant = null,
    ) {}

    /**
     * Whether $values hold what this rule needs: a constant always does.
     *
     * @param array<string, string|int|float|bool|null> $values
     */
    public function appliesTo(array $values): bool
    {
        return null === $this->local || array_key_exists($this->local, $values);
    }

    /**
     * @param array<string, string|int|float|bool|null> $values
     */
    public function value(array $values): string|int|float|bool|null
    {
        $value = null === $this->local ? $this->constant : $values[$this->local] ?? null;
        foreach ($this->transforms as $transform) {
            $value = $transform->apply($value);
        }

        return $value;
    }
}
