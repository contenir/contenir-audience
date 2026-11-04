<?php

declare(strict_types=1);

namespace Contenir\Audience\Storage;

use Contenir\Audience\Consent;
use Contenir\Audience\Exception\StorageException;
use JsonException;
use UnexpectedValueException;

use function is_array;
use function is_scalar;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * The values and consent an outbox row keeps, as JSON text.
 *
 * @internal
 */
final class Json
{
    public static function consent(?Consent $consent): ?string
    {
        return null === $consent ? null : json_encode($consent->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * @throws StorageException
     */
    public static function decodeConsent(?string $json): ?Consent
    {
        if (null === $json) {
            return null;
        }

        try {
            return Consent::fromArray(self::decode($json));
        } catch (UnexpectedValueException) {
            throw StorageException::unreadable('consent', 'a consent');
        }
    }

    /**
     * @return array<string, string|int|float|bool|null>
     *
     * @throws StorageException
     *
     * @mago-expect analysis:mixed-assignment Stored JSON is untyped; each value is checked as plain.
     */
    public static function decodeValues(?string $json): array
    {
        $values = [];
        foreach (self::decode($json) as $key => $value) {
            if (null !== $value && ! is_scalar($value)) {
                throw StorageException::unreadable('payload', 'plain values');
            }
            $values[(string) $key] = $value;
        }

        return $values;
    }

    /**
     * @param array<string, string|int|float|bool|null> $values
     */
    public static function values(array $values): string
    {
        return json_encode($values, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws StorageException
     *
     * @mago-expect analysis:mixed-assignment Decoded JSON is untyped; it's checked as an object.
     */
    private static function decode(?string $json): array
    {
        if (null === $json) {
            return [];
        }

        try {
            $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw StorageException::unreadable('payload', 'JSON');
        }

        return is_array($decoded) ? $decoded : throw StorageException::unreadable('payload', 'a JSON object');
    }
}
