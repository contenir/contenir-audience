<?php

declare(strict_types=1);

namespace Contenir\Audience\Suppression;

/**
 * Why a binding must not subscribe someone, as their service reported it.
 *
 * @api
 */
enum SuppressionReason: string
{
    /** They unsubscribed: only fresh consent brings them back */
    case Unsubscribed = 'unsubscribed';

    /** Their address bounced: fresh consent may bring them back, as it may work again */
    case Cleaned = 'cleaned';

    /** They marked an email as spam: never again */
    case Complaint = 'complaint';

    /** The service erased them: never again */
    case Forgotten = 'forgotten';

    /** Whether fresh consent is enough to bring them back */
    public function yieldsToConsent(): bool
    {
        return self::Unsubscribed === $this || self::Cleaned === $this;
    }
}
