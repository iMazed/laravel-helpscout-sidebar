<?php

namespace Imazed\HelpScoutSidebar\Emails;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use Imazed\HelpScoutSidebar\Api\MailboxApiClient;
use Imazed\HelpScoutSidebar\Api\MailboxApiConfig;
use Imazed\HelpScoutSidebar\Contracts\ProvidesCustomerEmails;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

/**
 * Turns the signed `customer-id` into email addresses via the Mailbox API.
 *
 * This is the provider that makes production resolution work. The callback
 * gives us a Help Scout customer ID and nothing else identifying; the Mailbox
 * API is the only trustworthy way to learn what that ID means.
 *
 * Results are cached per customer ID. A support agent moving through a queue
 * re-renders this sidebar constantly, and Help Scout's rate limits are per
 * minute, so an uncached lookup would burn allowance on the same handful of
 * customers. Empty results are cached too, on a shorter TTL, so a customer with
 * no email address does not trigger a request on every render.
 */
class MailboxApiEmailProvider implements ProvidesCustomerEmails
{
    public function __construct(
        protected MailboxApiClient $client,
        protected CacheRepository $cache,
        protected MailboxApiConfig $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function emails(HelpScoutContext $context): array
    {
        $customerId = $context->customerId();

        if ($customerId === null || ! $this->client->isUsable()) {
            return [];
        }

        $key = $this->cacheKey($customerId);
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $customer = $this->client->customer($customerId);

        // A failed lookup is not cached: a network blip or an expired token
        // should not suppress retries for the whole TTL.
        if ($customer === null) {
            return [];
        }

        $emails = $this->extractEmails($customer);

        $this->cache->put(
            $key,
            $emails,
            $emails === [] ? $this->config->missingCacheTtl : $this->config->customerCacheTtl,
        );

        return $emails;
    }

    /**
     * The cache key holding the resolved addresses for a Help Scout customer.
     */
    public function cacheKey(int $customerId): string
    {
        return $this->config->cachePrefix.':customer-emails:'.$customerId;
    }

    /**
     * Pull email addresses out of a Mailbox API customer payload.
     *
     * The documented shape nests them under `_embedded.emails[].value`. A
     * top-level `emails` key is accepted as well, because Help Scout embeds the
     * same collection differently depending on the endpoint.
     *
     * @param  array<string, mixed>  $customer
     * @return array<int, string>
     */
    protected function extractEmails(array $customer): array
    {
        $entries = Arr::get($customer, '_embedded.emails');

        if (! is_array($entries)) {
            $entries = Arr::get($customer, 'emails');
        }

        if (! is_array($entries)) {
            return [];
        }

        $emails = [];

        foreach ($entries as $entry) {
            $value = is_array($entry) ? ($entry['value'] ?? null) : $entry;

            if (is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false) {
                $emails[] = trim($value);
            }
        }

        return $emails;
    }
}
