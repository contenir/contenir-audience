<?php

declare(strict_types=1);

namespace Contenir\Audience\Outbox;

/**
 * Where a message in the outbox stands.
 *
 * @api
 */
enum OutboxState: string
{
    /** Due now, or after its next attempt time */
    case Waiting = 'waiting';

    /** Sent, or nothing needed sending; its email and values are cleared */
    case Done = 'done';

    /** The service refused it, and sending it again won't help */
    case Failed = 'failed';

    /** It went on failing until its retries ran out */
    case Dead = 'dead';
}
