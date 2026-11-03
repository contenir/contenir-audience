<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit;

use Contenir\Audience\Email;
use Contenir\Audience\RemoteEvents;
use Contenir\Audience\Service\RemoteEvent;
use Contenir\Audience\Service\RemoteEventKind;
use Contenir\Audience\Subject\Subject;
use Contenir\Audience\Suppression\SuppressionReason;
use Contenir\Audience\Testing\InMemoryStores;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RemoteEventsTest extends TestCase
{
    private InMemoryStores $memory;

    private string $hash;

    /**
     * @return array<string, array{RemoteEventKind, SuppressionReason}>
     */
    public static function suppressingProvider(): array
    {
        return [
            'an unsubscribe' => [RemoteEventKind::Unsubscribed, SuppressionReason::Unsubscribed],
            'a bounce'       => [RemoteEventKind::Cleaned, SuppressionReason::Cleaned],
            'a complaint'    => [RemoteEventKind::Complaint, SuppressionReason::Complaint],
            'an erasure'     => [RemoteEventKind::Forgotten, SuppressionReason::Forgotten],
        ];
    }

    #[Test]
    public function appliesEveryEventInACall(): void
    {
        (new RemoteEvents($this->memory->stores))->apply(Make::binding(), [
            new RemoteEvent(RemoteEventKind::EmailChanged, Email::fromString('old@example.com'), Make::time()),
            new RemoteEvent(RemoteEventKind::Unsubscribed, Email::fromString(Make::EMAIL), Make::time()),
        ]);

        static::assertSame(SuppressionReason::Unsubscribed, $this->memory->suppression->reason(
            'newsletter',
            $this->hash,
        ));
    }

    #[Test]
    public function forgetsTheOldAddressWhenSomeoneChangesTheirs(): void
    {
        $this->memory->subjects->save(new Subject('newsletter', $this->hash, 'r1', 'v', true, Make::time()));

        $this->apply(RemoteEventKind::EmailChanged);

        static::assertSame(
            [null, null],
            [
                $this->memory->subjects->find('newsletter', $this->hash),
                $this->memory->suppression->reason('newsletter', $this->hash),
            ],
        );
    }

    #[Test]
    public function leavesNoSyncStateForSomeoneNeverSent(): void
    {
        $this->apply(RemoteEventKind::Unsubscribed);

        static::assertSame([], $this->memory->subjects->subjects);
    }

    #[Test]
    public function marksSomeoneAlreadySentAsUnsubscribed(): void
    {
        $this->memory->subjects->save(new Subject('newsletter', $this->hash, 'r1', 'v', true, Make::time()));

        $this->apply(RemoteEventKind::Unsubscribed, Make::time('2026-11-06 10:00:00'));

        static::assertEquals(
            new Subject('newsletter', $this->hash, 'r1', 'v', false, Make::time('2026-11-06 10:00:00')),
            $this->memory->subjects->find('newsletter', $this->hash),
        );
    }

    #[DataProvider('suppressingProvider')]
    #[Test]
    public function suppressesWhoeverTheServiceStopped(RemoteEventKind $kind, SuppressionReason $expected): void
    {
        $this->apply($kind);

        static::assertSame($expected, $this->memory->suppression->reason('newsletter', $this->hash));
    }

    protected function setUp(): void
    {
        $this->memory = new InMemoryStores();
        $this->hash   = $this->memory->hasher->email(Email::fromString(Make::EMAIL));
    }

    private function apply(RemoteEventKind $kind, ?DateTimeImmutable $at = null): void
    {
        (new RemoteEvents($this->memory->stores))->apply(Make::binding(), [
            new RemoteEvent($kind, Email::fromString(Make::EMAIL), $at ?? Make::time()),
        ]);
    }
}
