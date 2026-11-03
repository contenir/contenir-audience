<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit;

use Contenir\Audience\Audience;
use Contenir\Audience\Email;
use Contenir\Audience\Exception\InvalidEmail;
use Contenir\Audience\Exception\UnknownBinding;
use Contenir\Audience\Outbox\Action;
use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Audience\Outbox\OutboxState;
use Contenir\Audience\Testing\InMemoryStores;
use Contenir\Audience\Testing\MutableClock;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AudienceTest extends TestCase
{
    private InMemoryStores $memory;

    private Audience $audience;

    #[Test]
    public function putsAChangedRecordInTheOutboxAsAnUpdateWithoutConsent(): void
    {
        $id  = $this->audience->update('newsletter', ['email' => 'ada@example.com', 'name' => 'Ada', 'phone' => null]);
        $row = $this->memory->outbox->row($id);

        static::assertSame(
            [Action::Update, ['Name' => 'Ada', 'Phone' => null], null],
            [$row->action, $row->values, $row->consent],
        );
    }

    #[Test]
    public function putsADeletedRecordInTheOutboxByItsEmail(): void
    {
        $id  = $this->audience->remove('newsletter', 'Ada@example.com');
        $row = $this->memory->outbox->row($id);

        static::assertSame(
            [Action::Remove, 'ada@example.com', $this->memory->hasher->email(Email::fromString('ada@example.com'))],
            [$row->action, $row->email?->value, $row->emailHash],
        );
    }

    #[Test]
    public function putsASignUpInTheOutboxWithItsMappedValuesAndConsent(): void
    {
        $id = $this->audience->subscribe(
            'newsletter',
            [
                'email' => ' Ada@Example.com ',
                'name'  => 'Ada',
                'phone' => ['not', 'plain'],
                'extra' => 'ignored',
            ],
            Make::consent(),
        );

        static::assertEquals(
            new OutboxRow(
                $id,
                'newsletter',
                Action::Subscribe,
                Email::fromString('ada@example.com'),
                $this->memory->hasher->email(Email::fromString('ada@example.com')),
                ['Name' => 'Ada'],
                Make::consent(),
                OutboxState::Waiting,
                0,
                Make::time(),
                null,
                Make::time(),
                Make::time(),
            ),
            $this->memory->outbox->row($id),
        );
    }

    #[Test]
    public function readsTheEmailFromTheFieldTheBindingNames(): void
    {
        $audience = new Audience(
            Make::bindings(['email' => 'email_address']),
            $this->memory->stores,
            new MutableClock(Make::time()),
        );

        $id = $audience->update('newsletter', ['email_address' => 'ada@example.com']);

        static::assertSame('ada@example.com', $this->memory->outbox->row($id)->email?->value);
    }

    #[Test]
    public function refusesADeleteForABindingItDoesNotHave(): void
    {
        $this->expectException(UnknownBinding::class);

        $this->audience->remove('members', 'ada@example.com');
    }

    #[Test]
    public function refusesASignUpForABindingItDoesNotHave(): void
    {
        $this->expectException(UnknownBinding::class);

        $this->audience->subscribe('members', ['email' => 'ada@example.com'], Make::consent());
    }

    #[Test]
    public function refusesASignUpWithoutAnEmail(): void
    {
        $this->expectException(InvalidEmail::class);

        $this->audience->subscribe('newsletter', ['name' => 'Ada'], Make::consent());
    }

    protected function setUp(): void
    {
        $this->memory   = new InMemoryStores();
        $this->audience = new Audience(
            Make::bindings(['fields' => ['Name' => 'name', 'Phone' => 'phone']]),
            $this->memory->stores,
            new MutableClock(Make::time()),
        );
    }
}
