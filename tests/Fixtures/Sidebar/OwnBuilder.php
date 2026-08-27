<?php

namespace Imazed\HelpScoutSidebar\Tests\Fixtures\Sidebar;

use Imazed\HelpScoutSidebar\Contracts\BuildsSidebar;
use Imazed\HelpScoutSidebar\Sidebar\Section;
use Imazed\HelpScoutSidebar\Sidebar\Sidebar;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

class OwnBuilder implements BuildsSidebar
{
    public function build(Sidebar $sidebar, mixed $customer, HelpScoutContext $context): Sidebar
    {
        return $sidebar->section('Custom', fn (Section $section) => $section->note('From the builder'));
    }
}
