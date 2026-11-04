<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Testing;

use Contenir\Audience\Email;
use Contenir\Audience\Service\Account;
use Contenir\Audience\Service\Capabilities;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\DoubleOptIn;
use Contenir\Audience\Service\Outcome;
use Contenir\Audience\Service\Person;
use Contenir\Audience\Service\RemoteField;
use Contenir\Audience\Service\RemoteList;
use Contenir\Audience\Testing\InMemoryService;
use Contenir\Audience\Testing\MutableClock;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function md5;

#[Group('unit')]
final class InMemoryServiceTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function methodProvider(): array
    {
        return [
            'subscribe'   => ['subscribe'],
            'update'      => ['update'],
            'unsubscribe' => ['unsubscribe'],
            'archive'     => ['archive'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedProvider(): array
    {
        return [
            'verify'    => ['verifyConnection'],
            'lists'     => ['lists'],
            'fields'    => ['fields'],
            'subscribe' => ['subscribe'],
        ];
    }

    #[Test]
    public function answersAsTheTestSays(): void
    {
        $service = new InMemoryService();
        $service->answer(static fn(string $method, Person $person): Outcome => Outcome::failed(
            "{$method} {$person->email->value}",
        ));

        static::assertEquals(
            [Outcome::failed('update ada@example.com')],
            $service->update(Make::binding(), [new Person(Email::fromString(Make::EMAIL))]),
        );
    }

    #[Test]
    public function answersWhatItWasSetUpWith(): void
    {
        $lists   = [new RemoteList('list-1', 'Newsletter')];
        $fields  = ['list-1' => [new RemoteField('FNAME', 'First name', 'text', required: true)]];
        $service = new InMemoryService(new Capabilities(500, DoubleOptIn::PerList, tags: true), $lists, $fields);

        static::assertEquals(
            [
                new Capabilities(500, DoubleOptIn::PerList, tags: true),
                new Account('test', 'Test account'),
                $lists,
                $fields['list-1'],
                [],
            ],
            [
                $service->capabilities(),
                $service->verifyConnection(),
                $service->lists(),
                $service->fields('list-1'),
                $service->fields('list-2'),
            ],
        );
    }

    #[Test]
    public function movesItsClockByHand(): void
    {
        $clock = new MutableClock(Make::time());

        $clock->advance('+90 seconds');

        static::assertEquals(Make::time('2026-11-05 09:01:30'), $clock->now());
    }

    #[DataProvider('methodProvider')]
    #[Test]
    public function recordsEachRequestAndAnswersDoneWithAnIdFromTheEmail(string $method): void
    {
        $service = new InMemoryService();
        $person  = new Person(Email::fromString(Make::EMAIL));

        $outcomes = $service->{$method}(Make::binding(), [$person]);

        static::assertEquals(
            [[Outcome::done('remote-' . md5(Make::EMAIL))], [[$method, 'newsletter', [$person]]]],
            [$outcomes, $service->calls],
        );
    }

    #[Test]
    public function recordsNoRequestItRefused(): void
    {
        $service         = new InMemoryService();
        $service->refuse = '401';

        try {
            $service->subscribe(Make::binding(), []);
        } catch (ConnectionRefused) {
            static::assertSame([], $service->calls);

            return;
        }

        static::fail('The request was not refused');
    }

    #[DataProvider('refusedProvider')]
    #[Test]
    public function refusesEveryRequestWhenTold(string $method): void
    {
        $service         = new InMemoryService();
        $service->refuse = '401';

        $this->expectException(ConnectionRefused::class);
        $this->expectExceptionMessage('The service refused the connection: 401');

        match ($method) {
            'verifyConnection' => $service->verifyConnection(),
            'lists'            => $service->lists(),
            'fields'           => $service->fields('list-1'),
            default            => $service->{$method}(Make::binding(), []),
        };
    }

    #[Test]
    public function startsItsClockOnTheFifthOfNovember(): void
    {
        static::assertEquals(Make::time(), (new MutableClock())->now());
    }

    #[Test]
    public function takesAHundredPeopleARequestAndConfirmationPerRequestUnlessTold(): void
    {
        static::assertEquals(new Capabilities(100, DoubleOptIn::PerRequest), (new InMemoryService())->capabilities());
    }
}
