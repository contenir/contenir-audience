<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

/**
 * What a service reports about a person through its webhook.
 *
 * @api
 */
enum RemoteEventKind
{
    /** They unsubscribed at the service */
    case Unsubscribed;

    /** The service stopped sending to the address after it bounced */
    case Cleaned;

    /** They marked an email as spam */
    case Complaint;

    /** The service erased them and won't take the address again */
    case Forgotten;

    /** They changed their address at the service */
    case EmailChanged;
}
