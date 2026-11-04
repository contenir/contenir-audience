<?php

declare(strict_types=1);

namespace Contenir\Audience\Worker;

/**
 * Counts what one drain did, as it goes.
 *
 * @internal
 */
final class Tally
{
    public int     $sent     = 0;
    public int     $skipped  = 0;
    public int     $retried  = 0;
    public int     $failed   = 0;
    public int     $dead     = 0;
    public bool    $finished = true;
    public ?string $paused   = null;

    public function report(): DrainReport
    {
        return new DrainReport(
            $this->sent,
            $this->skipped,
            $this->retried,
            $this->failed,
            $this->dead,
            $this->finished,
            $this->paused,
        );
    }
}
