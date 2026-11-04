<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Field;

use Contenir\Audience\Field\Transform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TransformTest extends TestCase
{
    /**
     * @return array<string, array{Transform, string|int|float|bool|null, string|int|float|bool|null}>
     */
    public static function transformProvider(): array
    {
        return [
            'trim'                       => [Transform::Trim, "  Ada \n", 'Ada'],
            'lower'                      => [Transform::Lower, 'ÉMILE', 'émile'],
            'upper'                      => [Transform::Upper, 'émile', 'ÉMILE'],
            'yes from a ticked box'      => [Transform::YesNo, true, 'Yes'],
            'no from an unticked box'    => [Transform::YesNo, false, 'No'],
            'yes from "1"'               => [Transform::YesNo, '1', 'Yes'],
            'yes from " Yes "'           => [Transform::YesNo, ' Yes ', 'Yes'],
            'yes from "y"'               => [Transform::YesNo, 'y', 'Yes'],
            'yes from "true"'            => [Transform::YesNo, 'TRUE', 'Yes'],
            'yes from "on"'              => [Transform::YesNo, 'on', 'Yes'],
            'yes from 1'                 => [Transform::YesNo, 1, 'Yes'],
            'no from "0"'                => [Transform::YesNo, '0', 'No'],
            'no from nothing'            => [Transform::YesNo, null, 'No'],
            'a date'                     => [Transform::Date, '5 November 2026', '2026-11-05'],
            'a date with a time'         => [Transform::Date, '2026-11-05T23:30:00+10:00', '2026-11-05'],
            'no date from nonsense'      => [Transform::Date, 'not a date', null],
            'no date from blank'         => [Transform::Date, '  ', null],
            'a first name'               => [Transform::FirstName, 'Ada  King Lovelace', 'Ada'],
            'a last name'                => [Transform::LastName, 'Ada  King Lovelace', 'King Lovelace'],
            'no last name from one word' => [Transform::LastName, 'Ada', ''],
            'a first name from blank'    => [Transform::FirstName, '', ''],
            'a first name after spaces'  => [Transform::FirstName, '  Ada Lovelace', 'Ada'],
            'a number left as it is'     => [Transform::Trim, 42, 42],
            'nothing left as nothing'    => [Transform::Lower, null, null],
        ];
    }

    #[DataProvider('transformProvider')]
    #[Test]
    public function changesAValueOnItsWayToTheService(
        Transform $transform,
        string|int|float|bool|null $value,
        string|int|float|bool|null $expected,
    ): void {
        static::assertSame($expected, $transform->apply($value));
    }
}
