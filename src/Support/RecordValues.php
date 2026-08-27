<?php

namespace Imazed\HelpScoutSidebar\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Psr\Log\LoggerInterface;

/**
 * Reads a value off the resolved record, by dotted path or by class.
 *
 * Configuration cannot contain closures, because it has to survive
 * `config:cache`. A dotted path is the cacheable way to say where a value
 * comes from, and it covers attributes, accessors, relations, and arrays
 * without inventing anything Laravel developers do not already know.
 *
 * A path is walked one segment at a time rather than handed to `data_get()`
 * whole, because each step is where the sensitive-attribute check has to
 * happen — see {@see self::refuses()}.
 *
 * When a path is not enough, a value may instead name an invokable class,
 * which is called with the record — the escape hatch that keeps the
 * configuration format from growing into a programming language.
 */
class RecordValues
{
    /**
     * Attribute names never rendered, whatever the configuration says.
     *
     * Eloquent's own `$hidden` is respected first and is the mechanism that
     * matters, because an application has already declared its secrets there.
     * This list is a floor for models that never set it: the sidebar is seen
     * by every agent in the inbox, and a password hash reaching one of them
     * because of a typo in a config file is not a recoverable mistake.
     *
     * @var array<int, string>
     */
    public const NEVER_RENDER = [
        'password',
        'password_hash',
        'remember_token',
        'api_token',
        'access_token',
        'refresh_token',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function __construct(
        protected ?LoggerInterface $logger = null,
        protected ?Container $container = null,
    ) {}

    /**
     * Resolve a configured value against the record.
     *
     * Returns null for anything missing, refused, or unreadable. Callers treat
     * null as "do not render this", so a mistake in configuration costs a row
     * rather than the whole sidebar.
     */
    public function get(mixed $source, mixed $record): mixed
    {
        if (! is_string($source) || trim($source) === '') {
            return null;
        }

        $source = trim($source);

        if (class_exists($source)) {
            return $this->fromClass($source, $record);
        }

        return $this->fromPath($source, $record);
    }

    /**
     * Whether a configured path resolves to something truthy.
     *
     * Used for the `when` key on a section or an item. Deliberately plain
     * truthiness: the moment a condition needs an operator, the answer is a
     * BuildsSidebar implementation, not a richer configuration format.
     */
    public function truthy(mixed $source, mixed $record): bool
    {
        $value = $this->get($source, $record);

        if (is_countable($value)) {
            return count($value) > 0;
        }

        return (bool) $value;
    }

    /**
     * Walk a dotted path, checking each step before reading it.
     */
    protected function fromPath(string $path, mixed $record): mixed
    {
        $current = $record;

        foreach (explode('.', $path) as $segment) {
            if ($current === null) {
                return null;
            }

            if ($this->refuses($current, $segment)) {
                $this->logger?->warning('Help Scout sidebar refused to read a protected attribute.', [
                    'path' => $path,
                    'attribute' => $segment,
                ]);

                return null;
            }

            $current = $this->read($current, $segment);
        }

        return $current;
    }

    /**
     * Whether this attribute must not be read off this record.
     *
     * Checked per segment rather than once for the whole path, so that
     * `subscription.secret` is refused as surely as `secret` is.
     */
    protected function refuses(mixed $record, string $segment): bool
    {
        if (in_array(mb_strtolower($segment), static::NEVER_RENDER, true)) {
            return true;
        }

        return $record instanceof Model
            && in_array($segment, $record->getHidden(), true);
    }

    /**
     * Read one segment from whatever the previous step produced.
     */
    protected function read(mixed $record, string $segment): mixed
    {
        if (is_array($record)) {
            return $record[$segment] ?? null;
        }

        if ($record instanceof Model) {
            // getAttribute covers columns, accessors, casts, and relations.
            return $record->getAttribute($segment);
        }

        if (is_object($record)) {
            return $record->{$segment} ?? null;
        }

        return null;
    }

    /**
     * Call an invokable class with the record.
     *
     * Built through the container when one was injected, so a value class can
     * take constructor dependencies; without one it must construct bare.
     */
    protected function fromClass(string $class, mixed $record): mixed
    {
        try {
            $resolver = $this->container !== null ? $this->container->make($class) : new $class;

            if (! is_callable($resolver)) {
                $this->logger?->warning('Help Scout sidebar value class is not invokable.', ['class' => $class]);

                return null;
            }

            return $resolver($record);
        } catch (\Throwable $e) {
            // An exception here would surface to an agent mid-conversation.
            $this->logger?->error('Help Scout sidebar value class threw.', [
                'class' => $class,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
