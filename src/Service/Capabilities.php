<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

/**
 * What a service can do, so the shared rules know what to ask of it.
 *
 * @api
 */
final readonly class Capabilities
{
    /**
     * @param positive-int $batchSize The most people one request may carry
     */
    public function __construct(
        public int $batchSize,
        public DoubleOptIn $doubleOptIn,
        public bool $tags = false,
        public bool $signedWebhooks = false,
    ) {}
}
