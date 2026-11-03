<?php

declare(strict_types=1);

namespace Contenir\Audience;

use Contenir\Audience\Exception\InvalidEmail;

use function filter_var;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_EMAIL;

/**
 * An email address as every service keys a person by it: trimmed and lower-cased, so the same
 * person typed two ways is one subscriber. Only ASCII addresses pass, as PHP's email check
 * allows no others, so lower-casing needs nothing more than strtolower().
 *
 * @api
 */
final readonly class Email
{
    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidEmail When the text is not an email address.
     */
    public static function fromString(string $address): self
    {
        $email = strtolower(trim($address));
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw InvalidEmail::of($address);
        }

        return new self($email);
    }
}
