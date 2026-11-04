<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\TestAsset\Service;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Service\Account;
use Contenir\Audience\Service\Capabilities;
use Contenir\Audience\Service\DoubleOptIn;
use Contenir\Audience\Service\ServiceAdapterInterface;
use Override;

/**
 * A service that accepts every request and answers with no outcome for anyone, as a faulty
 * adapter might.
 */
final class SilentService implements ServiceAdapterInterface
{
    #[Override]
    public function archive(Binding $binding, array $people): array
    {
        return [];
    }

    #[Override]
    public function capabilities(): Capabilities
    {
        return new Capabilities(100, DoubleOptIn::PerRequest);
    }

    #[Override]
    public function fields(string $listId): array
    {
        return [];
    }

    #[Override]
    public function lists(): array
    {
        return [];
    }

    #[Override]
    public function subscribe(Binding $binding, array $people): array
    {
        return [];
    }

    #[Override]
    public function unsubscribe(Binding $binding, array $people): array
    {
        return [];
    }

    #[Override]
    public function update(Binding $binding, array $people): array
    {
        return [];
    }

    #[Override]
    public function verifyConnection(): Account
    {
        return new Account('silent', 'Silent');
    }
}
