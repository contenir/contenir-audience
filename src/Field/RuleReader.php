<?php

declare(strict_types=1);

namespace Contenir\Audience\Field;

use InvalidArgumentException;

use function array_is_list;
use function array_key_exists;
use function is_array;
use function is_scalar;
use function is_string;
use function sprintf;

/**
 * Reads one field's rule from a binding's "fields" config (see {@see FieldMap}).
 *
 * @internal
 */
final class RuleReader
{
    /**
     * The rule for the service's field $remote, from its config.
     *
     * @throws InvalidArgumentException
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; each is checked before use.
     */
    public static function read(string $remote, mixed $rule): FieldRule
    {
        if (is_string($rule)) {
            return new FieldRule($remote, $rule);
        }
        if (! is_array($rule)) {
            throw new InvalidArgumentException(sprintf('Field "%s" needs a field name or a rule', $remote));
        }
        if (array_key_exists('value', $rule)) {
            $value = $rule['value'];

            return new FieldRule(
                $remote,
                null,
                constant: null === $value || is_scalar($value)
                    ? $value
                    : throw new InvalidArgumentException(sprintf('Field "%s" needs a plain value', $remote)),
            );
        }

        $from = $rule['from'] ?? null;
        if (! is_string($from)) {
            throw new InvalidArgumentException(sprintf('Field "%s" needs "from" or "value"', $remote));
        }

        return new FieldRule($remote, $from, self::transforms($remote, $rule['transforms'] ?? []));
    }

    /**
     * @return list<Transform>
     *
     * @throws InvalidArgumentException
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; each is checked before use.
     */
    private static function transforms(string $remote, mixed $names): array
    {
        if (! is_array($names) || ! array_is_list($names)) {
            throw new InvalidArgumentException(sprintf('Field "%s" needs its transforms as a list', $remote));
        }

        $transforms = [];
        foreach ($names as $name) {
            $transforms[] = (is_string($name) ? Transform::tryFrom($name) : null)
                ?? throw new InvalidArgumentException(sprintf('Field "%s" has an unknown transform', $remote));
        }

        return $transforms;
    }
}
