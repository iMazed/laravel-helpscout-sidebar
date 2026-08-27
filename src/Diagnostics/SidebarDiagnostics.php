<?php

namespace Imazed\HelpScoutSidebar\Diagnostics;

use Imazed\HelpScoutSidebar\Api\AccessTokenRepository;
use Imazed\HelpScoutSidebar\Api\MailboxApiConfig;
use Imazed\HelpScoutSidebar\Contracts\CustomerResolver;
use Imazed\HelpScoutSidebar\Resolvers\EmailCustomerResolver;
use Imazed\HelpScoutSidebar\Sidebar\Section;
use Imazed\HelpScoutSidebar\Sidebar\Sidebar;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

/**
 * Renders an explanation of *why* no customer was matched.
 *
 * Resolution has several places it can quietly come up empty: the callback
 * carried no customer ID, the API is switched off, credentials are wrong, the
 * customer has no email in Help Scout, or the address simply does not exist in
 * the host application. From the outside all of these look identical — an empty
 * sidebar — so this screen names which one happened.
 *
 * Enabled with `HELPSCOUT_SIDEBAR_DEBUG=true`. Keep it off in production: it
 * discloses your model and column names, and lists customer email addresses.
 */
class SidebarDiagnostics
{
    public function __construct(
        protected MailboxApiConfig $apiConfig,
        protected AccessTokenRepository $tokens,
    ) {}

    /**
     * Build the diagnostics sidebar for a request that matched nothing.
     */
    public function build(HelpScoutContext $context, CustomerResolver $resolver): Sidebar
    {
        return Sidebar::make('No customer matched')
            ->subtitle('Diagnostics are enabled. Set HELPSCOUT_SIDEBAR_DEBUG=false to hide this.')
            ->section('Callback', fn (Section $section) => $this->callback($section, $context))
            ->section('Mailbox API', fn (Section $section) => $this->api($section))
            ->section('Resolution', fn (Section $section) => $this->resolution($section, $context, $resolver));
    }

    /**
     * What Help Scout actually sent.
     */
    protected function callback(Section $section, HelpScoutContext $context): void
    {
        $section
            ->row('Customer ID', $context->customerId() ?? 'Not sent')
            ->row('Conversation ID', $context->conversationId() ?? 'Not sent')
            ->row('Parameters', $context->keys() === [] ? 'None' : implode(', ', $context->keys()));

        // Unsigned parameters are excluded from customer resolution by design,
        // so seeing one here explains why its value was not used.
        $section->when($context->untrustedKeys(), function (Section $section) use ($context): void {
            $section->row('Unsigned (ignored for resolution)', implode(', ', $context->untrustedKeys()));
        });
    }

    /**
     * Whether the API is in a position to answer.
     */
    protected function api(Section $section): void
    {
        $section
            ->row('Enabled', $this->apiConfig->enabled)
            ->row('Credentials', $this->apiConfig->appId !== null && $this->apiConfig->appSecret !== null
                ? 'Present'
                : 'Missing (set HELPSCOUT_SIDEBAR_APP_ID and HELPSCOUT_SIDEBAR_APP_SECRET)');

        if (! $this->apiConfig->isUsable()) {
            $section->badge('API lookups are not running', 'warning');

            return;
        }

        $section->row('Access token', $this->tokens->isCached()
            ? 'Cached'
            : 'Not cached (fetched on next lookup, or the last attempt failed)');
    }

    /**
     * Which email sources produced which addresses, and what was queried.
     */
    protected function resolution(Section $section, HelpScoutContext $context, CustomerResolver $resolver): void
    {
        $section->row('Resolver', $resolver::class);

        if (! $resolver instanceof EmailCustomerResolver) {
            $section->badge('Custom resolver — no further detail available', 'neutral');

            return;
        }

        $section->row('Looks up', $resolver->target());

        if (! $resolver->isQueryable()) {
            $section->badge('Model is missing or is not an Eloquent model', 'negative');

            return;
        }

        $candidates = $resolver->candidatesByProvider($context);
        $found = false;

        foreach ($candidates as $candidate) {
            $found = $found || $candidate['emails'] !== [];

            $section->row(
                class_basename($candidate['provider']),
                $candidate['emails'] === [] ? 'No addresses' : implode(', ', $candidate['emails']),
            );
        }

        $section->badge(
            $found
                ? 'Addresses were queried but no record matched'
                : 'No address to look up — nothing was queried',
            'warning',
        );
    }
}
