<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

/**
 * How a person joins a list: straight away, or once they confirm by email.
 *
 * @api
 */
enum PersonStatus
{
    case Subscribed;
    case Pending;
}
