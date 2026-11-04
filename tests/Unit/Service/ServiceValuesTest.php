<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Service;

use Contenir\Audience\Service\Capabilities;
use Contenir\Audience\Service\DoubleOptIn;
use Contenir\Audience\Service\RemoteField;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ServiceValuesTest extends TestCase
{
    #[Test]
    public function assumesAFieldIsOptionalUnlessTheServiceSays(): void
    {
        static::assertFalse((new RemoteField('PHONE', 'Phone', 'phone'))->required);
    }

    #[Test]
    public function assumesAServiceHasNoTagsOrSignedWebhooksUnlessItSays(): void
    {
        $capabilities = new Capabilities(100, DoubleOptIn::None);

        static::assertSame([false, false], [$capabilities->tags, $capabilities->signedWebhooks]);
    }
}
