<?php

namespace Imazed\HelpScoutSidebar\Emails;

use Imazed\HelpScoutSidebar\Contracts\ProvidesCustomerEmails;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

/**
 * Returns a single hard-coded address, regardless of the conversation.
 *
 * This is a local development convenience and nothing more. It lets you open
 * the sidebar route in a browser and see a realistic render without a Help
 * Scout account or API credentials.
 *
 * Leaving it configured in production means every conversation resolves to the
 * same record, so the sidebar shows one customer's data to agents handling
 * everybody else's tickets. The provider yields nothing at all unless
 * `fallback_email` holds a valid address, and it ships unset.
 */
class FallbackEmailProvider implements ProvidesCustomerEmails
{
    public function __construct(protected ?string $email = null) {}

    /**
     * {@inheritDoc}
     */
    public function emails(HelpScoutContext $context): array
    {
        if (! is_string($this->email)) {
            return [];
        }

        $email = trim($this->email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [];
        }

        return [$email];
    }
}
