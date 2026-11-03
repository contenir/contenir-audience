<?php

declare(strict_types=1);

namespace Contenir\Audience\Policy;

/**
 * What is sent for a message, once its consent, the person's history and the binding are
 * weighed.
 *
 * @api
 */
enum Decision
{
    /** Join at once */
    case Subscribe;

    /** Join once they confirm by email */
    case Pending;

    /** Join again after unsubscribing, with fresh consent */
    case Resubscribe;

    /** Bring their values up to date, without changing whether they're subscribed */
    case Update;

    case Unsubscribe;

    case Archive;

    /** Send nothing */
    case Skip;
}
