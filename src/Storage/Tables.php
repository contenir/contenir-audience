<?php

declare(strict_types=1);

namespace Contenir\Audience\Storage;

use InvalidArgumentException;

use function preg_match;

/**
 * The names of one add-on's audience tables: its prefix, such as "ext_mailchimp_", then the
 * table's own name.
 *
 * @api
 */
final readonly class Tables
{
    /**
     * @throws InvalidArgumentException When the prefix isn't lowercase letters, digits and underscores ending in one.
     */
    public function __construct(
        public string $prefix,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_]*_$/D', $prefix)) {
            throw new InvalidArgumentException(
                "An audience table prefix is lowercase letters, digits and underscores, ending in \"_\", not \"{$prefix}\"",
            );
        }
    }

    public function consent(): string
    {
        return "{$this->prefix}consent";
    }

    public function outbox(): string
    {
        return "{$this->prefix}outbox";
    }

    public function subject(): string
    {
        return "{$this->prefix}subject";
    }

    public function suppression(): string
    {
        return "{$this->prefix}suppression";
    }

    public function webhook(): string
    {
        return "{$this->prefix}webhook";
    }
}
