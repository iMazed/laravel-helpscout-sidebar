<?php

namespace Imazed\HelpScoutSidebar\Tests\Feature;

use Imazed\HelpScoutSidebar\Tests\Fixtures\Models\Customer;
use Imazed\HelpScoutSidebar\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class HeaderLinksTest extends TestCase
{
    #[Test]
    public function it_renders_configured_links_as_icons(): void
    {
        $this->sidebarWith([
            ['label' => 'Open in admin', 'icon' => 'user', 'url' => 'https://admin.example.test/c/{email}'],
        ]);

        $this->get('/helpscout/sidebar')
            ->assertOk()
            ->assertSee('<nav class="hs-sidebar-links"', false)
            ->assertSee('https://admin.example.test/c/ada%40example.com', false)
            ->assertSee('Open in admin')
            ->assertSee('<svg', false);
    }

    #[Test]
    public function a_help_scout_identifier_placeholder_needs_a_signed_request(): void
    {
        Customer::create(['email' => 'ada@example.com', 'name' => 'Ada Lovelace']);

        config()->set('helpscout-sidebar.fallback_email', 'ada@example.com');
        config()->set('helpscout-sidebar.links', [
            ['label' => 'In Help Scout', 'url' => 'https://admin.example.test/c/{customer_id}'],
        ]);

        // Signed: customer-id is trusted, so the URL can be built from it.
        $this->get($this->signedUrl($this->callbackParameters()))
            ->assertOk()
            ->assertSee('https://admin.example.test/c/4821', false);

        // Unsigned: every parameter is untrusted, so there is nothing to
        // build the URL from and the link is dropped rather than guessed at.
        config()->set('helpscout-sidebar.signature.enabled', false);

        $this->get('/helpscout/sidebar?customer-id=4821')
            ->assertOk()
            ->assertDontSee('In Help Scout');
    }

    #[Test]
    public function it_drops_a_link_whose_placeholder_cannot_be_filled(): void
    {
        $this->sidebarWith([
            ['label' => 'Needs a conversation', 'url' => 'https://admin.example.test/c/{conversation_id}'],
            ['label' => 'Always works', 'url' => 'https://admin.example.test/all'],
        ]);

        // Unsigned, so conversation-id is untrusted and reads as absent.
        $this->get('/helpscout/sidebar?conversation-id=991')
            ->assertOk()
            ->assertDontSee('Needs a conversation')
            ->assertSee('Always works');
    }

    #[Test]
    public function the_documented_id_placeholder_works(): void
    {
        $this->sidebarWith([
            ['label' => 'Open in admin', 'url' => 'https://admin.example.test/customers/{id}'],
        ]);

        $id = Customer::where('email', 'ada@example.com')->firstOrFail()->getKey();

        $this->get('/helpscout/sidebar')
            ->assertOk()
            ->assertSee('https://admin.example.test/customers/'.$id, false);
    }

    #[Test]
    public function it_drops_a_link_with_an_unknown_placeholder(): void
    {
        $this->sidebarWith([
            ['label' => 'Typo', 'url' => 'https://admin.example.test/{custmoer_id}'],
        ]);

        $this->get('/helpscout/sidebar?customer-id=4821')
            ->assertOk()
            ->assertDontSee('Typo');
    }

    #[Test]
    public function it_falls_back_to_a_generic_icon_for_an_unknown_name(): void
    {
        $this->sidebarWith([
            ['label' => 'Somewhere', 'icon' => 'not-an-icon', 'url' => 'https://example.test'],
        ]);

        $this->get('/helpscout/sidebar')
            ->assertOk()
            ->assertSee('Somewhere')
            ->assertSee('<svg', false);
    }

    #[Test]
    public function it_escapes_a_label_and_a_url(): void
    {
        $this->sidebarWith([
            ['label' => '"><script>alert(1)</script>', 'url' => 'https://example.test/"><script>alert(1)</script>'],
        ]);

        $response = $this->get('/helpscout/sidebar')->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    #[Test]
    public function it_renders_no_header_when_nothing_is_configured(): void
    {
        $this->sidebarWith([]);

        $this->get('/helpscout/sidebar')
            ->assertOk()
            ->assertDontSee('<nav class="hs-sidebar-links"', false);
    }

    #[Test]
    public function it_does_not_render_links_on_the_no_match_state(): void
    {
        // No record and no fallback address, so nothing resolves.
        config()->set('helpscout-sidebar.signature.enabled', false);
        config()->set('helpscout-sidebar.links', [
            ['label' => 'Open in admin', 'url' => 'https://admin.example.test/all'],
        ]);

        $this->get('/helpscout/sidebar')
            ->assertOk()
            ->assertSee('No customer found')
            ->assertDontSee('Open in admin');
    }

    #[Test]
    public function it_resolves_a_relative_url_against_the_app_url(): void
    {
        config()->set('app.url', 'https://app.example.test');

        $this->sidebarWith([
            ['label' => 'Open in admin', 'url' => '/admin/customers/{id}'],
        ]);

        $this->get('/helpscout/sidebar')
            ->assertOk()
            ->assertSee('https://app.example.test/admin/customers/', false);
    }

    #[Test]
    public function a_link_base_overrides_the_app_url(): void
    {
        config()->set('app.url', 'https://app.example.test');

        $this->sidebarWith([
            ['label' => 'Open in admin', 'url' => 'admin/customers/{id}'],
        ]);

        config()->set('helpscout-sidebar.link_base', 'https://backoffice.example.test/');

        $this->get('/helpscout/sidebar')
            ->assertOk()
            ->assertSee('https://backoffice.example.test/admin/customers/', false)
            ->assertDontSee('app.example.test', false);
    }

    #[Test]
    public function an_absolute_url_is_left_alone(): void
    {
        config()->set('app.url', 'https://app.example.test');

        $this->sidebarWith([
            ['label' => 'Stripe', 'url' => 'https://dashboard.stripe.com/customers/{id}'],
            ['label' => 'Email', 'url' => 'mailto:{email}'],
        ]);

        $this->get('/helpscout/sidebar')
            ->assertOk()
            ->assertSee('https://dashboard.stripe.com/customers/', false)
            ->assertSee('mailto:ada%40example.com', false);
    }

    /**
     * A customer that resolves through the development fallback, and the
     * header links to render for it.
     *
     * @param  array<int, array<string, mixed>>  $links
     */
    protected function sidebarWith(array $links): void
    {
        Customer::create(['email' => 'ada@example.com', 'name' => 'Ada Lovelace']);

        config()->set('helpscout-sidebar.fallback_email', 'ada@example.com');
        config()->set('helpscout-sidebar.signature.enabled', false);
        config()->set('helpscout-sidebar.links', $links);
    }
}
