<?php

namespace Imazed\HelpScoutSidebar\Contracts;

use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

/**
 * Turns the context of a Help Scout conversation into a record from the host
 * application, or null when no record can be matched.
 */
interface CustomerResolver
{
    /**
     * Resolve the host application record this conversation belongs to.
     *
     * Implementations must never throw as a result of ordinary lookup failure.
     * The sidebar renders inside an iframe, and an exception there surfaces to
     * a support agent mid-conversation. Return null instead.
     *
     * @return mixed The resolved record, or null when nothing matched.
     */
    public function resolve(HelpScoutContext $context): mixed;
}
