<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\TestAsset\Contract;

use Contenir\Audience\Tests\TestAsset\Factory\Make;
use Contenir\Audience\Webhook\RegisteredWebhook;
use Contenir\Audience\Webhook\WebhookStoreInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;

/**
 * What every registered webhook store does, whatever holds it.
 */
abstract class AbstractWebhookStoreContract extends TestCase
{
    #[Test]
    public function findsNothingForABindingWithNoWebhook(): void
    {
        static::assertNull($this->store()->find('newsletter'));
    }

    #[Test]
    public function findsTheWebhookOfTheBindingItIsGiven(): void
    {
        $store = $this->store();
        $store->save(new RegisteredWebhook('members', 'w1', null, Make::time()));
        $store->save(new RegisteredWebhook('newsletter', 'w2', null, Make::time()));

        static::assertSame('w2', $store->find('newsletter')?->remoteId);
    }

    #[Test]
    public function forgetsOnlyTheBindingItIsGiven(): void
    {
        $store = $this->store();
        $store->save(new RegisteredWebhook('newsletter', 'w1', null, Make::time()));
        $store->save(new RegisteredWebhook('members', 'w2', null, Make::time()));

        $store->forget('newsletter');

        static::assertSame(['members'], array_keys($store->all()));
    }

    #[Test]
    public function keepsAWebhookWithItsSigningSecret(): void
    {
        $store   = $this->store();
        $webhook = new RegisteredWebhook('newsletter', 'w1', 'a signing secret', Make::time());

        $store->save($webhook);

        static::assertEquals($webhook, $store->find('newsletter'));
    }

    #[Test]
    public function listsEveryWebhookByBindingInOrder(): void
    {
        $store = $this->store();
        $store->save(new RegisteredWebhook('newsletter', 'w1', null, Make::time()));
        $store->save(new RegisteredWebhook('members', 'w2', 'secret', Make::time()));

        $all = $store->all();

        static::assertEquals(
            [
                ['members', 'newsletter'],
                [
                    'members'    => new RegisteredWebhook('members', 'w2', 'secret', Make::time()),
                    'newsletter' => new RegisteredWebhook('newsletter', 'w1', null, Make::time()),
                ],
            ],
            [array_keys($all), $all],
        );
    }

    #[Test]
    public function replacesTheWebhookWhenTheBindingRegistersAgain(): void
    {
        $store = $this->store();
        $store->save(new RegisteredWebhook('newsletter', 'w1', 'old', Make::time()));
        $again = new RegisteredWebhook('newsletter', 'w2', 'new', Make::time('2026-11-06 09:00:00'));

        $store->save($again);

        static::assertEquals(['newsletter' => $again], $store->all());
    }

    abstract protected function store(): WebhookStoreInterface;
}
