<?php

declare(strict_types=1);

namespace Contenir\Audience\Worker;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Audience\Policy\Decision;
use Contenir\Audience\Service\Person;

/**
 * Messages for one binding that are all sent the same way, with the person each one is sent as.
 *
 * @internal
 */
final readonly class Batch
{
    /**
     * @param non-empty-list<OutboxRow> $rows
     * @param non-empty-list<Person>    $people One per row, in the same order
     */
    public function __construct(
        public Binding $binding,
        public Decision $decision,
        public array $rows,
        public array $people,
    ) {}
}
