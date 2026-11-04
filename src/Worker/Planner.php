<?php

declare(strict_types=1);

namespace Contenir\Audience\Worker;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Exception\UnknownBinding;
use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Audience\Policy\ConsentPolicy;
use Contenir\Audience\Policy\Decision;
use Contenir\Audience\Service\Capabilities;
use Contenir\Audience\Service\Person;
use Contenir\Audience\Service\PersonStatus;
use Contenir\Audience\Stores;
use DateTimeImmutable;
use LogicException;

use function array_chunk;

/**
 * Turns due messages into batches: each is decided by the consent policy, those that need
 * nothing sent are done at once, and the rest are grouped by binding and decision, no larger
 * than the service allows.
 *
 * @internal
 */
final readonly class Planner
{
    public function __construct(
        private Bindings $bindings,
        private Stores $stores,
    ) {}

    /**
     * @param non-empty-list<array{OutboxRow, Binding, Decision}> $chunk
     */
    private static function batch(array $chunk): Batch
    {
        $rows   = [];
        $people = [];
        foreach ($chunk as [$row, , $decision]) {
            $rows[]   = $row;
            $people[] = new Person(
                $row->email ?? throw new LogicException('A message with nothing to send was batched'),
                $row->values,
                Decision::Pending === $decision ? PersonStatus::Pending : PersonStatus::Subscribed,
                $row->consent,
                resubscribe: Decision::Resubscribe === $decision,
            );
        }

        return new Batch($chunk[0][1], $chunk[0][2], $rows, $people);
    }

    /**
     * @param list<OutboxRow> $rows
     *
     * @return list<Batch>
     *
     * @throws StorageException
     */
    public function plan(array $rows, Capabilities $capabilities, Tally $tally, DateTimeImmutable $now): array
    {
        /** @var array<string, non-empty-list<array{OutboxRow, Binding, Decision}>> $groups */
        $groups = [];
        foreach ($rows as $row) {
            $planned = $this->decide($row, $capabilities, $tally, $now);
            if (null !== $planned) {
                $groups["{$row->binding}\0{$planned[1]->name}"][] = [$row, $planned[0], $planned[1]];
            }
        }

        $batches = [];
        foreach ($groups as $group) {
            /** @var list<non-empty-list<array{OutboxRow, Binding, Decision}>> $chunks */
            $chunks = array_chunk($group, $capabilities->batchSize);
            foreach ($chunks as $chunk) {
                $batches[] = self::batch($chunk);
            }
        }

        return $batches;
    }

    /**
     * The binding $row is for and what to send for it, or null when it's settled here: done when
     * nothing needs sending, failed when its binding has gone.
     *
     * @return array{Binding, Decision}|null
     *
     * @throws StorageException
     */
    private function decide(OutboxRow $row, Capabilities $capabilities, Tally $tally, DateTimeImmutable $now): ?array
    {
        try {
            $binding = $this->bindings->get($row->binding);
        } catch (UnknownBinding $e) {
            $this->stores->outbox->fail($row->id, $e->getMessage(), $now);
            $tally->failed++;

            return null;
        }

        $subject  = $this->stores->subjects->find($row->binding, $row->emailHash);
        $decision = ConsentPolicy::decide(
            $row,
            $binding,
            $subject,
            $this->stores->suppression->reason($row->binding, $row->emailHash),
            $capabilities->doubleOptIn,
        );
        $unchanged =
            Decision::Update === $decision
            && null !== $subject
            && $subject->valuesHash === $this->stores->hasher->values($row->values);
        if (Decision::Skip !== $decision && ! $unchanged && null !== $row->email) {
            return [$binding, $decision];
        }

        $this->stores->outbox->done($row->id, $now);
        $tally->skipped++;

        return null;
    }
}
