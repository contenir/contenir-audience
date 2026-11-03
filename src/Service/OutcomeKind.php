<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

/**
 * What became of one person in a request to a service.
 *
 * @api
 */
enum OutcomeKind
{
    /** The service did what was asked */
    case Done;

    /** The service couldn't take it just now (429, 5xx, a timeout): send it again later */
    case Retry;

    /** The service refused what was sent (a validation error): sending it again won't help */
    case Failed;

    /** The service won't hold this address again (Mailchimp's "forgotten", a deleted subscriber) */
    case Forgotten;
}
