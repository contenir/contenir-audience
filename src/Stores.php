<?php

declare(strict_types=1);

namespace Contenir\Audience;

use Contenir\Audience\Outbox\OutboxStoreInterface;
use Contenir\Audience\Subject\SubjectStoreInterface;
use Contenir\Audience\Suppression\SuppressionStoreInterface;

/**
 * Everything one add-on keeps about its people, in its own tables, and the hasher that keys it.
 *
 * @api
 */
final readonly class Stores
{
    public function __construct(
        public OutboxStoreInterface $outbox,
        public SubjectStoreInterface $subjects,
        public SuppressionStoreInterface $suppression,
        public ConsentLedgerInterface $consents,
        public EmailHasher $hasher,
    ) {}
}
