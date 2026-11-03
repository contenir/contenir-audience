<?php

declare(strict_types=1);

namespace Contenir\Audience\Testing;

use Closure;
use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Service\Account;
use Contenir\Audience\Service\Capabilities;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\DoubleOptIn;
use Contenir\Audience\Service\Outcome;
use Contenir\Audience\Service\Person;
use Contenir\Audience\Service\RemoteField;
use Contenir\Audience\Service\RemoteList;
use Contenir\Audience\Service\ServiceAdapterInterface;
use Override;

use function array_map;
use function md5;

/**
 * A service held in memory, for tests: it records each request, and answers each person as the
 * test says, or done with an id made from their email.
 *
 * @api
 *
 * @mago-expect lint:too-many-methods One method per method of the service interface, plus the test's controls.
 */
final class InMemoryService implements ServiceAdapterInterface
{
    /** @var list<array{string, string, list<Person>}> Method, binding and people, in the order asked */
    public array $calls = [];

    /** Why the next request is refused, if it is */
    public ?string $refuse = null;

    /** @var (Closure(string, Person): Outcome)|null */
    private ?Closure $answer = null;

    /**
     * @param list<RemoteList>                  $lists
     * @param array<string, list<RemoteField>>  $fields By list id
     */
    public function __construct(
        private Capabilities $capabilities = new Capabilities(100, DoubleOptIn::PerRequest),
        private array $lists = [],
        private array $fields = [],
    ) {}

    /**
     * Answers each person with what $answer gives for the method asked and that person.
     *
     * @param Closure(string, Person): Outcome $answer
     */
    public function answer(Closure $answer): void
    {
        $this->answer = $answer;
    }

    #[Override]
    public function archive(Binding $binding, array $people): array
    {
        return $this->ask('archive', $binding, $people);
    }

    #[Override]
    public function capabilities(): Capabilities
    {
        return $this->capabilities;
    }

    #[Override]
    public function fields(string $listId): array
    {
        $this->refuseIfTold();

        return $this->fields[$listId] ?? [];
    }

    #[Override]
    public function lists(): array
    {
        $this->refuseIfTold();

        return $this->lists;
    }

    #[Override]
    public function subscribe(Binding $binding, array $people): array
    {
        return $this->ask('subscribe', $binding, $people);
    }

    #[Override]
    public function unsubscribe(Binding $binding, array $people): array
    {
        return $this->ask('unsubscribe', $binding, $people);
    }

    #[Override]
    public function update(Binding $binding, array $people): array
    {
        return $this->ask('update', $binding, $people);
    }

    #[Override]
    public function verifyConnection(): Account
    {
        $this->refuseIfTold();

        return new Account('test', 'Test account');
    }

    /**
     * @param list<Person> $people
     *
     * @return list<Outcome>
     *
     * @throws ConnectionRefused
     */
    private function ask(string $method, Binding $binding, array $people): array
    {
        $this->refuseIfTold();
        $this->calls[] = [$method, $binding->name, $people];
        $answer        = $this->answer
            ?? static fn(string $method, Person $person): Outcome => Outcome::done(
                'remote-' . md5($person->email->value),
            );

        return array_map(static fn(Person $person): Outcome => $answer($method, $person), $people);
    }

    /**
     * @throws ConnectionRefused
     */
    private function refuseIfTold(): void
    {
        if (null !== $this->refuse) {
            throw ConnectionRefused::because($this->refuse);
        }
    }
}
