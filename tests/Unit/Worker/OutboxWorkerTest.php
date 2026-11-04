<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Worker;

use Contenir\Audience\Backoff;
use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\Consent;
use Contenir\Audience\Email;
use Contenir\Audience\Outbox\Action;
use Contenir\Audience\Outbox\OutboxState;
use Contenir\Audience\Service\Capabilities;
use Contenir\Audience\Service\DoubleOptIn;
use Contenir\Audience\Service\Outcome;
use Contenir\Audience\Service\Person;
use Contenir\Audience\Service\PersonStatus;
use Contenir\Audience\Subject\Subject;
use Contenir\Audience\Suppression\SuppressionReason;
use Contenir\Audience\Testing\InMemoryService;
use Contenir\Audience\Testing\InMemoryStores;
use Contenir\Audience\Testing\MutableClock;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use Contenir\Audience\Tests\TestAsset\Log\RecordingLogger;
use Contenir\Audience\Tests\TestAsset\Service\SilentService;
use Contenir\Audience\Worker\DrainReport;
use Contenir\Audience\Worker\OutboxWorker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use RuntimeException;

use function array_keys;
use function array_map;
use function count;
use function md5;

#[Group('unit')]
final class OutboxWorkerTest extends TestCase
{
    private InMemoryStores $memory;

    private MutableClock $clock;

    private InMemoryService $service;

    private string $hash;

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function deleteProvider(): array
    {
        return [
            'unsubscribe' => [['on_delete' => 'unsubscribe'], 'unsubscribe'],
            'archive'     => [['on_delete' => 'archive'], 'archive'],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('deleteProvider')]
    #[Test]
    public function doesWhatTheBindingSaysWhenARecordIsDeleted(array $config, string $method): void
    {
        $this->sent(['Name' => 'Ada']);
        $this->put(Action::Remove, values: []);

        $this->worker(Make::bindings($config))->drain($this->service);

        static::assertSame(
            [$method, false],
            [$this->service->calls[0][0], $this->memory->subjects->find('newsletter', $this->hash)?->subscribed],
        );
    }

    #[Test]
    public function failsAMessageForABindingThatHasGone(): void
    {
        $id = $this->put(Action::Subscribe, Make::consent());

        $report = $this->worker(Bindings::fromConfig([]))->drain($this->service);

        static::assertEquals(
            [new DrainReport(0, 0, 0, 1, 0, true, null), 'There is no audience binding "newsletter"'],
            [$report, $this->memory->outbox->row($id)->lastError],
        );
    }

    #[Test]
    public function failsAMessageTheServiceRefuses(): void
    {
        $this->service->answer(static fn(): Outcome => Outcome::failed('Invalid merge field'));
        $id = $this->put(Action::Subscribe, Make::consent());

        $report = $this->worker()->drain($this->service);

        static::assertEquals(
            [new DrainReport(0, 0, 0, 1, 0, true, null), OutboxState::Failed, 'Invalid merge field', []],
            [
                $report,
                $this->memory->outbox->row($id)->state,
                $this->memory->outbox->row($id)->lastError,
                $this->memory->subjects->subjects,
            ],
        );
    }

    #[Test]
    public function givesUpOnceTheRetriesRunOut(): void
    {
        $this->service->answer(static fn(): Outcome => Outcome::retry('503'));
        $id = $this->put(Action::Subscribe, Make::consent());

        $this->worker(backoff: new Backoff([60]))->drain($this->service);
        $this->clock->advance('+1 minute');
        $report = $this->worker(backoff: new Backoff([60]))->drain($this->service);

        static::assertEquals(
            [new DrainReport(0, 0, 0, 0, 1, true, null), OutboxState::Dead, '503'],
            [$report, $this->memory->outbox->row($id)->state, $this->memory->outbox->row($id)->lastError],
        );
    }

    #[Test]
    public function goesOnToTheNextBatchAfterARequestFails(): void
    {
        $this->service = new InMemoryService(new Capabilities(1, DoubleOptIn::PerRequest));
        $this->service->answer(static fn(string $method, Person $person): Outcome => Make::EMAIL
            === $person->email->value
                ? throw new RuntimeException('Connection timed out')
                : Outcome::done());
        $this->put(Action::Subscribe, Make::consent());
        $this->put(Action::Subscribe, Make::consent(), email: 'grace@example.com');

        static::assertEquals(new DrainReport(1, 0, 1, 0, 0, true, null), $this->worker()->drain($this->service));
    }

    #[Test]
    public function keepsDoneMessagesForAWeek(): void
    {
        $done = $this->put(Action::Subscribe, Make::consent());
        $this->memory->outbox->done($done, Make::time());
        $this->clock->advance('+6 days');

        static::assertSame(0, $this->worker()->prune());
    }

    #[Test]
    public function keepsSendingForFortySecondsUnlessTold(): void
    {
        $this->service = new InMemoryService(new Capabilities(1, DoubleOptIn::PerRequest));
        $this->service->answer(function (): Outcome {
            $this->clock->advance('+39 seconds');

            return Outcome::done();
        });
        $this->put(Action::Subscribe, Make::consent());
        $this->put(Action::Subscribe, Make::consent(), email: 'grace@example.com');

        static::assertSame(2, $this->worker()->drain($this->service)->sent);
    }

    #[Test]
    public function keepsSendingUntilItsTimeIsUp(): void
    {
        $this->service = new InMemoryService(new Capabilities(1, DoubleOptIn::PerRequest));
        $this->service->answer(function (): Outcome {
            $this->clock->advance('+39 seconds');

            return Outcome::done();
        });
        $this->put(Action::Subscribe, Make::consent());
        $this->put(Action::Subscribe, Make::consent(), email: 'grace@example.com');

        static::assertSame(2, $this->worker()->drain($this->service, seconds: 40)->sent);
    }

    #[Test]
    public function keepsTheServicesIdWhenAnUpdateGivesNone(): void
    {
        $this->sent(['Name' => 'Ada']);
        $this->service->answer(static fn(string $method, Person $person): Outcome => Outcome::done());
        $this->put(Action::Update, values: ['Name' => 'Ada Lovelace']);

        $this->worker()->drain($this->service);

        static::assertSame('r1', $this->memory->subjects->find('newsletter', $this->hash)?->remoteId);
    }

    #[Test]
    public function keepsTheServicesNewIdForSomeone(): void
    {
        $this->sent(['Name' => 'Ada']);
        $this->service->answer(static fn(string $method, Person $person): Outcome => Outcome::done('r2'));
        $this->put(Action::Update, values: ['Name' => 'Ada Lovelace']);

        $this->worker()->drain($this->service);

        static::assertSame('r2', $this->memory->subjects->find('newsletter', $this->hash)?->remoteId);
    }

    #[Test]
    public function keepsWhatItSentAndTheConsentThatAllowedIt(): void
    {
        $this->put(Action::Subscribe, Make::consent());

        $this->worker()->drain($this->service);

        static::assertEquals(
            [
                new Subject(
                    'newsletter',
                    $this->hash,
                    'remote-' . md5(Make::EMAIL),
                    $this->memory->hasher->values(['Name' => 'Ada']),
                    true,
                    Make::time(),
                ),
                [['newsletter', $this->hash, Make::consent()]],
            ],
            [$this->memory->subjects->find('newsletter', $this->hash), $this->memory->consents->records],
        );
    }

    #[Test]
    public function leavesTheRestForNextTimeOnceItsTimeIsUp(): void
    {
        $this->service = new InMemoryService(new Capabilities(1, DoubleOptIn::PerRequest));
        $this->service->answer(function (): Outcome {
            $this->clock->advance('+40 seconds');

            return Outcome::done();
        });
        $this->put(Action::Subscribe, Make::consent());
        $second = $this->put(Action::Subscribe, Make::consent(), email: 'grace@example.com');

        $report = $this->worker()->drain($this->service);

        static::assertEquals(
            [new DrainReport(1, 0, 0, 0, 0, false, null), OutboxState::Waiting],
            [$report, $this->memory->outbox->row($second)->state],
        );
    }

    #[Test]
    public function logsAMessageTheServiceRefused(): void
    {
        $logger = new RecordingLogger();
        $this->service->answer(static fn(): Outcome => Outcome::failed('Invalid merge field'));
        $id = $this->put(Action::Subscribe, Make::consent());

        $this->worker(logger: $logger)->drain($this->service);

        static::assertSame(
            [[
                LogLevel::NOTICE,
                '{binding}: message {id} was refused: {reason}',
                ['binding' => 'newsletter', 'id' => $id, 'reason' => 'Invalid merge field'],
            ]],
            $logger->entries,
        );
    }

    #[Test]
    public function logsARequestThatFailed(): void
    {
        $logger = new RecordingLogger();
        $this->service->answer(static fn(): Outcome => throw new RuntimeException('Connection timed out'));
        $this->put(Action::Subscribe, Make::consent());

        $this->worker(logger: $logger)->drain($this->service);

        static::assertSame(
            [[
                LogLevel::WARNING,
                '{binding}: sending {count} failed: {reason}',
                ['binding' => 'newsletter', 'count' => 1, 'reason' => 'Connection timed out'],
            ]],
            $logger->entries,
        );
    }

    #[Test]
    public function logsWhySendingPaused(): void
    {
        $logger                = new RecordingLogger();
        $this->service->refuse = '401 API key invalid';
        $this->put(Action::Subscribe, Make::consent());

        $this->worker(logger: $logger)->drain($this->service);

        static::assertSame(
            [[
                LogLevel::WARNING,
                'Sending paused: {reason}',
                ['reason' => 'The service refused the connection: 401 API key invalid'],
            ]],
            $logger->entries,
        );
    }

    #[Test]
    public function looksAtFiveHundredMessagesUnlessTold(): void
    {
        for ($i = 0; $i < 501; $i++) {
            $this->put(Action::Subscribe, Make::consent(), email: "person{$i}@example.com");
        }

        static::assertSame(500, $this->worker()->drain($this->service)->sent);
    }

    #[Test]
    public function looksAtNoMoreMessagesThanItIsAllowed(): void
    {
        $this->put(Action::Subscribe, Make::consent());
        $this->put(Action::Subscribe, Make::consent(), email: 'grace@example.com');

        $report = $this->worker()->drain($this->service, limit: 1);

        static::assertSame(1, $report->sent);
    }

    #[Test]
    public function marksSomeoneSubscribedAtOnceAsOnTheList(): void
    {
        $this->service = new InMemoryService(new Capabilities(100, DoubleOptIn::PerList));
        $this->put(Action::Subscribe, Make::consent());

        $this->worker()->drain($this->service);

        static::assertTrue($this->memory->subjects->find('newsletter', $this->hash)?->subscribed);
    }

    #[Test]
    public function marksSomeoneUnsubscribedWhenTheirRecordIsDeleted(): void
    {
        $this->sent(['Name' => 'Ada']);
        $this->put(Action::Remove, values: []);

        $this->worker()->drain($this->service);

        static::assertFalse($this->memory->subjects->find('newsletter', $this->hash)?->subscribed);
    }

    #[Test]
    public function neverSubscribesSomeoneWhoComplained(): void
    {
        $this->memory->suppression->suppress('newsletter', $this->hash, SuppressionReason::Complaint, Make::time());
        $id = $this->put(Action::Subscribe, Make::consent());

        $report = $this->worker()->drain($this->service);

        static::assertEquals(
            [new DrainReport(0, 1, 0, 0, 0, true, null), [], OutboxState::Done],
            [$report, $this->service->calls, $this->memory->outbox->row($id)->state],
        );
    }

    #[Test]
    public function pausesAndLeavesEverythingWaitingWhenTheConnectionIsRefused(): void
    {
        $this->service->refuse = '401 API key invalid';
        $id                    = $this->put(Action::Subscribe, Make::consent());

        $report = $this->worker()->drain($this->service);
        $row    = $this->memory->outbox->row($id);

        static::assertEquals(
            [
                new DrainReport(0, 0, 0, 0, 0, false, 'The service refused the connection: 401 API key invalid'),
                true,
                OutboxState::Waiting,
                0,
            ],
            [$report, $report->isPaused(), $row->state, $row->attempts],
        );
    }

    #[Test]
    public function prunesFailedMessagesAfter30Days(): void
    {
        $failed = $this->put(Action::Subscribe, Make::consent());
        $this->memory->outbox->fail($failed, 'Refused', Make::time());
        $this->clock->advance('+31 days');

        static::assertSame(1, $this->worker()->prune());
    }

    #[Test]
    public function prunesMessagesKeptPastTheirTime(): void
    {
        $done   = $this->put(Action::Subscribe, Make::consent());
        $failed = $this->put(Action::Subscribe, Make::consent(), email: 'grace@example.com');
        $this->memory->outbox->done($done, Make::time());
        $this->memory->outbox->fail($failed, 'Refused', Make::time());
        $this->clock->advance('+8 days');

        $pruned = $this->worker()->prune();

        static::assertSame([1, [$failed]], [$pruned, array_keys($this->memory->outbox->rows)]);
    }

    #[Test]
    public function recordsNoConsentForAChange(): void
    {
        $this->sent(['Name' => 'Ada']);
        $this->put(Action::Update, values: ['Name' => 'Ada Lovelace']);

        $this->worker()->drain($this->service);

        static::assertSame([], $this->memory->consents->records);
    }

    #[Test]
    public function reportsADrainThatWasNotPaused(): void
    {
        static::assertFalse($this->worker()->drain($this->service)->isPaused());
    }

    #[Test]
    public function sendsAChangeForSomeoneAlreadyOnTheList(): void
    {
        $this->sent(['Name' => 'Ada']);
        $this->put(Action::Update, values: ['Name' => 'Ada Lovelace']);

        $this->worker()->drain($this->service);

        static::assertSame(
            ['update', true],
            [$this->service->calls[0][0], $this->memory->subjects->find('newsletter', $this->hash)?->subscribed],
        );
    }

    #[Test]
    public function sendsEachKindOfChangeInARequestOfItsOwn(): void
    {
        $this->memory->subjects->save(new Subject(
            'newsletter',
            $this->memory->hasher->email(Email::fromString('grace@example.com')),
            'r2',
            'v',
            true,
            Make::time(),
        ));
        $this->put(Action::Subscribe, Make::consent());
        $this->put(Action::Update, values: ['Name' => 'Grace'], email: 'grace@example.com');

        $this->worker()->drain($this->service);

        static::assertSame(
            ['subscribe', 'update'],
            array_map(static fn(array $call): string => $call[0], $this->service->calls),
        );
    }

    #[Test]
    public function sendsNoChangeForSomeoneTakenOffTheList(): void
    {
        $this->sent(['Name' => 'Ada'], subscribed: false);
        $this->put(Action::Update, values: ['Name' => 'Ada Lovelace']);

        $report = $this->worker()->drain($this->service);

        static::assertEquals([new DrainReport(0, 1, 0, 0, 0, true, null), []], [$report, $this->service->calls]);
    }

    #[Test]
    public function sendsNoMoreThanTheServiceTakesInOneRequest(): void
    {
        $this->service = new InMemoryService(new Capabilities(2, DoubleOptIn::PerRequest));
        foreach (['a', 'b', 'c'] as $name) {
            $this->put(Action::Subscribe, Make::consent(), email: "{$name}@example.com");
        }

        $this->worker()->drain($this->service);

        static::assertSame([2, 1], array_map(static fn(array $call): int => count($call[2]), $this->service->calls));
    }

    #[Test]
    public function sendsNothingMoreOnceTheConnectionIsRefused(): void
    {
        $this->service = new InMemoryService(new Capabilities(1, DoubleOptIn::PerRequest));
        $this->service->answer(function (string $method, Person $person): Outcome {
            $this->service->refuse = '401';

            return Outcome::done();
        });
        $this->put(Action::Subscribe, Make::consent());
        $this->put(Action::Subscribe, Make::consent(), email: 'grace@example.com');
        $this->put(Action::Subscribe, Make::consent(), email: 'alan@example.com');

        $report = $this->worker()->drain($this->service);

        static::assertSame([1, 1], [$report->sent, count($this->service->calls)]);
    }

    #[Test]
    public function sendsNothingWhenTheValuesHaveNotChanged(): void
    {
        $this->sent(['Name' => 'Ada']);
        $this->put(Action::Update, values: ['Name' => 'Ada']);

        $report = $this->worker()->drain($this->service);

        static::assertEquals([new DrainReport(0, 1, 0, 0, 0, true, null), []], [$report, $this->service->calls]);
    }

    #[Test]
    public function subscribesANewPersonWhoConsentedPendingTheirConfirmation(): void
    {
        $id = $this->put(Action::Subscribe, Make::consent());

        $report = $this->worker()->drain($this->service);

        static::assertEquals(
            [
                new DrainReport(1, 0, 0, 0, 0, true, null),
                [[
                    'subscribe',
                    'newsletter',
                    [new Person(
                        Email::fromString(Make::EMAIL),
                        ['Name' => 'Ada'],
                        PersonStatus::Pending,
                        Make::consent(),
                    )],
                ]],
                OutboxState::Done,
            ],
            [$report, $this->service->calls, $this->memory->outbox->row($id)->state],
        );
    }

    #[Test]
    public function subscribesAtOnceWhereTheListDecidesOnConfirmation(): void
    {
        $this->service = new InMemoryService(new Capabilities(100, DoubleOptIn::PerList));
        $this->put(Action::Subscribe, Make::consent());

        $this->worker()->drain($this->service);

        static::assertSame(PersonStatus::Subscribed, $this->service->calls[0][2][0]->status);
    }

    #[Test]
    public function suppressesSomeoneTheServiceHasErased(): void
    {
        $this->service->answer(static fn(): Outcome => Outcome::forgotten('Member was permanently deleted'));
        $id = $this->put(Action::Subscribe, Make::consent());

        $this->worker()->drain($this->service);

        static::assertSame(
            [SuppressionReason::Forgotten, OutboxState::Failed],
            [$this->memory->suppression->reason('newsletter', $this->hash), $this->memory->outbox->row($id)->state],
        );
    }

    #[Test]
    public function takesBackSomeoneWhoUnsubscribedAndAgreedAgain(): void
    {
        $this->memory->suppression->suppress('newsletter', $this->hash, SuppressionReason::Unsubscribed, Make::time());
        $this->put(Action::Subscribe, Make::consent());

        $this->worker()->drain($this->service);

        static::assertSame(
            [true, null],
            [
                $this->service->calls[0][2][0]->resubscribe,
                $this->memory->suppression->reason('newsletter', $this->hash),
            ],
        );
    }

    #[Test]
    public function triesAgainForSomeoneTheServiceGaveNoOutcomeFor(): void
    {
        $id = $this->put(Action::Subscribe, Make::consent());

        $this->worker()->drain(new SilentService());

        static::assertSame(
            [OutboxState::Waiting, 'The service gave no outcome for this person'],
            [$this->memory->outbox->row($id)->state, $this->memory->outbox->row($id)->lastError],
        );
    }

    #[Test]
    public function triesAgainLaterWhenTheServiceIsBusy(): void
    {
        $this->service->answer(static fn(): Outcome => Outcome::retry('429 Too Many Requests', retryAfter: 120));
        $id = $this->put(Action::Subscribe, Make::consent());

        $report = $this->worker()->drain($this->service);
        $row    = $this->memory->outbox->row($id);

        static::assertEquals(
            [
                new DrainReport(0, 0, 1, 0, 0, true, null),
                OutboxState::Waiting,
                1,
                Make::time('2026-11-05 09:02:00'),
                '429 Too Many Requests',
            ],
            [$report, $row->state, $row->attempts, $row->nextAttemptAt, $row->lastError],
        );
    }

    #[Test]
    public function triesTheWholeBatchAgainWhenTheRequestFails(): void
    {
        $this->service->answer(static fn(): Outcome => throw new RuntimeException('Connection timed out'));
        $first  = $this->put(Action::Subscribe, Make::consent());
        $second = $this->put(Action::Subscribe, Make::consent(), email: 'grace@example.com');

        $report = $this->worker()->drain($this->service);

        static::assertEquals(
            [new DrainReport(0, 0, 2, 0, 0, true, null), 'Connection timed out', 'Connection timed out'],
            [$report, $this->memory->outbox->row($first)->lastError, $this->memory->outbox->row($second)->lastError],
        );
    }

    protected function setUp(): void
    {
        $this->memory  = new InMemoryStores();
        $this->clock   = new MutableClock(Make::time());
        $this->service = new InMemoryService();
        $this->hash    = $this->memory->hasher->email(Email::fromString(Make::EMAIL));
    }

    /**
     * @param array<string, string|int|float|bool|null> $values
     */
    private function put(
        Action $action,
        ?Consent $consent = null,
        array $values = ['Name' => 'Ada'],
        string $email = Make::EMAIL,
    ): int {
        $message = Make::message($action, $values, $consent, $email);

        return $this->memory->outbox->add($message, $this->memory->hasher->email($message->email), $this->clock->now());
    }

    /**
     * @param array<string, string|int|float|bool|null> $values
     */
    private function sent(array $values, bool $subscribed = true): void
    {
        $this->memory->subjects->save(new Subject(
            'newsletter',
            $this->hash,
            'r1',
            $this->memory->hasher->values($values),
            $subscribed,
            Make::time(),
        ));
    }

    private function worker(
        ?Bindings $bindings = null,
        Backoff $backoff = new Backoff(),
        LoggerInterface $logger = new NullLogger(),
    ): OutboxWorker {
        return new OutboxWorker($bindings ?? Make::bindings(), $this->memory->stores, $this->clock, $logger, $backoff);
    }
}
