<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Service;

use Contenir\Audience\Service\ConnectionRefused;
use Contenir\Audience\Service\InvalidWebhook;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ServiceExceptionsTest extends TestCase
{
    #[Test]
    public function saysWhyAWebhookCallWasRefused(): void
    {
        static::assertSame(
            'The webhook call was refused: the signature does not match',
            InvalidWebhook::because('the signature does not match')->getMessage(),
        );
    }

    #[Test]
    public function saysWhyTheServiceRefusedTheConnection(): void
    {
        static::assertSame(
            'The service refused the connection: 401 API key invalid',
            ConnectionRefused::because('401 API key invalid')->getMessage(),
        );
    }
}
