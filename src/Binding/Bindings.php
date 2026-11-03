<?php

declare(strict_types=1);

namespace Contenir\Audience\Binding;

use Contenir\Audience\Exception\InvalidBinding;
use Contenir\Audience\Exception\UnknownBinding;

use function array_values;
use function is_array;

/**
 * A site's bindings for one service, by name, as its config lists them.
 *
 * @api
 */
final readonly class Bindings
{
    /**
     * @param array<string, Binding> $bindings
     */
    private function __construct(
        private array $bindings,
    ) {}

    /**
     * @param array<array-key, mixed> $config Each binding's config, by its name
     * @param OnDelete                $onDelete What a delete does when a binding doesn't say: each service has its own default
     *
     * @throws InvalidBinding
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; each binding's is checked as a list.
     */
    public static function fromConfig(array $config, OnDelete $onDelete = OnDelete::Unsubscribe): self
    {
        $bindings = [];
        foreach ($config as $name => $binding) {
            $name = (string) $name;
            if (! is_array($binding)) {
                throw InvalidBinding::because($name, 'needs its settings as a list');
            }
            $bindings[$name] = BindingReader::read($name, $binding, $onDelete);
        }

        return new self($bindings);
    }

    /**
     * @return list<Binding>
     */
    public function all(): array
    {
        return array_values($this->bindings);
    }

    /**
     * The bindings that sync the CMS section $path.
     *
     * @return list<Binding>
     */
    public function forSection(string $path): array
    {
        $found = [];
        foreach ($this->bindings as $binding) {
            if ($path !== $binding->source) {
                continue;
            }

            $found[] = $binding;
        }

        return $found;
    }

    /**
     * @throws UnknownBinding
     */
    public function get(string $name): Binding
    {
        return $this->bindings[$name] ?? throw UnknownBinding::named($name);
    }
}
