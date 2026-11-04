<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\Unit\Policy;

use Contenir\Audience\Outbox\Action;
use Contenir\Audience\Policy\ConsentPolicy;
use Contenir\Audience\Policy\Decision;
use Contenir\Audience\Service\DoubleOptIn;
use Contenir\Audience\Subject\Subject;
use Contenir\Audience\Suppression\SuppressionReason;
use Contenir\Audience\Tests\TestAsset\Factory\Make;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_slice;

#[Group('unit')]
final class ConsentPolicyTest extends TestCase
{
    /**
     * What's given, then the decision expected.
     *
     * @return array<string, array{array{Action, bool, ?bool, ?SuppressionReason, array<string, mixed>, DoubleOptIn}, Decision}>
     */
    public static function decisionProvider(): array
    {
        return array_map(static fn(array $case): array => [
            array_slice($case, offset: 0, length: 6),
            $case[6],
        ], self::cases());
    }

    /**
     * @return array<string, array{Action, bool, ?bool, ?SuppressionReason, array<string, mixed>, DoubleOptIn, Decision}>
     */
    private static function cases(): array
    {
        $per  = DoubleOptIn::PerRequest;
        $list = DoubleOptIn::PerList;

        return [
            'a sign-up confirms first where each request can ask' => [
                Action::Subscribe,
                true,
                null,
                null,
                [],
                $per,
                Decision::Pending,
            ],
            'a sign-up joins at once where the list decides'      => [
                Action::Subscribe,
                true,
                null,
                null,
                [],
                $list,
                Decision::Subscribe,
            ],
            'a sign-up joins at once without double opt-in'       => [
                Action::Subscribe,
                true,
                null,
                null,
                ['double_opt_in' => false],
                $per,
                Decision::Subscribe,
            ],
            'a sign-up after unsubscribing comes back'            => [
                Action::Subscribe,
                true,
                false,
                SuppressionReason::Unsubscribed,
                [],
                $per,
                Decision::Resubscribe,
            ],
            'a sign-up after bouncing joins again'                => [
                Action::Subscribe,
                true,
                false,
                SuppressionReason::Cleaned,
                [],
                $per,
                Decision::Pending,
            ],
            'a sign-up after a complaint is refused'              => [
                Action::Subscribe,
                true,
                false,
                SuppressionReason::Complaint,
                [],
                $per,
                Decision::Skip,
            ],
            'a sign-up after being erased is refused'             => [
                Action::Subscribe,
                true,
                null,
                SuppressionReason::Forgotten,
                [],
                $list,
                Decision::Skip,
            ],
            'a subscribe without consent only updates'            => [
                Action::Subscribe,
                false,
                true,
                null,
                [],
                $per,
                Decision::Update,
            ],
            'a subscribe without consent skips someone unknown'   => [
                Action::Subscribe,
                false,
                null,
                null,
                [],
                $per,
                Decision::Skip,
            ],
            'an update reaches someone already sent'              => [
                Action::Update,
                false,
                true,
                null,
                [],
                $per,
                Decision::Update,
            ],
            'an update skips someone taken off the list'          => [
                Action::Update,
                false,
                false,
                null,
                [],
                $per,
                Decision::Skip,
            ],
            'an update skips someone never sent'                  => [
                Action::Update,
                false,
                null,
                null,
                [],
                $per,
                Decision::Skip,
            ],
            'an update skips someone suppressed'                  => [
                Action::Update,
                false,
                false,
                SuppressionReason::Unsubscribed,
                [],
                $per,
                Decision::Skip,
            ],
            'a delete unsubscribes by default'                    => [
                Action::Remove,
                false,
                true,
                null,
                [],
                $per,
                Decision::Unsubscribe,
            ],
            'a delete archives when the binding says'             => [
                Action::Remove,
                false,
                true,
                null,
                ['on_delete' => 'archive'],
                $per,
                Decision::Archive,
            ],
            'a delete changes nothing when the binding keeps'     => [
                Action::Remove,
                false,
                true,
                null,
                ['on_delete' => 'keep'],
                $per,
                Decision::Skip,
            ],
            'a delete skips someone never sent'                   => [
                Action::Remove,
                false,
                null,
                null,
                [],
                $per,
                Decision::Skip,
            ],
            'a delete skips someone already unsubscribed'         => [
                Action::Remove,
                false,
                false,
                null,
                [],
                $per,
                Decision::Skip,
            ],
        ];
    }

    /**
     * @param array{Action, bool, ?bool, ?SuppressionReason, array<string, mixed>, DoubleOptIn} $given
     */
    #[DataProvider('decisionProvider')]
    #[Test]
    public function decidesWhatIsSentForAMessage(array $given, Decision $expected): void
    {
        [$action, $consented, $subscribed, $suppressed, $binding, $doubleOptIn] = $given;
        $subject = null === $subscribed
            ? null
            : new Subject('newsletter', 'hash', 'r1', 'v', $subscribed, Make::time());

        static::assertSame($expected, ConsentPolicy::decide(
            Make::row($action, $consented ? Make::consent() : null),
            Make::binding($binding),
            $subject,
            $suppressed,
            $doubleOptIn,
        ));
    }
}
