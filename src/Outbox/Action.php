<?php

declare(strict_types=1);

namespace Contenir\Audience\Outbox;

/**
 * What a message in the outbox asks for. The consent policy turns it into what is sent.
 *
 * @api
 */
enum Action: string
{
    /** Join the list, with the consent that came with the message */
    case Subscribe = 'subscribe';

    /** Bring an existing member's values up to date */
    case Update = 'update';

    /** The record was deleted: do what the binding says on delete */
    case Remove = 'remove';
}
