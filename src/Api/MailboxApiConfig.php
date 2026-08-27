<?php

namespace Imazed\HelpScoutSidebar\Api;

/**
 * Immutable snapshot of the Mailbox API configuration.
 *
 * Bundling these values keeps the API classes free of `config()` lookups, which
 * makes them straightforward to construct in tests.
 */
final readonly class MailboxApiConfig
{
    public function __construct(
        public bool $enabled = false,
        public string $baseUrl = 'https://api.helpscout.net/v2',
        public string $tokenUrl = 'https://api.helpscout.net/v2/oauth2/token',
        public ?string $appId = null,
        public ?string $appSecret = null,
        public int $timeout = 5,
        public int $connectTimeout = 2,
        public string $cachePrefix = 'helpscout-sidebar',
        public int $customerCacheTtl = 600,
        public int $missingCacheTtl = 60,
        public int $tokenCacheLeeway = 60,
    ) {}

    /**
     * Build from the `helpscout-sidebar.api` config array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $cache = is_array($config['cache'] ?? null) ? $config['cache'] : [];

        return new self(
            enabled: (bool) ($config['enabled'] ?? false),
            baseUrl: rtrim((string) ($config['base_url'] ?? 'https://api.helpscout.net/v2'), '/'),
            tokenUrl: (string) ($config['token_url'] ?? 'https://api.helpscout.net/v2/oauth2/token'),
            appId: self::nullableString($config['app_id'] ?? null),
            appSecret: self::nullableString($config['app_secret'] ?? null),
            timeout: (int) ($config['timeout'] ?? 5),
            connectTimeout: (int) ($config['connect_timeout'] ?? 2),
            cachePrefix: (string) ($cache['prefix'] ?? 'helpscout-sidebar'),
            customerCacheTtl: (int) ($cache['customer_ttl'] ?? 600),
            missingCacheTtl: (int) ($cache['missing_ttl'] ?? 60),
            tokenCacheLeeway: (int) ($cache['token_leeway'] ?? 60),
        );
    }

    /**
     * Whether the API is switched on *and* has credentials to use.
     *
     * The package treats "enabled but incomplete" as off rather than as an
     * error, so a half-finished .env degrades to the no-match state instead of
     * breaking the sidebar.
     */
    public function isUsable(): bool
    {
        return $this->enabled
            && $this->appId !== null
            && $this->appSecret !== null;
    }

    public function customerUrl(int $customerId): string
    {
        return "{$this->baseUrl}/customers/{$customerId}";
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return trim($value) === '' ? null : trim($value);
    }
}
