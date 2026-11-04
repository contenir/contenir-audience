<?php

declare(strict_types=1);

namespace Contenir\Audience\Worker;

/**
 * What one drain of the outbox did: how many messages were sent, needed nothing, will be tried
 * again, failed, or ran out of retries; whether it got through everything due; and, when the
 * service refused the connection, why it stopped.
 *
 * @api
 */
final readonly class DrainReport
{
    /**
     * @mago-expect lint:excessive-parameter-list One count per way a message can end a drain.
     */
    public function __construct(
        public int $sent,
        public int $skipped,
        public int $retried,
        public int $failed,
        public int $dead,
        public bool $finished,
        public ?string $paused,
    ) {}

    public function isPaused(): bool
    {
        return null !== $this->paused;
    }
}
