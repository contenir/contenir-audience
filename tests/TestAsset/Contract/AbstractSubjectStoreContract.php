<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\TestAsset\Contract;

use Contenir\Audience\Subject\Subject;
use Contenir\Audience\Subject\SubjectStoreInterface;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What every sync state store does, whatever holds it.
 */
abstract class AbstractSubjectStoreContract extends TestCase
{
    #[Test]
    public function forgetsSomeone(): void
    {
        $store = $this->store();
        $store->save(new Subject('newsletter', 'hash-1', 'r1', 'v', true, Make::time()));
        $store->save(new Subject('newsletter', 'hash-2', 'r2', 'v', true, Make::time()));

        $store->forget('newsletter', 'hash-1');

        static::assertSame(
            [null, 'r2'],
            [$store->find('newsletter', 'hash-1'), $store->find('newsletter', 'hash-2')?->remoteId],
        );
    }

    #[Test]
    public function forgetsSomeoneOnlyForTheBindingItIsGiven(): void
    {
        $store = $this->store();
        $store->save(new Subject('newsletter', 'hash-1', 'r1', 'v', true, Make::time()));

        $store->forget('members', 'hash-1');

        static::assertSame('r1', $store->find('newsletter', 'hash-1')?->remoteId);
    }

    #[Test]
    public function keepsEachBindingsStateApart(): void
    {
        $store = $this->store();
        $store->save(new Subject('newsletter', 'hash-1', 'r1', 'v', true, Make::time()));

        static::assertNull($store->find('members', 'hash-1'));
    }

    #[Test]
    public function keepsWhatABindingLastDidForSomeone(): void
    {
        $store   = $this->store();
        $subject = new Subject('newsletter', 'hash-1', 'r1', 'values-1', true, Make::time());

        $store->save($subject);

        static::assertEquals($subject, $store->find('newsletter', 'hash-1'));
    }

    #[Test]
    public function replacesItWhenTheBindingDoesMore(): void
    {
        $store = $this->store();
        $store->save(new Subject('newsletter', 'hash-1', 'r1', 'values-1', true, Make::time()));
        $later = new Subject('newsletter', 'hash-1', null, 'values-2', false, Make::time('2026-11-06 09:00:00'));

        $store->save($later);

        static::assertEquals($later, $store->find('newsletter', 'hash-1'));
    }

    abstract protected function store(): SubjectStoreInterface;
}
