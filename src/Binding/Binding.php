<?php

declare(strict_types=1);

namespace Contenir\Audience\Binding;

use Contenir\Audience\Field\FieldMap;

/**
 * Which list a form or a section feeds, and how: the field holding the email, the fields sent
 * with it, the wording a sign-up agrees to, double opt-in, tags, what a delete does, and any
 * settings only one service understands.
 *
 * @api
 */
final readonly class Binding
{
    /** The source of a binding fed by the site's own sign-up forms */
    public const string SITE = 'site';

    /**
     * @param string                               $source  "site", or the CMS section path the binding syncs
     * @param list<string>                         $tags
     * @param array<string, string|int|float|bool> $options Settings only the binding's service reads
     *
     * @mago-expect lint:excessive-parameter-list A binding is all of these together, as its config names them.
     */
    public function __construct(
        public string $name,
        public string $list,
        public string $source = self::SITE,
        public string $emailField = 'email',
        public FieldMap $fields = new FieldMap(),
        public ?string $consent = null,
        public bool $doubleOptIn = true,
        public array $tags = [],
        public OnDelete $onDelete = OnDelete::Unsubscribe,
        public array $options = [],
    ) {}

    public function isFromSite(): bool
    {
        return self::SITE === $this->source;
    }

    public function option(string $name): string|int|float|bool|null
    {
        return $this->options[$name] ?? null;
    }
}
