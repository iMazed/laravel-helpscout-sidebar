<?php

namespace Imazed\HelpScoutSidebar\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

/**
 * A deliberately small read-only client for the Help Scout Mailbox API.
 *
 * This package needs exactly one thing from Help Scout — the email addresses
 * belonging to a customer ID — so this client does not attempt to be a general
 * SDK. It exposes what the sidebar needs and nothing more.
 *
 * Every method returns null on failure rather than throwing. The sidebar
 * renders inside an iframe a support agent is looking at while talking to a
 * customer; a stack trace there helps nobody. Failures are logged and the
 * sidebar degrades to its no-match state.
 *
 * @see https://developer.helpscout.com/mailbox-api/endpoints/customers/get/
 */
class MailboxApiClient
{
    public function __construct(
        protected HttpFactory $http,
        protected AccessTokenRepository $tokens,
        protected MailboxApiConfig $config,
        protected LoggerInterface $logger,
    ) {}

    /**
     * Whether the client has everything it needs to make a request.
     */
    public function isUsable(): bool
    {
        return $this->config->isUsable();
    }

    /**
     * Fetch a single customer by their Help Scout customer ID.
     *
     * @return array<string, mixed>|null The decoded customer, or null when
     *                                   unavailable, unknown, or rate limited.
     */
    public function customer(int $customerId): ?array
    {
        if (! $this->isUsable() || $customerId <= 0) {
            return null;
        }

        return $this->get($this->config->customerUrl($customerId));
    }

    /**
     * Perform an authenticated GET, retrying once if the token was rejected.
     *
     * @param  bool  $retryOnUnauthorized  False on the retry itself, to bound recursion to one extra call.
     * @return array<string, mixed>|null
     */
    protected function get(string $url, bool $retryOnUnauthorized = true): ?array
    {
        $token = $this->tokens->token();

        if ($token === null) {
            return null;
        }

        try {
            $response = $this->http
                ->asJson()
                ->timeout($this->config->timeout)
                ->connectTimeout($this->config->connectTimeout)
                ->withToken($token)
                ->get($url);
        } catch (ConnectionException $e) {
            $this->logger->warning('Help Scout sidebar could not reach the Mailbox API.', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        // A cached token can expire between storage and use, or be revoked in
        // Help Scout. Drop it and negotiate a new one exactly once.
        if ($response->status() === 401 && $retryOnUnauthorized) {
            $this->tokens->forget();

            return $this->get($url, retryOnUnauthorized: false);
        }

        // An unknown customer is an ordinary outcome, not a fault worth logging.
        if ($response->status() === 404) {
            return null;
        }

        if ($response->status() === 429) {
            $this->logger->warning('Help Scout sidebar hit the Mailbox API rate limit.', [
                'url' => $url,
            ]);

            return null;
        }

        if (! $response->successful()) {
            $this->logger->warning('Help Scout sidebar received an error from the Mailbox API.', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            return null;
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }
}
