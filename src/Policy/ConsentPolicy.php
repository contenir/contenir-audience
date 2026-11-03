<?php

declare(strict_types=1);

namespace Contenir\Audience\Policy;

use Contenir\Audience\Binding\Binding;
use Contenir\Audience\Binding\OnDelete;
use Contenir\Audience\Outbox\Action;
use Contenir\Audience\Outbox\OutboxRow;
use Contenir\Audience\Service\DoubleOptIn;
use Contenir\Audience\Subject\Subject;
use Contenir\Audience\Suppression\SuppressionReason;

/**
 * Decides what is sent for a message. Nobody joins a list without consent: a subscribe without
 * one is only an update. Nobody who left, bounced, complained or was erased is taken back
 * without fresh consent, or at all when the reason forbids it.
 *
 * @api
 */
final class ConsentPolicy
{
    public static function decide(
        OutboxRow $row,
        Binding $binding,
        ?Subject $subject,
        ?SuppressionReason $suppressed,
        DoubleOptIn $doubleOptIn,
    ): Decision {
        return match ($row->action) {
            Action::Remove    => self::remove($binding, $subject),
            Action::Update    => self::update($subject, $suppressed),
            Action::Subscribe => null === $row->consent
                ? self::update($subject, $suppressed)
                : self::join($binding, $suppressed, $doubleOptIn),
        };
    }

    private static function join(Binding $binding, ?SuppressionReason $suppressed, DoubleOptIn $doubleOptIn): Decision
    {
        if (null !== $suppressed && ! $suppressed->yieldsToConsent()) {
            return Decision::Skip;
        }
        if (SuppressionReason::Unsubscribed === $suppressed) {
            return Decision::Resubscribe;
        }

        return $binding->doubleOptIn && DoubleOptIn::PerRequest === $doubleOptIn
            ? Decision::Pending
            : Decision::Subscribe;
    }

    private static function remove(Binding $binding, ?Subject $subject): Decision
    {
        return (
            null === $subject || ! $subject->subscribed
                ? Decision::Skip
                : match ($binding->onDelete) {
                    OnDelete::Unsubscribe => Decision::Unsubscribe,
                    OnDelete::Archive     => Decision::Archive,
                    OnDelete::Keep        => Decision::Skip,
                }
        );
    }

    /**
     * An update reaches only someone on the list: never someone not yet sent, taken off the list
     * when their record was deleted, or stopped by the service.
     */
    private static function update(?Subject $subject, ?SuppressionReason $suppressed): Decision
    {
        return null === $subject || ! $subject->subscribed || null !== $suppressed ? Decision::Skip : Decision::Update;
    }
}
