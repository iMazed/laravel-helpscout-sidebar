<?php

namespace Imazed\HelpScoutSidebar\Emails;

use Imazed\HelpScoutSidebar\Contracts\ProvidesCustomerEmails;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

/**
 * Reads an email address straight out of the signed callback parameters.
 *
 * Help Scout's current app platform does not send one, so on a stock install
 * this provider returns nothing. It exists for two situations:
 *
 * 1. A callback URL that carries an email as a *signed* parameter.
 * 2. Local development, where you construct requests yourself.
 *
 * It reads through {@see HelpScoutContext::trusted()}, so any parameter you
 * added to your own callback URL and listed in `signature.ignore` is ignored
 * here on purpose: those values are not covered by the signature and a support
 * agent could edit them in the iframe URL. Resolving a customer from an
 * editable address would let anyone with iframe access look up arbitrary
 * addresses in the host application.
 */
class ContextEmailProvider implements ProvidesCustomerEmails
{
    /**
     * Signed parameter names checked, in order.
     *
     * @var array<int, string>
     */
    protected array $parameters = ['email', 'customerEmail', 'customer-email'];

    /**
     * @param  array<int, string>|null  $parameters  Override the parameters to check.
     */
    public function __construct(?array $parameters = null)
    {
        if ($parameters !== null) {
            $this->parameters = $parameters;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function emails(HelpScoutContext $context): array
    {
        $emails = [];

        foreach ($this->parameters as $parameter) {
            $value = $context->trusted($parameter);

            if (is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false) {
                $emails[] = trim($value);
            }
        }

        return $emails;
    }
}
