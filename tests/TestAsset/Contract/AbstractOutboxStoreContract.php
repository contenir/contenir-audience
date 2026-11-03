<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\TestAsset\Contract;

use Contenir\Audience\Email;
use Contenir\Audience\Outbox\Action;
use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Audience\Outbox\OutboxState;
use Contenir\Audience\Outbox\OutboxStoreInterface;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * What every outbox store does, whatever holds it: each store's test extends this once.
 */
abstract class AbstractOutboxStoreContract extends TestCase
{
    /**
     * @param list<OutboxRow> $rows
     *
     * @return list<int>
     */
    private static function ids(array $rows): array
    {
        return array_map(static fn(OutboxRow $row): int => $row->id, $rows);
    }

    #[Test]
    public function clearsWhatItKeptAboutAPersonOnceTheirMessageIsDone(): void
    {
        $store = $this->store();
        $id    = $store->add(Make::message(consent: Make::consent()), 'a', Make::time());

        $store->done($id, Make::time('2026-11-05 09:01:00'));
        $row = $store->find($id);

        static::assertEquals(
            [OutboxState::Done, null, [], null, 'a', Make::time('2026-11-05 09:01:00'), []],
            [
                $row?->state,
                $row?->email,
                $row?->values,
                $row?->consent,
                $row?->emailHash,
                $row?->updatedAt,
                self::ids($store->due(10, Make::time('2026-11-06'))),
            ],
        );
    }

    #[Test]
    public function countsEachAttemptAndWhyItFailed(): void
    {
        $store = $this->store();
        $id    = $store->add(Make::message(), 'a', Make::time());

        $store->retry($id, Make::time('2026-11-05 09:01:00'), 'busy', Make::time());
        $store->retry($id, Make::time('2026-11-05 09:06:00'), 'still busy', Make::time('2026-11-05 09:01:00'));
        $row = $store->find($id);

        static::assertEquals(
            [OutboxState::Waiting, 2, Make::time('2026-11-05 09:06:00'), 'still busy'],
            [$row?->state, $row?->attempts, $row?->nextAttemptAt, $row?->lastError],
        );
    }

    #[Test]
    public function countsTheMessagesInEveryState(): void
    {
        $store = $this->store();
        $store->add(Make::message(), 'a', Make::time());
        $store->add(Make::message(), 'b', Make::time());
        $store->fail($store->add(Make::message(), 'c', Make::time()), 'x', Make::time());

        static::assertSame(['waiting' => 2, 'done' => 0, 'failed' => 1, 'dead' => 0], $store->counts());
    }

    #[Test]
    public function findsEachMessageByItsId(): void
    {
        $store = $this->store();
        $store->add(Make::message(), 'a', Make::time());
        $id = $store->add(Make::message(), 'b', Make::time());

        static::assertSame('b', $store->find($id)?->emailHash);
    }

    #[Test]
    public function findsNoMessageThatIsNotThere(): void
    {
        static::assertNull($this->store()->find(404));
    }

    #[Test]
    public function givesNoMessageBeforeItIsDue(): void
    {
        $store = $this->store();
        $id    = $store->add(Make::message(), 'a', Make::time());
        $store->retry($id, Make::time('2026-11-05 09:05:00'), 'busy', Make::time());

        static::assertSame(
            [[], [$id]],
            [
                self::ids($store->due(10, Make::time('2026-11-05 09:04:59'))),
                self::ids($store->due(10, Make::time('2026-11-05 09:05:00'))),
            ],
        );
    }

    #[Test]
    public function givesTheWaitingMessagesDueOldestFirstUpToItsLimit(): void
    {
        $store  = $this->store();
        $later  = $store->add(Make::message(), 'a', Make::time('2026-11-05 09:00:02'));
        $first  = $store->add(Make::message(), 'b', Make::time('2026-11-05 09:00:00'));
        $second = $store->add(Make::message(), 'c', Make::time('2026-11-05 09:00:01'));
        $store->add(Make::message(), 'd', Make::time('2026-11-05 09:00:03'));

        static::assertSame([$first, $second, $later], self::ids($store->due(3, Make::time('2026-11-05 09:00:03'))));
    }

    #[Test]
    public function keepsAFailedMessageWithItsDetailsForTheSyncScreen(): void
    {
        $store = $this->store();
        $id    = $store->add(Make::message(), 'a', Make::time());

        $store->fail($id, 'Invalid merge field', Make::time());
        $row = $store->find($id);

        static::assertEquals(
            [OutboxState::Failed, 'Invalid merge field', Make::EMAIL, ['Name' => 'Ada']],
            [$row?->state, $row?->lastError, $row?->email?->value, $row?->values],
        );
    }

    #[Test]
    public function keepsAMessageAsItWasPut(): void
    {
        $store = $this->store();
        $id    = $store->add(Make::message(consent: Make::consent()), 'hash-1', Make::time());

        static::assertEquals(
            new OutboxRow(
                $id,
                'newsletter',
                Action::Subscribe,
                Email::fromString(Make::EMAIL),
                'hash-1',
                ['Name' => 'Ada'],
                Make::consent(),
                OutboxState::Waiting,
                0,
                Make::time(),
                null,
                Make::time(),
                Make::time(),
            ),
            $store->find($id),
        );
    }

    #[Test]
    public function keepsAMessageChangedExactlyAtItsPruningTime(): void
    {
        $store  = $this->store();
        $done   = $store->add(Make::message(), 'a', Make::time());
        $failed = $store->add(Make::message(), 'b', Make::time());
        $store->done($done, Make::time('2026-11-02'));
        $store->fail($failed, 'x', Make::time('2026-10-02'));

        static::assertSame(0, $store->prune(Make::time('2026-11-02'), Make::time('2026-10-02')));
    }

    #[Test]
    public function listsTheMessagesInAStateMostRecentlyChangedFirst(): void
    {
        $store  = $this->store();
        $first  = $store->add(Make::message(), 'a', Make::time());
        $second = $store->add(Make::message(), 'b', Make::time());
        $third  = $store->add(Make::message(), 'c', Make::time());
        $other  = $store->add(Make::message(), 'd', Make::time());
        $store->fail($first, 'x', Make::time('2026-11-05 09:02:00'));
        $store->fail($second, 'x', Make::time('2026-11-05 09:03:00'));
        $store->fail($third, 'x', Make::time('2026-11-05 09:01:00'));
        $store->dead($other, 'x', Make::time('2026-11-05 09:04:00'));

        static::assertSame(
            [[$second, $first, $third], [$second]],
            [self::ids($store->inState(OutboxState::Failed, 10)), self::ids($store->inState(OutboxState::Failed, 1))],
        );
    }

    #[Test]
    public function marksAMessageWhoseRetriesRanOutAsDead(): void
    {
        $store = $this->store();
        $id    = $store->add(Make::message(), 'a', Make::time());

        $store->dead($id, '503', Make::time());

        static::assertSame([OutboxState::Dead, '503'], [$store->find($id)?->state, $store->find($id)?->lastError]);
    }

    #[Test]
    public function neverPrunesAWaitingMessage(): void
    {
        $store = $this->store();
        $id    = $store->add(Make::message(), 'a', Make::time('2020-01-01'));

        static::assertSame([0, [$id]], [
            $store->prune(Make::time(), Make::time()),
            self::ids($store->due(10, Make::time())),
        ]);
    }

    #[Test]
    public function prunesDoneMessagesAndFailedOnesEachAfterTheirOwnTime(): void
    {
        $store  = $this->store();
        $done   = $store->add(Make::message(), 'a', Make::time());
        $recent = $store->add(Make::message(), 'b', Make::time());
        $failed = $store->add(Make::message(), 'c', Make::time());
        $dead   = $store->add(Make::message(), 'd', Make::time());
        $old    = $store->add(Make::message(), 'e', Make::time());
        $store->done($done, Make::time('2026-11-01'));
        $store->done($recent, Make::time('2026-11-04'));
        $store->fail($failed, 'x', Make::time('2026-11-01'));
        $store->dead($dead, 'x', Make::time('2026-10-01'));
        $store->fail($old, 'x', Make::time('2026-09-01'));

        $pruned = $store->prune(Make::time('2026-11-02'), Make::time('2026-10-02'));

        static::assertSame(
            [3, [$recent], [$failed], []],
            [
                $pruned,
                self::ids($store->inState(OutboxState::Done, 10)),
                self::ids($store->inState(OutboxState::Failed, 10)),
                self::ids($store->inState(OutboxState::Dead, 10)),
            ],
        );
    }

    #[Test]
    public function putsAFailedMessageBackToWaitWithAFullSetOfRetries(): void
    {
        $store = $this->store();
        $id    = $store->add(Make::message(), 'a', Make::time());
        $store->retry($id, Make::time('2026-11-05 09:01:00'), 'busy', Make::time());
        $store->dead($id, 'busy', Make::time('2026-11-05 09:01:00'));

        $store->requeue($id, Make::time('2026-11-05 10:00:00'));
        $row = $store->find($id);

        static::assertEquals(
            [OutboxState::Waiting, 0, Make::time('2026-11-05 10:00:00'), null, [$id]],
            [
                $row?->state,
                $row?->attempts,
                $row?->nextAttemptAt,
                $row?->lastError,
                self::ids($store->due(10, Make::time('2026-11-05 10:00:00'))),
            ],
        );
    }

    #[Test]
    public function removesAMessage(): void
    {
        $store = $this->store();
        $id    = $store->add(Make::message(), 'a', Make::time());

        $store->delete($id);

        static::assertNull($store->find($id));
    }

    #[Test]
    public function removesOnlyTheMessageItIsGiven(): void
    {
        $store = $this->store();
        $kept  = $store->add(Make::message(), 'a', Make::time());
        $store->delete($store->add(Make::message(), 'b', Make::time()));

        static::assertSame('a', $store->find($kept)?->emailHash);
    }

    abstract protected function store(): OutboxStoreInterface;
}
