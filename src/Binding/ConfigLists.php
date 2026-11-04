<?php

declare(strict_types=1);

namespace Contenir\Audience\Binding;

use Contenir\Audience\Exception\InvalidBinding;

use function array_filter;
use function array_is_list;
use function count;
use function is_string;

/**
 * Checks the two lists in a binding's config: its tags, and its service's options.
 *
 * @internal
 */
final class ConfigLists
{
    /**
     * @param array<array-key, mixed> $options
     *
     * @return array<string, string|int|float|bool>
     *
     * @throws InvalidBinding
     */
    public static function options(string $name, array $options): array
    {
        $plain = array_filter($options, is_scalar(...));
        if (count($plain) !== count($options) || array_is_list($options) && [] !== $options) {
            throw InvalidBinding::because($name, 'needs "options" as names and plain values');
        }

        /** @var array<string, string|int|float|bool> $options */
        return $options;
    }

    /**
     * @param array<array-key, mixed> $tags
     *
     * @return list<string>
     *
     * @throws InvalidBinding
     */
    public static function tags(string $name, array $tags): array
    {
        $text = array_filter($tags, static fn(mixed $tag): bool => is_string($tag) && '' !== $tag);
        if (! array_is_list($tags) || count($text) !== count($tags)) {
            throw InvalidBinding::because($name, 'needs "tags" as a list of text');
        }

        /** @var list<string> $tags */
        return $tags;
    }
}
