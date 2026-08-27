<?php

namespace Imazed\HelpScoutSidebar\Sidebar;

use Illuminate\Database\Eloquent\Model;
use Imazed\HelpScoutSidebar\Contracts\BuildsSidebar;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

/**
 * A minimal builder that proves the plumbing works.
 *
 * It shows the resolved record's key and little else, because this package has
 * no idea what your customers are. Replace it with your own implementation of
 * {@see BuildsSidebar} and point `helpscout-sidebar.builder` at that class.
 */
class DefaultSidebarBuilder implements BuildsSidebar
{
    /**
     * {@inheritDoc}
     */
    public function build(Sidebar $sidebar, mixed $customer, HelpScoutContext $context): Sidebar
    {
        return $sidebar->section('Customer', function (Section $section) use ($customer, $context): void {
            $section->row('Record', $this->identifier($customer));

            $section->when($context->conversationNumber(), function (Section $section) use ($context): void {
                $section->row('Conversation', '#'.$context->conversationNumber());
            });
        });
    }

    /**
     * A human-readable identifier for the resolved record.
     */
    protected function identifier(mixed $customer): mixed
    {
        if ($customer instanceof Model) {
            return $customer->getKey();
        }

        if (is_array($customer)) {
            return $customer['id'] ?? null;
        }

        if (is_object($customer)) {
            return $customer->id ?? null;
        }

        return null;
    }
}
