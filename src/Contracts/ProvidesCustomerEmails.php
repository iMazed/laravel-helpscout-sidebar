<?php

namespace Imazed\HelpScoutSidebar\Contracts;

use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

/**
 * One source of candidate email addresses for a conversation.
 *
 * EmailCustomerResolver walks an ordered list of these, querying the host
 * application once per address until something matches. Providers are ordered
 * cheapest-first in the shipped configuration, so a provider that costs an HTTP
 * request only runs when the free ones came back empty.
 *
 * A provider must return only addresses it can vouch for. Anything derived from
 * a value the browser could have altered does not belong here; see the "Trust
 * boundaries" section of the README.
 */
interface ProvidesCustomerEmails
{
    /**
     * Candidate email addresses for this conversation, in preference order.
     *
     * Returning an empty array is normal and means "I have nothing for this
     * conversation". Providers must swallow their own failures rather than
     * throwing.
     *
     * @return array<int, string>
     */
    public function emails(HelpScoutContext $context): array;
}
