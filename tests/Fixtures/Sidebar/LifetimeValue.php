<?php

namespace Imazed\HelpScoutSidebar\Tests\Fixtures\Sidebar;

/**
 * The escape hatch: a value a dotted path cannot express.
 */
class LifetimeValue
{
    public function __invoke(mixed $customer): string
    {
        return 'lifetime-for-'.$customer->email;
    }
}
