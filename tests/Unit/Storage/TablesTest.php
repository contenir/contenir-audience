<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Storage;

use Contenir\Audience\Storage\Tables;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TablesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function badPrefixProvider(): array
    {
        return [
            'empty'                 => [''],
            'no closing underscore' => ['ext_mailchimp'],
            'a dash'                => ['ext_campaign-monitor_'],
            'a capital'             => ['Ext_mailchimp_'],
            'starting with a digit' => ['9ext_'],
            'a trailing newline'    => ["ext_mailchimp_\n"],
            'a leading space'       => [' ext_mailchimp_'],
        ];
    }

    #[Test]
    public function namesEachTableUnderTheAddOnsPrefix(): void
    {
        $tables = new Tables('ext_campaign_monitor_');

        static::assertSame(
            [
                'ext_campaign_monitor_outbox',
                'ext_campaign_monitor_subject',
                'ext_campaign_monitor_suppression',
                'ext_campaign_monitor_consent',
            ],
            [$tables->outbox(), $tables->subject(), $tables->suppression(), $tables->consent()],
        );
    }

    #[DataProvider('badPrefixProvider')]
    #[Test]
    public function refusesAPrefixThatIsNotATableNamesStart(string $prefix): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("not \"{$prefix}\"");

        new Tables($prefix);
    }
}
