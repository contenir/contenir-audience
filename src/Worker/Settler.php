<?php

declare(strict_types=1);

namespace Contenir\Audience\Worker;

use Contenir\Audience\Backoff;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Audience\Policy\Decision;
use Contenir\Audience\Service\Outcome;
use Contenir\Audience\Service\OutcomeKind;
use Contenir\Audience\Stores;
use Contenir\Audience\Subject\Subject;
use Contenir\Audience\Suppression\SuppressionReason;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

use function count;

/**
 * Records what became of each message a service was sent: done, with the person's sync state
 * and consent kept; tried again later, until the retries run out; or failed.
 *
 * @internal
 */
final readonly class Settler
{
    public function __construct(
        private Stores $stores,
        private Backoff $backoff,
        private LoggerInterface $logger,
    ) {}

    /**
     * Every message in the batch goes back to wait, as when the request itself failed.
     *
     * @throws StorageException
     */
    public function retryAll(Batch $batch, string $reason, Tally $tally, DateTimeImmutable $now): void
    {
        $this->logger->warning('{binding}: sending {count} failed: {reason}', [
            'binding' => $batch->binding->name,
            'count'   => count($batch->rows),
            'reason'  => $reason,
        ]);
        foreach ($batch->rows as $row) {
            $this->retry($row, $reason, null, $tally, $now);
        }
    }

    /**
     * @throws StorageException
     */
    public function settle(Batch $batch, OutboxRow $row, Outcome $outcome, Tally $tally, DateTimeImmutable $now): void
    {
        match ($outcome->kind) {
            OutcomeKind::Done      => $this->done($batch, $row, $outcome, $tally, $now),
            OutcomeKind::Retry     => $this->retry($row, (string) $outcome->reason, $outcome->retryAfter, $tally, $now),
            OutcomeKind::Failed    => $this->fail($batch, $row, (string) $outcome->reason, $tally, $now),
            OutcomeKind::Forgotten => $this->forgotten($batch, $row, (string) $outcome->reason, $tally, $now),
        };
    }

    /**
     * @throws StorageException
     */
    private function done(Batch $batch, OutboxRow $row, Outcome $outcome, Tally $tally, DateTimeImmutable $now): void
    {
        $name     = $batch->binding->name;
        $previous = $this->stores->subjects->find($name, $row->emailHash);
        $joined   = match ($batch->decision) {
            Decision::Subscribe, Decision::Pending, Decision::Resubscribe => true,
            default                                                       => false,
        };
        $onList = $joined || Decision::Update === $batch->decision;

        $this->stores->subjects->save(new Subject(
            $name,
            $row->emailHash,
            $outcome->remoteId ?? $previous?->remoteId,
            $this->stores->hasher->values($row->values),
            subscribed: $onList,
            syncedAt: $now,
        ));
        if ($joined && null !== $row->consent) {
            $this->stores->consents->record($name, $row->emailHash, $row->consent);
            $this->stores->suppression->lift($name, $row->emailHash);
        }

        $this->stores->outbox->done($row->id, $now);
        $tally->sent++;
    }

    /**
     * @throws StorageException
     */
    private function fail(Batch $batch, OutboxRow $row, string $reason, Tally $tally, DateTimeImmutable $now): void
    {
        $this->logger->notice('{binding}: message {id} was refused: {reason}', [
            'binding' => $batch->binding->name,
            'id'      => $row->id,
            'reason'  => $reason,
        ]);
        $this->stores->outbox->fail($row->id, $reason, $now);
        $tally->failed++;
    }

    /**
     * @throws StorageException
     */
    private function forgotten(Batch $batch, OutboxRow $row, string $reason, Tally $tally, DateTimeImmutable $now): void
    {
        $this->stores->suppression->suppress(
            $batch->binding->name,
            $row->emailHash,
            SuppressionReason::Forgotten,
            $now,
        );
        $this->fail($batch, $row, $reason, $tally, $now);
    }

    /**
     * @throws StorageException
     */
    private function retry(OutboxRow $row, string $reason, ?int $retryAfter, Tally $tally, DateTimeImmutable $now): void
    {
        $next = $this->backoff->next($row->attempts + 1, $now, $retryAfter);
        if (null === $next) {
            $this->stores->outbox->dead($row->id, $reason, $now);
            $tally->dead++;

            return;
        }

        $this->stores->outbox->retry($row->id, $next, $reason, $now);
        $tally->retried++;
    }
}
