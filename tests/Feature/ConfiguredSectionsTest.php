<?php

namespace Imazed\HelpScoutSidebar\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Imazed\HelpScoutSidebar\Tests\Fixtures\Models\Customer;
use Imazed\HelpScoutSidebar\Tests\Fixtures\Sidebar\LifetimeValue;
use Imazed\HelpScoutSidebar\Tests\Fixtures\Sidebar\OwnBuilder;
use Imazed\HelpScoutSidebar\Tests\Fixtures\Sidebar\PlainRecordResolver;
use Imazed\HelpScoutSidebar\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConfiguredSectionsTest extends TestCase
{
    #[Test]
    public function it_renders_rows_metrics_and_badges_from_paths(): void
    {
        $this->resolving([
            [
                'title' => 'Billing',
                'items' => [
                    ['type' => 'row', 'label' => 'Plan', 'value' => 'plan'],
                    ['type' => 'metric', 'label' => 'MRR', 'value' => 'mrr', 'format' => 'currency', 'prefix' => '$'],
                    ['type' => 'badge', 'value' => 'status', 'tones' => ['past_due' => 'negative']],
                ],
            ],
        ]);

        $this->render()
            ->assertSee('Billing')
            ->assertSee('Plan')
            ->assertSee('Team')
            ->assertSee('$348.00')
            ->assertSee('hs-sidebar-badge-negative', false)
            ->assertSee('past_due');
    }

    #[Test]
    public function it_formats_dates_and_numbers(): void
    {
        $this->resolving([
            [
                'title' => 'Account',
                'items' => [
                    ['type' => 'row', 'label' => 'Signed up', 'value' => 'created_at', 'format' => 'date'],
                    ['type' => 'row', 'label' => 'Last seen', 'value' => 'last_seen_at', 'format' => 'diff'],
                    ['type' => 'row', 'label' => 'Calls', 'value' => 'api_calls', 'format' => 'number'],
                    ['type' => 'row', 'label' => 'Active', 'value' => 'is_active', 'format' => 'bool'],
                ],
            ],
        ]);

        $this->render()
            ->assertSee('Mar 4, 2023')
            ->assertSee('ago')
            ->assertSee('41,208')
            ->assertSee('Yes');
    }

    #[Test]
    public function it_drops_an_item_whose_value_is_missing(): void
    {
        $this->resolving([
            [
                'title' => 'Billing',
                'items' => [
                    ['type' => 'row', 'label' => 'Plan', 'value' => 'plan'],
                    ['type' => 'row', 'label' => 'Nothing here', 'value' => 'does_not_exist'],
                ],
            ],
        ]);

        $this->render()
            ->assertSee('Plan')
            ->assertDontSee('Nothing here');
    }

    #[Test]
    public function it_skips_a_section_whose_condition_is_falsy(): void
    {
        $this->resolving([
            ['title' => 'Shown', 'when' => 'is_active', 'items' => [
                ['type' => 'row', 'label' => 'Plan', 'value' => 'plan'],
            ]],
            ['title' => 'Hidden', 'when' => 'cancelled_at', 'items' => [
                ['type' => 'row', 'label' => 'Plan', 'value' => 'plan'],
            ]],
        ]);

        $this->render()
            ->assertSee('Shown')
            ->assertDontSee('Hidden');
    }

    #[Test]
    public function it_repeats_a_list_over_a_collection(): void
    {
        $this->resolving([
            [
                'title' => 'Recent activity',
                'items' => [
                    ['type' => 'list', 'source' => 'events', 'limit' => 2,
                        'label' => 'when', 'value' => 'summary'],
                ],
            ],
        ]);

        $this->render()
            ->assertSee('9 days ago')
            ->assertSee('Payment failed')
            ->assertSee('4 days ago')
            ->assertDontSee('Removed 3 users');
    }

    #[Test]
    public function it_calls_an_invokable_class_when_a_path_is_not_enough(): void
    {
        $this->resolving([
            [
                'title' => 'Billing',
                'items' => [
                    ['type' => 'row', 'label' => 'Lifetime', 'value' => LifetimeValue::class],
                ],
            ],
        ]);

        $this->render()->assertSee('lifetime-for-ada@example.com');
    }

    #[Test]
    public function it_refuses_to_render_a_hidden_attribute(): void
    {
        $this->resolving([
            [
                'title' => 'Account',
                'items' => [
                    ['type' => 'row', 'label' => 'Secret token', 'value' => 'private_note'],
                    ['type' => 'row', 'label' => 'Plan', 'value' => 'plan'],
                ],
            ],
        ]);

        $this->render()
            ->assertSee('Plan')
            ->assertDontSee('Secret token')
            ->assertDontSee('do-not-show-this');
    }

    #[Test]
    public function it_refuses_a_password_even_when_the_model_does_not_hide_it(): void
    {
        $this->resolving([
            [
                'title' => 'Account',
                'items' => [
                    ['type' => 'row', 'label' => 'Password', 'value' => 'password'],
                    ['type' => 'row', 'label' => 'Plan', 'value' => 'plan'],
                ],
            ],
        ]);

        $this->render()
            ->assertSee('Plan')
            ->assertDontSee('Password')
            ->assertDontSee('hunter2');
    }

    #[Test]
    public function a_custom_builder_ignores_the_configured_sections(): void
    {
        $this->resolving([
            ['title' => 'From config', 'items' => [
                ['type' => 'row', 'label' => 'Plan', 'value' => 'plan'],
            ]],
        ]);

        config()->set('helpscout-sidebar.builder', OwnBuilder::class);

        $this->render()
            ->assertSee('From the builder')
            ->assertDontSee('From config');
    }

    #[Test]
    public function it_falls_back_to_the_default_builder_when_nothing_is_configured(): void
    {
        $this->resolving([]);

        $this->render()->assertSee('Record');
    }

    #[Test]
    public function a_plain_object_record_renders_from_configuration(): void
    {
        // The resolved record is `mixed` by contract, so paths, conditions,
        // and lists have to work on a bare object as surely as on a model.
        config()->set('helpscout-sidebar.signature.enabled', false);
        config()->set('helpscout-sidebar.resolver', PlainRecordResolver::class);
        config()->set('helpscout-sidebar.sections', [
            [
                'title' => 'Billing',
                'items' => [
                    ['type' => 'row', 'label' => 'Plan', 'value' => 'planName'],
                    ['type' => 'metric', 'label' => 'Monthly revenue', 'value' => 'mrr', 'format' => 'currency', 'prefix' => '$'],
                    ['type' => 'row', 'label' => 'Last payment', 'value' => 'declineReason', 'when' => 'paymentFailed'],
                    ['type' => 'row', 'label' => 'Trial', 'value' => 'trialDaysLeft', 'when' => 'trialDaysLeft'],
                ],
            ],
            [
                'title' => 'Recent activity',
                'items' => [
                    ['type' => 'list', 'source' => 'events', 'limit' => 2, 'label' => 'when', 'value' => 'summary'],
                ],
            ],
        ]);

        $this->get('/helpscout/sidebar')
            ->assertOk()
            ->assertSee('Team (annual)')
            ->assertSee('$348.00')
            ->assertSee('Declined — insufficient funds')
            ->assertSee('Payment failed')
            ->assertDontSee('Trial')
            ->assertDontSee('Removed 3 users');
    }

    #[Test]
    public function it_shows_that_the_record_resolved_when_every_value_is_empty(): void
    {
        // Configuration pointing at columns this record does not have. The
        // header would otherwise render alone, which reads as a broken install
        // rather than as a configuration that matched nothing.
        $this->resolving([
            [
                'title' => 'Billing',
                'items' => [
                    ['type' => 'row', 'label' => 'Plan', 'value' => 'subscription.plan_name'],
                    ['type' => 'metric', 'label' => 'MRR', 'value' => 'subscription.mrr'],
                ],
            ],
        ]);

        $this->render()
            ->assertSee('Record')
            ->assertDontSee('Billing');
    }

    /**
     * A customer that resolves, and the sections to render for it.
     *
     * @param  array<int, array<string, mixed>>  $sections
     */
    protected function resolving(array $sections): void
    {
        Customer::create([
            'email' => 'ada@example.com',
            'name' => 'Ada Lovelace',
            'plan' => 'Team',
            'mrr' => 348,
            'status' => 'past_due',
            'api_calls' => 41208,
            'is_active' => true,
            'password' => 'hunter2',
            'private_note' => 'do-not-show-this',
            'created_at' => '2023-03-04 09:00:00',
            'last_seen_at' => Carbon::now()->subDays(2),
        ]);

        config()->set('helpscout-sidebar.fallback_email', 'ada@example.com');
        config()->set('helpscout-sidebar.signature.enabled', false);
        config()->set('helpscout-sidebar.sections', $sections);
    }

    protected function render(): TestResponse
    {
        return $this->get('/helpscout/sidebar')->assertOk();
    }
}
