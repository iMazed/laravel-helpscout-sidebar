<?php

namespace Imazed\HelpScoutSidebar\Emails;

use Imazed\HelpScoutSidebar\Contracts\ProvidesCustomerEmails;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

/**
 * Returns a single hard-coded address, regardless of the conversation.
 *
 * A local development convenience: it lets the sidebar route render a real
 * record in a browser without a Help Scout account or API credentials. It
 * yields nothing unless `fallback_email` holds a valid address, and it ships
 * unset — the production warning lives with that option in the config file.
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
