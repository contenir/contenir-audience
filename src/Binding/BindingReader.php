<?php

declare(strict_types=1);

namespace Contenir\Audience\Binding;

use Contenir\Audience\Exception\InvalidBinding;
use Contenir\Audience\Field\FieldMap;
use InvalidArgumentException;

use function array_diff;
use function array_keys;
use function implode;

/**
 * Reads one binding from its config:
 *
 *     'newsletter' => [
 *         'list'          => 'a1b2c3',
 *         'source'        => 'site',
 *         'email'         => 'email',
 *         'fields'        => ['Name' => 'name'],
 *         'consent'       => 'Signing up to the newsletter',
 *         'double_opt_in' => true,
 *         'tags'          => ['website'],
 *         'on_delete'     => 'unsubscribe',
 *         'options'       => ['consent_to_track' => 'Yes'],
 *     ],
 *
 * Only "list" is required. A key it doesn't know is refused, so a typo can't pass unnoticed.
 *
 * @internal
 */
final class BindingReader
{
    private const array KEYS = [
        'list',
        'source',
        'email',
        'fields',
        'consent',
        'double_opt_in',
        'tags',
        'on_delete',
        'options',
    ];

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidBinding
     */
    public static function read(string $name, array $config, OnDelete $onDelete): Binding
    {
        $unknown = array_diff(array_keys($config), self::KEYS);
        if ([] !== $unknown) {
            throw InvalidBinding::because($name, 'has unknown keys: ' . implode(', ', $unknown));
        }

        $values = new ConfigValues($name, $config);
        try {
            $fields = FieldMap::fromConfig($values->map('fields'));
        } catch (InvalidArgumentException $e) {
            throw InvalidBinding::because($name, "has a field it can't read: {$e->getMessage()}");
        }

        return new Binding(
            $name,
            $values->required('list'),
            (string) $values->text('source', Binding::SITE),
            (string) $values->text('email', 'email'),
            $fields,
            $values->text('consent'),
            $values->flag('double_opt_in', default: true),
            $values->tags(),
            $values->onDelete($onDelete),
            $values->options(),
        );
    }
}
