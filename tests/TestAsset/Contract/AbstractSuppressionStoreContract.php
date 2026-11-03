<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\TestAsset\Contract;

use Contenir\Audience\Suppression\SuppressionReason;
use Contenir\Audience\Suppression\SuppressionStoreInterface;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What every suppression store does, whatever holds it.
 */
abstract class AbstractSuppressionStoreContract extends TestCase
{
    #[Test]
    public function keepsEachBindingsSuppressionsApart(): void
    {
        $store = $this->store();
        $store->suppress('newsletter', 'hash-1', SuppressionReason::Unsubscribed, Make::time());

        $store->suppress('members', 'hash-1', SuppressionReason::Complaint, Make::time());

        static::assertSame(SuppressionReason::Unsubscribed, $store->reason('newsletter', 'hash-1'));
    }

    #[Test]
    public function keepsTheLatestReason(): void
    {
        $store = $this->store();
        $store->suppress('newsletter', 'hash-1', SuppressionReason::Unsubscribed, Make::time());

        $store->suppress('newsletter', 'hash-1', SuppressionReason::Complaint, Make::time());

        static::assertSame(SuppressionReason::Complaint, $store->reason('newsletter', 'hash-1'));
    }

    #[Test]
    public function knowsWhySomeoneIsSuppressed(): void
    {
        $store = $this->store();

        $store->suppress('newsletter', 'hash-1', SuppressionReason::Unsubscribed, Make::time());

        static::assertSame(
            [SuppressionReason::Unsubscribed, null, null],
            [
                $store->reason('newsletter', 'hash-1'),
                $store->reason('newsletter', 'hash-2'),
                $store->reason('members', 'hash-1'),
            ],
        );
    }

    #[Test]
    public function liftsASuppression(): void
    {
        $store = $this->store();
        $store->suppress('newsletter', 'hash-1', SuppressionReason::Unsubscribed, Make::time());
        $store->suppress('newsletter', 'hash-2', SuppressionReason::Cleaned, Make::time());

        $store->lift('newsletter', 'hash-1');

        static::assertSame(
            [null, SuppressionReason::Cleaned],
            [$store->reason('newsletter', 'hash-1'), $store->reason('newsletter', 'hash-2')],
        );
    }

    #[Test]
    public function liftsASuppressionOnlyForTheBindingItIsGiven(): void
    {
        $store = $this->store();
        $store->suppress('newsletter', 'hash-1', SuppressionReason::Unsubscribed, Make::time());

        $store->lift('members', 'hash-1');

        static::assertSame(SuppressionReason::Unsubscribed, $store->reason('newsletter', 'hash-1'));
    }

    abstract protected function store(): SuppressionStoreInterface;
}
