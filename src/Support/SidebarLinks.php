<?php

namespace Imazed\HelpScoutSidebar\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Psr\Log\LoggerInterface;

/**
 * Turns the configured `links` into links for one resolved customer.
 *
 * These are the icons across the top of the sidebar. They live in
 * configuration rather than in a builder because they are the part of the
 * sidebar most likely to be wanted by someone who has not written a builder
 * at all: an agent looking at a conversation usually wants a way through to
 * the same customer in the admin, and that is a URL, not a design decision.
 *
 * URLs may carry placeholders, since a link to your admin root helps nobody:
 *
 *   {id}               the resolved record's key
 *   {email}            the resolved record's email address
 *   {customer_id}      Help Scout's customer ID
 *   {conversation_id}  Help Scout's conversation ID
 *
 * A link whose placeholder cannot be filled is dropped rather than rendered
 * with a hole in it, because a broken link in a support tool costs an agent a
 * click and a moment of doubt about whether the sidebar is working at all.
 *
 * Every drop is logged. Silently discarding configuration is how someone
 * spends an afternoon wondering why the icons they configured never appear.
 */
class SidebarLinks
{
    /**
     * Resolve every configured link that can be filled in.
     *
     * The base for relative URLs is passed in rather than read from `config()`
     * here, so this class stays constructible without a booted framework —
     * {@see self::baseFrom()} is how callers with a config repository get it.
     *
     * @param  array<int, array<string, mixed>>  $configured
     * @return array<int, array{label: string, url: string, icon: string|null}>
     */
    public static function resolve(
        array $configured,
        mixed $customer,
        HelpScoutContext $context,
        ?LoggerInterface $logger = null,
        ?string $base = null,
    ): array {
        $replacements = static::replacements($customer, $context);
        $links = [];

        foreach ($configured as $index => $link) {
            if (! is_array($link)) {
                static::drop($logger, $index, 'the entry is not an array');

                continue;
            }

            $label = $link['label'] ?? null;

            if (! is_string($label) || trim($label) === '') {
                static::drop($logger, $index, 'no label');

                continue;
            }

            $url = static::fill($link['url'] ?? null, $replacements, $base);

            if ($url === null) {
                static::drop($logger, $index, sprintf(
                    'the URL is missing or has a placeholder that could not be filled (available: %s)',
                    static::available($replacements),
                ));

                continue;
            }

            $links[] = [
                'label' => trim($label),
                'url' => $url,
                'icon' => is_string($link['icon'] ?? null) ? $link['icon'] : null,
            ];
        }

        return $links;
    }

    /**
     * Fill one URL template for a record, or null when it cannot be filled.
     *
     * Shared with the configured builder so that a link in a section behaves
     * exactly like a link in the header, rather than being a second, subtly
     * different set of placeholder rules.
     */
    public static function url(string $template, mixed $customer, HelpScoutContext $context, ?string $base = null): ?string
    {
        return static::fill($template, static::replacements($customer, $context), $base);
    }

    /**
     * The base URL for relative links, read off the configuration.
     *
     * `link_base` wins so that links can point somewhere other than the
     * application serving the sidebar — a separate admin, say — and `app.url`
     * covers everyone else without another setting to discover.
     */
    public static function baseFrom(ConfigRepository $config): ?string
    {
        $base = $config->get('helpscout-sidebar.link_base') ?: $config->get('app.url');

        return is_string($base) && trim($base) !== '' ? trim($base) : null;
    }

    /**
     * Say why a configured link will not be shown.
     */
    protected static function drop(?LoggerInterface $logger, int|string $index, string $reason): void
    {
        $logger?->warning('Help Scout sidebar dropped a configured header link.', [
            'index' => $index,
            'reason' => $reason,
        ]);
    }

    /**
     * The placeholders that could be filled for this request, for the log.
     *
     * @param  array<string, string|null>  $replacements
     */
    protected static function available(array $replacements): string
    {
        $usable = array_keys(array_filter(
            $replacements,
            static fn (?string $value): bool => $value !== null && $value !== '',
        ));

        return $usable === [] ? 'none' : '{'.implode('}, {', $usable).'}';
    }

    /**
     * Substitute placeholders, or return null when one cannot be filled.
     *
     * @param  array<string, string|null>  $replacements
     */
    protected static function fill(mixed $url, array $replacements, ?string $base = null): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        foreach ($replacements as $token => $value) {
            if (! str_contains($url, '{'.$token.'}')) {
                continue;
            }

            if ($value === null || $value === '') {
                return null;
            }

            $url = str_replace('{'.$token.'}', rawurlencode($value), $url);
        }

        // Anything still wrapped in braces was never a placeholder this
        // package knows about, and would reach the agent as a broken URL.
        if (preg_match('/\{[a-z_]+\}/', $url) === 1) {
            return null;
        }

        return static::absolute($url, $base);
    }

    /**
     * Resolve a relative URL against the configured base.
     *
     * Writing every link out in full invites a typo in the half that never
     * changes, and means the same configuration cannot be right in local and
     * in production at once. A relative path avoids both.
     *
     * Anything carrying a scheme is returned untouched — including mailto:
     * and tel:, and any external destination.
     */
    protected static function absolute(string $url, ?string $base): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) === 1 || str_starts_with($url, '//')) {
            return $url;
        }

        if ($base === null) {
            return $url;
        }

        return rtrim($base, '/').'/'.ltrim($url, '/');
    }

    /**
     * @return array<string, string|null>
     */
    protected static function replacements(mixed $customer, HelpScoutContext $context): array
    {
        return [
            'id' => static::stringOrNull(static::attribute($customer, 'id')),
            'email' => static::stringOrNull(static::attribute($customer, 'email')),
            'customer_id' => static::stringOrNull($context->customerId()),
            'conversation_id' => static::stringOrNull($context->conversationId()),
        ];
    }

    /**
     * Read a property from whatever the resolver returned.
     *
     * The resolved record is `mixed` by design — an Eloquent model for most
     * installations, but not necessarily — so this stays deliberately generous.
     */
    protected static function attribute(mixed $customer, string $key): mixed
    {
        if ($customer instanceof Model) {
            return $key === 'id' ? $customer->getKey() : $customer->getAttribute($key);
        }

        if (is_array($customer)) {
            return $customer[$key] ?? null;
        }

        if (is_object($customer)) {
            return $customer->{$key} ?? null;
        }

        return null;
    }

    protected static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
