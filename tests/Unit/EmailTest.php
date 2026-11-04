<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit;

use Contenir\Audience\Email;
use Contenir\Audience\Exception\InvalidEmail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class EmailTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function notAnEmailProvider(): array
    {
        return [
            'empty'        => [''],
            'no at-sign'   => ['ada.example.com'],
            'no domain'    => ['ada@'],
            'two at-signs' => ['ada@@example.com'],
        ];
    }

    #[Test]
    public function keysAPersonByTheirTrimmedLowerCaseAddress(): void
    {
        static::assertSame('ada@example.com', Email::fromString('  Ada@Example.COM ')->value);
    }

    #[DataProvider('notAnEmailProvider')]
    #[Test]
    public function refusesTextThatIsNotAnEmailAddress(string $address): void
    {
        $this->expectException(InvalidEmail::class);
        $this->expectExceptionMessage("\"{$address}\" is not an email address");

        Email::fromString($address);
    }
}
