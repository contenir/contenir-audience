<?php

declare(strict_types=1);

namespace Contenir\Audience;

use DateTimeImmutable;
use DateTimeInterface;
use UnexpectedValueException;

use function is_string;

/**
 * A person's agreement to be on a list, as the consent ledger keeps it: the wording they agreed
 * to, where, when, and from which address.
 *
 * @api
 */
final readonly class Consent
{
    public function __construct(
        public string $wording,
        public ConsentSource $source,
        public DateTimeImmutable $at,
        public ?string $ip = null,
    ) {}

    /**
     * A consent as {@see toArray()} wrote it.
     *
     * @param array<array-key, mixed> $stored
     *
     * @throws UnexpectedValueException When the stored consent is incomplete.
     *
     * @mago-expect analysis:mixed-assignment Stored values are untyped; each is checked before use.
     */
    public static function fromArray(array $stored): self
    {
        $wording = $stored['wording'] ?? null;
        $source  = $stored['source'] ?? null;
        $at      = $stored['at'] ?? null;
        $ip      = $stored['ip'] ?? null;
        $time    = is_string($at) ? DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $at) : false;
        if (! is_string($wording) || ! is_string($source) || false === $time) {
            throw new UnexpectedValueException('A stored consent needs its wording, source and time');
        }

        return new self($wording, ConsentSource::from($source), $time, is_string($ip) ? $ip : null);
    }

    /**
     * @return array{wording: string, source: string, at: string, ip: string|null}
     */
    public function toArray(): array
    {
        return [
            'wording' => $this->wording,
            'source'  => $this->source->value,
            'at'      => $this->at->format(DateTimeInterface::ATOM),
            'ip'      => $this->ip,
        ];
    }
}
