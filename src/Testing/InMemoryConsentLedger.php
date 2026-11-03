<?php

declare(strict_types=1);

namespace Contenir\Audience\Testing;

use Contenir\Audience\Consent;
use Contenir\Audience\ConsentLedgerInterface;
use Override;

/**
 * A consent ledger held in memory, for tests.
 *
 * @api
 */
final class InMemoryConsentLedger implements ConsentLedgerInterface
{
    /** @var list<array{string, string, Consent}> Binding, email hash and consent, in the order recorded */
    public array $records = [];

    #[Override]
    public function record(string $binding, string $emailHash, Consent $consent): void
    {
        $this->records[] = [$binding, $emailHash, $consent];
    }
}
