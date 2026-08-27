<?php

namespace Imazed\HelpScoutSidebar\Api;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

/**
 * Obtains and caches a Mailbox API access token using the OAuth2 client
 * credentials flow.
 *
 * Client credentials is the flow Help Scout documents for internal,
 * server-to-server integrations: no browser redirect, no per-user consent, no
 * refresh token. The host application exchanges its app ID and secret for an
 * access token and repeats that exchange when the token expires.
 *
 * Tokens are cached because Help Scout issues them with a long lifetime
 * (roughly two days at the time of writing) and the sidebar renders on every
 * conversation view. Fetching one per request would be both slow and a waste of
 * the account's rate limit.
 *
 * @see https://developer.helpscout.com/mailbox-api/overview/authentication/
 */
class AccessTokenRepository
{
    public function __construct(
        protected HttpFactory $http,
        protected CacheRepository $cache,
        protected MailboxApiConfig $config,
        protected LoggerInterface $logger,
    ) {}

    /**
     * A usable access token, or null when the API is unavailable.
     *
     * Null covers every failure mode — disabled, unconfigured, network error,
     * rejected credentials — because callers treat all of them the same way:
     * skip the API and fall through to the next email source.
     */
    public function token(): ?string
    {
        if (! $this->config->isUsable()) {
            return null;
        }

        if (($cached = $this->cached()) !== null) {
            return $cached;
        }

        $store = $this->cache->getStore();

        // Without a lock-capable cache store, a burst of concurrent sidebar
        // loads on a cold cache would each request their own token. That is
        // wasteful but harmless, so it is worth doing rather than refusing.
        if (! $store instanceof LockProvider) {
            return $this->request();
        }

        $lock = $store->lock($this->cacheKey().':lock', 10);

        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            // Someone else is mid-fetch and took longer than we are willing to
            // wait. Use whatever they managed to store rather than piling on.
            return $this->cached();
        }

        try {
            return $this->cached() ?? $this->request();
        } finally {
            $lock->release();
        }
    }

    /**
     * Discard the cached token.
     *
     * Called when the API rejects a token with a 401 so the next attempt
     * negotiates a fresh one instead of replaying a dead credential.
     */
    public function forget(): void
    {
        $this->cache->forget($this->cacheKey());
    }

    /**
     * Whether a token is currently cached.
     *
     * Reported on the diagnostics screen so you can tell a credentials problem
     * apart from a cold cache. The token itself is never exposed.
     */
    public function isCached(): bool
    {
        return $this->cached() !== null;
    }

    /**
     * The cache key for the current credentials.
     *
     * The app ID is hashed into the key so that rotating credentials cannot
     * accidentally reuse a token issued to the previous app.
     */
    public function cacheKey(): string
    {
        return $this->config->cachePrefix.':access-token:'.sha1((string) $this->config->appId);
    }

    /**
     * The cached token, if one is present and non-empty.
     */
    protected function cached(): ?string
    {
        $token = $this->cache->get($this->cacheKey());

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Exchange the client credentials for a new access token and cache it.
     */
    protected function request(): ?string
    {
        try {
            $response = $this->http
                ->asJson()
                ->timeout($this->config->timeout)
                ->connectTimeout($this->config->connectTimeout)
                ->post($this->config->tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->config->appId,
                    'client_secret' => $this->config->appSecret,
                ]);
        } catch (ConnectionException $e) {
            $this->logger->warning('Help Scout sidebar could not reach the token endpoint.', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            $this->logger->warning('Help Scout sidebar was refused an access token.', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            $this->logger->warning('Help Scout sidebar received a token response without an access token.');

            return null;
        }

        $this->cache->put($this->cacheKey(), $token, $this->lifetime($response->json('expires_in')));

        return $token;
    }

    /**
     * How long to cache a token, in seconds.
     *
     * Help Scout's documented token lifetime has changed before, so the value
     * from the response is preferred and shortened by a leeway to avoid racing
     * the expiry. When the response omits it, fall back to an hour: short
     * enough to be safe, long enough to be useful.
     */
    protected function lifetime(mixed $expiresIn): int
    {
        $expiresIn = is_numeric($expiresIn) ? (int) $expiresIn : 0;

        if ($expiresIn <= 0) {
            return 3600;
        }

        return max(60, $expiresIn - $this->config->tokenCacheLeeway);
    }
}
