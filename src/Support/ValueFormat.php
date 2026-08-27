<?php

namespace Imazed\HelpScoutSidebar\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * The formats a configured item may ask for.
 *
 * Deliberately a fixed vocabulary rather than a pattern language. A raw column
 * value reads badly in a sidebar — `1` where an agent needs "Yes", an ISO
 * timestamp where they need "3 days ago" — and without these, people write a
 * builder for the sole purpose of calling number_format().
 *
 * Every format degrades to the value as a string rather than throwing. A date
 * column holding something unparseable is a data problem; it should not take
 * the sidebar down while a customer waits.
 */
class ValueFormat
{
    /**
     * @var array<int, string>
     */
    public const NAMES = ['date', 'datetime', 'diff', 'currency', 'number', 'percent', 'bool'];

    /**
     * Apply a format, then any prefix and suffix.
     *
     * @param  array<string, mixed>  $item
     */
    public static function apply(mixed $value, array $item): ?string
    {
        if ($value === null) {
            return null;
        }

        $formatted = static::format($value, is_string($item['format'] ?? null) ? $item['format'] : null);

        if ($formatted === null || $formatted === '') {
            return null;
        }

        $prefix = is_string($item['prefix'] ?? null) ? $item['prefix'] : '';
        $suffix = is_string($item['suffix'] ?? null) ? $item['suffix'] : '';

        return $prefix.$formatted.$suffix;
    }

    protected static function format(mixed $value, ?string $format): ?string
    {
        return match ($format) {
            'date' => static::date($value, fn (Carbon $date): string => $date->toFormattedDateString()),
            'datetime' => static::date($value, fn (Carbon $date): string => $date->toDayDateTimeString()),
            'diff' => static::date($value, fn (Carbon $date): string => $date->diffForHumans()),
            'currency' => is_numeric($value) ? number_format((float) $value, 2) : static::plain($value),
            'number' => is_numeric($value) ? number_format((float) $value) : static::plain($value),
            'percent' => is_numeric($value) ? rtrim(rtrim(number_format((float) $value, 1), '0'), '.').'%' : static::plain($value),
            'bool' => $value ? 'Yes' : 'No',
            default => static::plain($value),
        };
    }

    /**
     * @param  callable(Carbon): string  $formatter
     */
    protected static function date(mixed $value, callable $formatter): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $formatter(Carbon::instance($value));
        }

        if (! is_string($value) && ! is_int($value)) {
            return static::plain($value);
        }

        try {
            return $formatter(Carbon::parse($value));
        } catch (\Throwable) {
            // Unparseable is not exceptional enough to break the sidebar.
            return static::plain($value);
        }
    }

    protected static function plain(mixed $value): ?string
    {
        return match (true) {
            is_bool($value) => $value ? 'Yes' : 'No',
            is_scalar($value) => (string) $value,
            $value instanceof \Stringable => (string) $value,
            default => null,
        };
    }
}
