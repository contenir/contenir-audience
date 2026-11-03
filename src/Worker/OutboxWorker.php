<?php

declare(strict_types=1);

namespace Contenir\Audience\Worker;

use Contenir\Audience\Backoff;
use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Policy\Decision;
use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\Outcome;
use Contenir\Audience\Service\ServiceAdapterInterface;
use Contenir\Audience\Stores;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function sprintf;

/**
 * Sends what's due in one add-on's outbox to its service, for a limited time: run by the
 * scheduler every minute, behind the queue's lock. When the service refuses the connection, it
 * stops and leaves everything waiting, so nothing is lost while the connection is put right.
 *
 * @api
 */
final readonly class OutboxWorker
{
    /** How long done messages are kept, in days */
    public const int KEEP_DONE_DAYS = 7;

    /** How long failed and dead messages are kept, in days */
    public const int KEEP_FAILED_DAYS = 30;

    private Planner $planner;

    private Settler $settler;

    public function __construct(
        Bindings $bindings,
        private Stores $stores,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        Backoff $backoff = new Backoff(),
    ) {
        $this->planner = new Planner($bindings, $stores);
        $this->settler = new Settler($stores, $backoff, $logger);
    }

    /**
     * @return list<Outcome>
     *
     * @throws ConnectionRefused
     */
    private static function send(ServiceAdapterInterface $service, Batch $batch): array
    {
        return match ($batch->decision) {
            Decision::Subscribe, Decision::Pending, Decision::Resubscribe => $service->subscribe(
                $batch->binding,
                $batch->people,
            ),
            Decision::Update => $service->update($batch->binding, $batch->people),
            Decision::Unsubscribe => $service->unsubscribe($batch->binding, $batch->people),
            Decision::Archive => $service->archive($batch->binding, $batch->people),
            Decision::Skip                                                => [],
        };
    }

    /**
     * @param positive-int $limit   The most messages to look at
     * @param positive-int $seconds How long to keep sending before leaving the rest for next time
     *
     * @throws StorageException
     */
    public function drain(ServiceAdapterInterface $service, int $limit = 500, int $seconds = 40): DrainReport
    {
        $tally    = new Tally();
        $start    = $this->clock->now();
        $deadline = $start->modify("+{$seconds} seconds");
        $batches  = $this->planner->plan(
            $this->stores->outbox->due($limit, $start),
            $service->capabilities(),
            $tally,
            $start,
        );

        foreach ($batches as $batch) {
            if ($this->clock->now() >= $deadline) {
                $tally->finished = false;

                return $tally->report();
            }

            try {
                $outcomes = self::send($service, $batch);
            } catch (ConnectionRefused $e) {
                $this->logger->warning('Sending paused: {reason}', ['reason' => $e->getMessage()]);
                $tally->paused   = $e->getMessage();
                $tally->finished = false;

                return $tally->report();
            } catch (Throwable $e) {
                $this->settler->retryAll($batch, $e->getMessage(), $tally, $this->clock->now());
                continue;
            }

            $this->settle($batch, $outcomes, $tally);
        }

        return $tally->report();
    }

    /**
     * Deletes messages kept past their time: done ones after a week, failed and dead ones after
     * 30 days. Answers with how many went.
     *
     * @throws StorageException
     */
    public function prune(): int
    {
        $now = $this->clock->now();

        return $this->stores->outbox->prune(
            $now->modify(sprintf('-%d days', self::KEEP_DONE_DAYS)),
            $now->modify(sprintf('-%d days', self::KEEP_FAILED_DAYS)),
        );
    }

    /**
     * Settles each message by its outcome, in order. A message the service gave no outcome for
     * goes back to wait.
     *
     * @param list<Outcome> $outcomes
     *
     * @throws StorageException
     */
    private function settle(Batch $batch, array $outcomes, Tally $tally): void
    {
        $now = $this->clock->now();
        foreach ($batch->rows as $index => $row) {
            $this->settler->settle(
                $batch,
                $row,
                $outcomes[$index] ?? Outcome::retry('The service gave no outcome for this person'),
                $tally,
                $now,
            );
        }
    }
}
