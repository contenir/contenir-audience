<?php

declare(strict_types=1);

namespace Contenir\Audience;

use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\Exception\InvalidEmail;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Exception\UnknownBinding;
use Contenir\Audience\Outbox\Action;
use Contenir\Audience\Outbox\Message;
use Psr\Clock\ClockInterface;

use function array_filter;
use function is_scalar;
use function is_string;

/**
 * Where sign-ups and record changes go in: each becomes a message in the outbox, for the outbox
 * worker to send. Nothing here calls a service, so it never slows a page or a save.
 *
 * @api
 */
final readonly class Audience
{
    public function __construct(
        private Bindings $bindings,
        private Stores $stores,
        private ClockInterface $clock,
    ) {}

    /**
     * A deleted record: the binding's on-delete setting decides what the service is asked to do.
     *
     * @throws UnknownBinding
     * @throws InvalidEmail
     * @throws StorageException
     */
    public function remove(string $binding, string $email): int
    {
        $address = Email::fromString($email);
        $this->bindings->get($binding);

        return $this->stores->outbox->add(
            new Message($binding, Action::Remove, $address),
            $this->stores->hasher->email($address),
            $this->clock->now(),
        );
    }

    /**
     * A person joining the binding's list with $consent, from a form or a record.
     *
     * @param array<string, mixed> $values The form's or record's fields, the email among them
     *
     * @throws UnknownBinding
     * @throws InvalidEmail
     * @throws StorageException
     */
    public function subscribe(string $binding, array $values, Consent $consent): int
    {
        return $this->put($binding, Action::Subscribe, $values, $consent);
    }

    /**
     * A record whose fields changed, for a person who may already be on the binding's list.
     *
     * @param array<string, mixed> $values
     *
     * @throws UnknownBinding
     * @throws InvalidEmail
     * @throws StorageException
     */
    public function update(string $binding, array $values): int
    {
        return $this->put($binding, Action::Update, $values);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @throws UnknownBinding
     * @throws InvalidEmail
     * @throws StorageException
     *
     * @mago-expect analysis:mixed-assignment The form's or record's values are untyped; the email is checked as text.
     */
    private function put(string $name, Action $action, array $values, ?Consent $consent = null): int
    {
        $binding = $this->bindings->get($name);
        $email   = $values[$binding->emailField] ?? null;
        $address = Email::fromString(is_string($email) ? $email : '');

        /** @var array<string, string|int|float|bool|null> $plain */
        $plain = array_filter($values, static fn(mixed $value): bool => null === $value || is_scalar($value));

        return $this->stores->outbox->add(
            new Message($name, $action, $address, $binding->fields->apply($plain), $consent),
            $this->stores->hasher->email($address),
            $this->clock->now(),
        );
    }
}
