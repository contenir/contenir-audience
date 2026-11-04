<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Field;

use Contenir\Audience\Field\FieldMap;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class FieldMapTest extends TestCase
{
    /**
     * @return array<string, array{array<array-key, mixed>, string}>
     */
    public static function unreadableProvider(): array
    {
        return [
            'a number for a rule'            => [['FNAME' => 7], 'Field "FNAME" needs a field name or a rule'],
            'neither from nor value'         => [
                ['FNAME' => ['transforms' => []]],
                'Field "FNAME" needs "from" or "value"',
            ],
            'a from that is not text'        => [['FNAME' => ['from' => 7]], 'Field "FNAME" needs "from" or "value"'],
            'a value that is not plain'      => [['FNAME' => ['value' => ['a']]], 'Field "FNAME" needs a plain value'],
            'transforms that are not a list' => [
                ['FNAME' => ['from' => 'name', 'transforms' => ['a' => 'trim']]],
                'Field "FNAME" needs its transforms as a list',
            ],
            'transforms as text'             => [
                ['FNAME' => ['from' => 'name', 'transforms' => 'trim']],
                'Field "FNAME" needs its transforms as a list',
            ],
            'an unknown transform'           => [
                ['FNAME' => ['from' => 'name', 'transforms' => ['shout']]],
                'Field "FNAME" has an unknown transform',
            ],
            'a transform that is not text'   => [
                ['FNAME' => ['from' => 'name', 'transforms' => [7]]],
                'Field "FNAME" has an unknown transform',
            ],
        ];
    }

    #[Test]
    public function fillsEachServiceFieldAsItsRuleSays(): void
    {
        $map = FieldMap::fromConfig([
            'FNAME'  => ['from' => 'name', 'transforms' => ['trim', 'first-name']],
            'LNAME'  => ['from' => 'name', 'transforms' => ['trim', 'last-name']],
            'PHONE'  => 'phone',
            'SOURCE' => ['value' => 'website'],
            'EMPTY'  => ['value' => null],
        ]);

        static::assertSame(
            [
                'FNAME'  => 'Ada',
                'LNAME'  => 'Lovelace',
                'PHONE'  => '0400 000 000',
                'SOURCE' => 'website',
                'EMPTY'  => null,
            ],
            $map->apply(['name' => ' Ada Lovelace ', 'phone' => '0400 000 000']),
        );
    }

    #[Test]
    public function goesOnPastAFieldTheValuesDoNotHave(): void
    {
        static::assertSame(
            ['PHONE' => '0400'],
            FieldMap::fromConfig(['FNAME' => 'name', 'PHONE' => 'phone'])->apply([
                'phone' => '0400',
            ]),
        );
    }

    #[Test]
    public function leavesOutAFieldTheValuesDoNotHave(): void
    {
        static::assertSame(
            ['FNAME' => 'Ada'],
            FieldMap::fromConfig(['FNAME' => 'name', 'PHONE' => 'phone'])->apply([
                'name' => 'Ada',
            ]),
        );
    }

    #[Test]
    public function mapsNothingWithoutRules(): void
    {
        static::assertSame([], (new FieldMap())->apply(['name' => 'Ada']));
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[DataProvider('unreadableProvider')]
    #[Test]
    public function refusesARuleItCannotRead(array $config, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        FieldMap::fromConfig($config);
    }

    #[Test]
    public function sendsAFieldTheValuesHoldAsEmpty(): void
    {
        static::assertSame(['PHONE' => null], FieldMap::fromConfig(['PHONE' => 'phone'])->apply(['phone' => null]));
    }
}
