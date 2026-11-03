<?php

declare(strict_types=1);

namespace Contenir\Audience\Service;

/**
 * A field a list holds for each person, as a binding's field map names it.
 *
 * @api
 */
final readonly class RemoteField
{
    /**
     * @param string $type The service's own name for the field's type, such as "text" or "date"
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $type,
        public bool $required = false,
    ) {}
}
