<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit;

use Contenir\Audience\Email;
use Contenir\Audience\EmailHasher;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function hash_hmac;
use function str_repeat;

#[Group('unit')]
final class EmailHasherTest extends TestCase
{
    private const string PEPPER = 'a pepper for tests only, 32 bytes';

    #[Test]
    public function acceptsAPepperOfExactly32Bytes(): void
    {
        static::assertSame(
            hash_hmac('sha256', data: 'ada@example.com', key: str_repeat('p', times: 32)),
            (new EmailHasher(str_repeat('p', times: 32)))->email(Email::fromString('ada@example.com')),
        );
    }

    #[Test]
    public function hashesAnEmailWithThePepper(): void
    {
        static::assertSame(
            hash_hmac('sha256', data: 'ada@example.com', key: self::PEPPER),
            (new EmailHasher(self::PEPPER))->email(Email::fromString('Ada@example.com')),
        );
    }

    #[Test]
    public function hashesTheSameValuesTheSameWhateverTheirOrder(): void
    {
        $hasher = new EmailHasher(self::PEPPER);

        static::assertSame(
            hash_hmac('sha256', data: '{"A":1,"B":"two"}', key: self::PEPPER),
            $hasher->values(['B' => 'two', 'A' => 1]),
        );
    }

    #[Test]
    public function refusesAPepperShorterThan32Bytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The audience pepper must be at least 32 bytes');

        new EmailHasher(str_repeat('p', times: 31));
    }
}
