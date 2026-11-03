<?php

declare(strict_types=1);

namespace Contenir\Audience;

use InvalidArgumentException;
use SensitiveParameter;

use function hash_hmac;
use function json_encode;
use function ksort;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * Hashes what is kept about a person after their details are sent: their email, and the values
 * last sent for them. A pepper only the site holds makes the hashes useless to anyone with a copy
 * of the tables alone.
 *
 * @api
 */
final readonly class EmailHasher
{
    public const int MIN_PEPPER_BYTES = 32;

    /**
     * @throws InvalidArgumentException When the pepper is shorter than 32 bytes.
     */
    public function __construct(
        #[SensitiveParameter]
        private string $pepper,
    ) {
        if (strlen($pepper) < self::MIN_PEPPER_BYTES) {
            throw new InvalidArgumentException('The audience pepper must be at least 32 bytes');
        }
    }

    public function email(Email $email): string
    {
        return hash_hmac('sha256', $email->value, $this->pepper);
    }

    /**
     * The hash of the values sent for a person, the same whatever order they're given in.
     *
     * @param array<string, scalar|null> $values
     */
    public function values(array $values): string
    {
        ksort($values);

        return hash_hmac('sha256', json_encode($values, JSON_THROW_ON_ERROR), $this->pepper);
    }
}
