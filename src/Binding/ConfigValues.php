<?php

declare(strict_types=1);

namespace Contenir\Audience\Binding;

use Contenir\Audience\Exception\InvalidBinding;

use function is_array;
use function is_bool;
use function is_string;

/**
 * Typed reads of one binding's config, each value refused with the binding's name and key when
 * it isn't what the binding needs, and a default when the key is left out.
 *
 * @internal
 */
final readonly class ConfigValues
{
    /**
     * @param array<array-key, mixed> $config
     */
    public function __construct(
        private string $name,
        private array $config,
    ) {}

    /**
     * @throws InvalidBinding
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; this checks the one it reads.
     */
    public function flag(string $key, bool $default): bool
    {
        $value = $this->config[$key] ?? $default;

        return is_bool($value)
            ? $value
            : throw InvalidBinding::because($this->name, "needs \"{$key}\" as true or false");
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws InvalidBinding
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; this checks the one it reads.
     */
    public function map(string $key): array
    {
        $value = $this->config[$key] ?? [];

        return is_array($value) ? $value : throw InvalidBinding::because($this->name, "needs \"{$key}\" as a list");
    }

    /**
     * @throws InvalidBinding
     */
    public function onDelete(OnDelete $default): OnDelete
    {
        return (
            OnDelete::tryFrom((string) $this->text('on_delete', $default->value))
                ?? throw InvalidBinding::because($this->name, 'needs "on_delete" as unsubscribe, archive or keep')
        );
    }

    /**
     * @return array<string, string|int|float|bool>
     *
     * @throws InvalidBinding
     */
    public function options(): array
    {
        return ConfigLists::options($this->name, $this->map('options'));
    }

    /**
     * @throws InvalidBinding
     */
    public function required(string $key): string
    {
        return $this->text($key) ?? throw InvalidBinding::because($this->name, "needs \"{$key}\"");
    }

    /**
     * @return list<string>
     *
     * @throws InvalidBinding
     */
    public function tags(): array
    {
        return ConfigLists::tags($this->name, $this->map('tags'));
    }

    /**
     * @throws InvalidBinding
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; this checks the one it reads.
     */
    public function text(string $key, ?string $default = null): ?string
    {
        $value = $this->config[$key] ?? $default;
        if (null === $value || is_string($value) && '' !== $value) {
            return $value;
        }

        throw InvalidBinding::because($this->name, "needs \"{$key}\" as text");
    }
}
