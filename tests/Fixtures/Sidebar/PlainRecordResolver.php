<?php

namespace Imazed\HelpScoutSidebar\Tests\Fixtures\Sidebar;

use Imazed\HelpScoutSidebar\Contracts\CustomerResolver;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

/**
 * Resolves every conversation to a plain PHP object.
 *
 * Nothing requires the record behind the sidebar to be an Eloquent model, and
 * this fixture is what keeps that true: it exercises the object-property read
 * path in RecordValues that a model-backed test never touches.
 */
class PlainRecordResolver implements CustomerResolver
{
    public function resolve(HelpScoutContext $context): object
    {
        return (object) [
            'planName' => 'Team (annual)',
            'mrr' => 348,
            'paymentFailed' => true,
            'declineReason' => 'Declined — insufficient funds',
            'trialDaysLeft' => 0,
            'events' => [
                ['when' => '9 days ago', 'summary' => 'Payment failed'],
                ['when' => '4 days ago', 'summary' => 'Payment retried'],
                ['when' => '2 days ago', 'summary' => 'Removed 3 users'],
            ],
        ];
    }
}
