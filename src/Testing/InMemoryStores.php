<?php

declare(strict_types=1);

namespace Contenir\Audience\Testing;

use Contenir\Audience\EmailHasher;
use Contenir\Audience\Stores;

/**
 * Stores held in memory, for tests, each reachable by its own type.
 *
 * @api
 */
final readonly class InMemoryStores
{
    public Stores $stores;

    public function __construct(
        public InMemoryOutboxStore $outbox = new InMemoryOutboxStore(),
        public InMemorySubjectStore $subjects = new InMemorySubjectStore(),
        public InMemorySuppressionStore $suppression = new InMemorySuppressionStore(),
        public InMemoryConsentLedger $consents = new InMemoryConsentLedger(),
        public EmailHasher $hasher = new EmailHasher(self::PEPPER),
    ) {
        $this->stores = new Stores($outbox, $subjects, $suppression, $consents, $hasher);
    }

    /** A pepper for tests only */
    public const string PEPPER = 'a pepper for tests only, 32 bytes';
}
