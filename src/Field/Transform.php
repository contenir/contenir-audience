<?php

declare(strict_types=1);

namespace Contenir\Audience\Field;

use DateTimeImmutable;
use Exception;

use function in_array;
use function is_bool;
use function is_string;
use function mb_strtolower;
use function mb_strtoupper;
use function preg_match;
use function preg_split;
use function strtolower;
use function trim;

/**
 * One change made to a value on its way to a service, named in a binding's field map.
 *
 * @api
 */
enum Transform: string
{
    case Trim  = 'trim';
    case Lower = 'lower';
    case Upper = 'upper';

    /** "Yes" for a ticked box or a yes-like answer, otherwise "No" */
    case YesNo = 'yes-no';

    /** A date as YYYY-MM-DD, or nothing when it can't be read as one */
    case Date = 'date';

    /** The first word of a full name */
    case FirstName = 'first-name';

    /** Everything after the first word of a full name */
    case LastName = 'last-name';

    private const array YES = ['1', 'yes', 'y', 'true', 'on'];

    private static function date(string $value): ?string
    {
        try {
            return 1 === preg_match('/\S/', $value) ? (new DateTimeImmutable($value))->format('Y-m-d') : null;
        } catch (Exception) {
            return null;
        }
    }

    private static function isYes(string|int|float|bool|null $value): bool
    {
        return is_bool($value) ? $value : in_array(strtolower(trim((string) $value)), self::YES, strict: true);
    }

    /**
     * @return non-empty-list<string> The first word, and the rest when there is any
     */
    private static function words(string $value): array
    {
        $parts = preg_split('/\s+/u', trim($value), limit: 2);

        return false === $parts || [] === $parts ? [''] : $parts;
    }

    public function apply(string|int|float|bool|null $value): string|int|float|bool|null
    {
        if (self::YesNo === $this) {
            return self::isYes($value) ? 'Yes' : 'No';
        }
        if (! is_string($value)) {
            return $value;
        }

        return match ($this) {
            self::Trim => trim($value),
            self::Lower => mb_strtolower($value),
            self::Upper => mb_strtoupper($value),
            self::Date => self::date($value),
            self::FirstName => self::words($value)[0],
            self::LastName => self::words($value)[1] ?? '',
        };
    }
}
