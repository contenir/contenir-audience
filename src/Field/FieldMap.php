<?php

declare(strict_types=1);

namespace Contenir\Audience\Field;

use InvalidArgumentException;

/**
 * Which of a service's fields are filled from which of a form's or record's, as a binding's
 * "fields" config gives them, keyed by the service's field:
 *
 *     'FNAME'  => ['from' => 'name', 'transforms' => ['trim', 'first-name']],
 *     'LNAME'  => ['from' => 'name', 'transforms' => ['trim', 'last-name']],
 *     'SOURCE' => ['value' => 'website'],
 *     'PHONE'  => 'phone',
 *
 * A field missing from the values given is left out, so it isn't blanked at the service.
 *
 * @api
 */
final readonly class FieldMap
{
    /**
     * @param list<FieldRule> $rules
     */
    public function __construct(
        public array $rules = [],
    ) {}

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidArgumentException When a field's rule can't be read.
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; RuleReader checks each one.
     */
    public static function fromConfig(array $config): self
    {
        $rules = [];
        foreach ($config as $remote => $rule) {
            $rules[] = RuleReader::read((string) $remote, $rule);
        }

        return new self($rules);
    }

    /**
     * @param array<string, string|int|float|bool|null> $values
     *
     * @return array<string, string|int|float|bool|null>
     */
    public function apply(array $values): array
    {
        $mapped = [];
        foreach ($this->rules as $rule) {
            if (! $rule->appliesTo($values)) {
                continue;
            }

            $mapped[$rule->remote] = $rule->value($values);
        }

        return $mapped;
    }
}
