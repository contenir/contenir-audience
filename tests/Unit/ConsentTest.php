<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit;

use Contenir\Audience\Consent;
use Contenir\Audience\ConsentSource;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

#[Group('unit')]
final class ConsentTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function incompleteProvider(): array
    {
        $whole = Make::consent()->toArray();

        return [
            'no wording'  => [['wording' => null] + $whole],
            'no source'   => [['source' => null] + $whole],
            'no time'     => [['at' => null] + $whole],
            'an odd time' => [['at' => 'yesterday'] + $whole],
        ];
    }

    #[Test]
    public function comesBackAsItWasStored(): void
    {
        $consent = Make::consent();

        static::assertEquals($consent, Consent::fromArray($consent->toArray()));
    }

    #[Test]
    public function keepsNoAddressWhenNoneWasStored(): void
    {
        $consent = new Consent('Joined at the event', ConsentSource::Import, Make::time());

        static::assertNull(Consent::fromArray($consent->toArray())->ip);
    }

    /**
     * @param array<string, mixed> $stored
     */
    #[DataProvider('incompleteProvider')]
    #[Test]
    public function refusesAStoredConsentMissingWhatItNeeds(array $stored): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('A stored consent needs its wording, source and time');

        Consent::fromArray($stored);
    }
}
