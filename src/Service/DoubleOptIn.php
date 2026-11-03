<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

/**
 * Where a service decides whether a new subscriber must confirm by email first.
 *
 * @api
 */
enum DoubleOptIn
{
    /** Each request says whether this person must confirm (Mailchimp's pending status) */
    case PerRequest;

    /** The list's own setting decides, for everyone added to it (Campaign Monitor) */
    case PerList;

    /** The service has no confirmation step of its own */
    case None;
}
