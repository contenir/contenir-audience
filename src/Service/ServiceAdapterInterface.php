<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

use Contenir\Audience\Binding\Binding;

/**
 * One service's half of keeping a list in step: each method does one thing to a batch of people
 * on the binding's list, and answers with one outcome per person, in the order given. A batch is
 * never larger than the service's batch size.
 *
 * An adapter paces its own requests to the service's limits. It throws only when the connection
 * itself is refused; anything about one person is that person's outcome.
 *
 * @api
 */
interface ServiceAdapterInterface
{
    /**
     * Takes people out of the list's active members, in a way the service can undo.
     *
     * @param list<Person> $people
     *
     * @return list<Outcome>
     *
     * @throws ConnectionRefused
     */
    public function archive(Binding $binding, array $people): array;

    public function capabilities(): Capabilities;

    /**
     * @return list<RemoteField>
     *
     * @throws ConnectionRefused
     */
    public function fields(string $listId): array;

    /**
     * @return list<RemoteList>
     *
     * @throws ConnectionRefused
     */
    public function lists(): array;

    /**
     * Adds people to the list, or keeps them on it with their values brought up to date: each
     * person's status says whether they join at once or once they confirm.
     *
     * @param list<Person> $people
     *
     * @return list<Outcome>
     *
     * @throws ConnectionRefused
     */
    public function subscribe(Binding $binding, array $people): array;

    /**
     * Keeps people on the list, unsubscribed.
     *
     * @param list<Person> $people
     *
     * @return list<Outcome>
     *
     * @throws ConnectionRefused
     */
    public function unsubscribe(Binding $binding, array $people): array;

    /**
     * Brings the values of people already on the list up to date, never adding anyone and never
     * changing whether they're subscribed.
     *
     * @param list<Person> $people
     *
     * @return list<Outcome>
     *
     * @throws ConnectionRefused
     */
    public function update(Binding $binding, array $people): array;

    /**
     * @throws ConnectionRefused
     */
    public function verifyConnection(): Account;
}
