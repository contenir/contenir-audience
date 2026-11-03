<?php

declare(strict_types=1);

namespace Contenir\Audience\Tests\TestAsset\Log;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps each entry logged, for tests that check what was said.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{mixed, string, array<array-key, mixed>}> Level, message and context, in order */
    public array $entries = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->entries[] = [$level, (string) $message, $context];
    }
}
