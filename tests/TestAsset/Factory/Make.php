<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\TestAsset\Factory;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\Binding\OnDelete;
use Contenir\Audience\Consent;
use Contenir\Audience\ConsentSource;
use Contenir\Audience\Email;
use Contenir\Audience\Outbox\Action;
use Contenir\Audience\Outbox\Message;
use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Audience\Outbox\OutboxState;
use DateTimeImmutable;

use function max;

/**
 * Builds the audience's values for tests, with sensible defaults a test overrides by name.
 */
final class Make
{
    public const string BINDING = 'newsletter';

    public const string EMAIL = 'ada@example.com';

    /**
     * @param array<array-key, mixed> $config
     */
    public static function binding(array $config = [], OnDelete $onDelete = OnDelete::Unsubscribe): Binding
    {
        return self::bindings($config, $onDelete)->get(self::BINDING);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    public static function bindings(array $config = [], OnDelete $onDelete = OnDelete::Unsubscribe): Bindings
    {
        return Bindings::fromConfig([self::BINDING => ['list' => 'list-1', ...$config]], $onDelete);
    }

    public static function consent(string $wording = 'Signing up to the newsletter'): Consent
    {
        return new Consent($wording, ConsentSource::Form, self::time(), '203.0.113.7');
    }

    /**
     * @param array<string, string|int|float|bool|null> $values
     */
    public static function message(
        Action $action = Action::Subscribe,
        array $values = ['Name' => 'Ada'],
        ?Consent $consent = null,
        string $email = self::EMAIL,
    ): Message {
        return new Message(self::BINDING, $action, Email::fromString($email), $values, $consent);
    }

    /**
     * @param array<string, string|int|float|bool|null> $values
     */
    public static function row(
        Action $action = Action::Subscribe,
        ?Consent $consent = null,
        array $values = ['Name' => 'Ada'],
        int $attempts = 0,
    ): OutboxRow {
        return new OutboxRow(
            1,
            self::BINDING,
            $action,
            Email::fromString(self::EMAIL),
            'hash',
            $values,
            $consent,
            OutboxState::Waiting,
            max(0, $attempts),
            self::time(),
            null,
            self::time(),
            self::time(),
        );
    }

    public static function time(string $at = '2026-11-05 09:00:00'): DateTimeImmutable
    {
        return new DateTimeImmutable("{$at} UTC");
    }
}
