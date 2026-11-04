<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Storage;

use Contenir\Audience\Exception\StorageException;
use Contenir\Audience\Storage\Json;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class JsonTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function unreadableProvider(): array
    {
        return [
            'values that are not JSON'      => ['{', 'The audience column "payload" does not hold JSON'],
            'values that are not an object' => ['"Ada"', 'The audience column "payload" does not hold a JSON object'],
            'a value that is not plain'     => [
                '{"Name":["Ada"]}',
                'The audience column "payload" does not hold plain values',
            ],
        ];
    }

    #[Test]
    public function keepsAConsentAsItWas(): void
    {
        static::assertEquals(Make::consent(), Json::decodeConsent(Json::consent(Make::consent())));
    }

    #[Test]
    public function keepsNoConsentAsNone(): void
    {
        static::assertSame([null, null], [Json::consent(null), Json::decodeConsent(null)]);
    }

    #[Test]
    public function keepsValuesAsTheyWere(): void
    {
        $values = ['Name' => 'Ada', 'Age' => 36, 'Opted' => true, 'Score' => 1.5, 'Phone' => null];

        static::assertSame($values, Json::decodeValues(Json::values($values)));
    }

    #[Test]
    public function readsNoValuesFromNothing(): void
    {
        static::assertSame([], Json::decodeValues(null));
    }

    #[Test]
    public function refusesAStoredConsentThatIsIncomplete(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('The audience column "consent" does not hold a consent');

        Json::decodeConsent('{"wording":"Yes"}');
    }

    #[DataProvider('unreadableProvider')]
    #[Test]
    public function refusesStoredValuesItCannotRead(string $json, string $message): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage($message);

        Json::decodeValues($json);
    }
}
