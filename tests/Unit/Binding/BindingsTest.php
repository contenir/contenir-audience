<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Binding;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Binding\Bindings;
use Contenir\Audience\Binding\OnDelete;
use Contenir\Audience\Exception\InvalidBinding;
use Contenir\Audience\Exception\UnknownBinding;
use Contenir\Audience\Field\FieldMap;
use Contenir\Audience\Field\FieldRule;
use Contenir\Audience\Field\Transform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BindingsTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'settings that are not a list' => ['list-1', 'needs its settings as a list'],
            'no list'                      => [[], 'needs "list"'],
            'an unknown key'               => [['list' => '1', 'lists' => '2'], 'has unknown keys: lists'],
            'a list that is not text'      => [['list' => 7], 'needs "list" as text'],
            'an empty list'                => [['list' => ''], 'needs "list" as text'],
            'double opt-in as text'        => [
                ['list' => '1', 'double_opt_in' => 'yes'],
                'needs "double_opt_in" as true or false',
            ],
            'fields as text'               => [['list' => '1', 'fields' => 'name'], 'needs "fields" as a list'],
            'a field it cannot read'       => [
                ['list' => '1', 'fields' => ['FNAME' => 7]],
                'has a field it can\'t read: Field "FNAME" needs a field name or a rule',
            ],
            'tags with a number'           => [['list' => '1', 'tags' => ['a', 7]], 'needs "tags" as a list of text'],
            'tags with an empty one'       => [['list' => '1', 'tags' => ['']], 'needs "tags" as a list of text'],
            'tags by name'                 => [
                ['list' => '1', 'tags' => ['a' => 'b']],
                'needs "tags" as a list of text',
            ],
            'an unknown on-delete'         => [
                ['list' => '1', 'on_delete' => 'erase'],
                'needs "on_delete" as unsubscribe, archive or keep',
            ],
            'options as a list'            => [
                ['list' => '1', 'options' => ['Yes']],
                'needs "options" as names and plain values',
            ],
            'an option that is not plain'  => [
                ['list' => '1', 'options' => ['a' => ['b']]],
                'needs "options" as names and plain values',
            ],
        ];
    }

    #[Test]
    public function deletesAsTheServiceDefaultsWhenTheBindingDoesNotSay(): void
    {
        static::assertSame(
            OnDelete::Archive,
            Bindings::fromConfig(['newsletter' => ['list' => 'list-1']], OnDelete::Archive)->get(
                'newsletter',
            )->onDelete,
        );
    }

    #[Test]
    public function fillsInWhatABindingLeavesOut(): void
    {
        static::assertEquals(
            new Binding('newsletter', 'list-1'),
            Bindings::fromConfig(['newsletter' => ['list' => 'list-1']])->get('newsletter'),
        );
    }

    #[Test]
    public function findsNoBindingsForASectionNoneSyncs(): void
    {
        static::assertSame(
            [],
            Bindings::fromConfig(['newsletter' => ['list' => '1']])->forSection('site.page.resource'),
        );
    }

    #[Test]
    public function findsTheBindingsThatSyncASection(): void
    {
        $bindings = Bindings::fromConfig([
            'newsletter' => ['list' => '1'],
            'members'    => ['list' => '2', 'source' => 'library.members.member'],
            'staff'      => ['list' => '3', 'source' => 'library.members.member'],
        ]);

        static::assertSame(
            ['members', 'staff'],
            [
                $bindings->forSection('library.members.member')[0]->name,
                $bindings->forSection('library.members.member')[1]->name,
            ],
        );
    }

    #[Test]
    public function givesAnOptionOnlyItsServiceReads(): void
    {
        $binding = new Binding('newsletter', 'list-1', options: ['consent_to_track' => 'Yes']);

        static::assertSame(['Yes', null], [$binding->option('consent_to_track'), $binding->option('other')]);
    }

    #[Test]
    public function knowsABindingFedByTheSitesOwnForms(): void
    {
        static::assertSame(
            [true, false],
            [
                (new Binding('newsletter', 'list-1'))->isFromSite(),
                (new Binding('members', 'list-1', 'library.members.member'))->isFromSite(),
            ],
        );
    }

    #[Test]
    public function listsEveryBindingInOrder(): void
    {
        $bindings = Bindings::fromConfig(['a' => ['list' => '1'], 'b' => ['list' => '2']]);

        static::assertSame(['a', 'b'], [$bindings->all()[0]->name, $bindings->all()[1]->name]);
    }

    #[Test]
    public function readsEverythingABindingCanSay(): void
    {
        $bindings = Bindings::fromConfig([
            'members' => [
                'list'          => 'list-1',
                'source'        => 'library.members.member',
                'email'         => 'email_address',
                'fields'        => ['FNAME' => ['from' => 'name', 'transforms' => ['first-name']]],
                'consent'       => 'Joining as a member',
                'double_opt_in' => false,
                'tags'          => ['member', 'website'],
                'on_delete'     => 'archive',
                'options'       => ['consent_to_track' => 'Yes'],
            ],
        ]);

        static::assertEquals(
            new Binding(
                'members',
                'list-1',
                'library.members.member',
                'email_address',
                new FieldMap([new FieldRule('FNAME', 'name', [Transform::FirstName])]),
                'Joining as a member',
                doubleOptIn: false,
                tags: ['member', 'website'],
                onDelete: OnDelete::Archive,
                options: ['consent_to_track' => 'Yes'],
            ),
            $bindings->get('members'),
        );
    }

    #[DataProvider('invalidProvider')]
    #[Test]
    public function refusesABindingItCannotUse(mixed $config, string $message): void
    {
        $this->expectException(InvalidBinding::class);
        $this->expectExceptionMessage("The audience binding \"newsletter\" {$message}");

        Bindings::fromConfig(['newsletter' => $config]);
    }

    #[Test]
    public function refusesABindingItDoesNotHave(): void
    {
        $this->expectException(UnknownBinding::class);
        $this->expectExceptionMessage('There is no audience binding "members"');

        Bindings::fromConfig([])->get('members');
    }
}
