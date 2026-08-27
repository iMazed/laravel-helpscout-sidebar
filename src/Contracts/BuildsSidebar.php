<?php

namespace Imazed\HelpScoutSidebar\Contracts;

use Imazed\HelpScoutSidebar\Sidebar\Sidebar;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

/**
 * Decides what a resolved customer looks like in the Help Scout sidebar.
 */
interface BuildsSidebar
{
    /**
     * Populate the sidebar for a resolved customer.
     *
     * @param  Sidebar  $sidebar  An empty sidebar to build upon.
     * @param  mixed  $customer  The record returned by the configured CustomerResolver.
     */
    public function build(Sidebar $sidebar, mixed $customer, HelpScoutContext $context): Sidebar;
}
