<?php

declare(strict_types=1);

namespace Contenir\Audience\Binding;

/**
 * What happens at the service when a bound record is deleted in the CMS.
 *
 * @api
 */
enum OnDelete: string
{
    /** The person stays on the list, unsubscribed */
    case Unsubscribe = 'unsubscribe';

    /** The person leaves the list's active members and can be restored */
    case Archive = 'archive';

    /** Nothing changes at the service */
    case Keep = 'keep';
}
