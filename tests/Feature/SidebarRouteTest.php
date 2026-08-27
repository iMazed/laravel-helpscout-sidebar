<?php

namespace Imazed\HelpScoutSidebar\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Imazed\HelpScoutSidebar\Tests\Fixtures\Models\Customer;
use Imazed\HelpScoutSidebar\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SidebarRouteTest extends TestCase
{
    #[Test]
    public function it_rejects_an_unsigned_request(): void
    {
        $this->get('/helpscout/sidebar?customer-id=4821')->assertForbidden();
    }

    #[Test]
    public function it_rejects_a_request_signed_with_the_wrong_secret(): void
    {
        $this->get($this->signedUrl($this->callbackParameters(), secret: 'wrong'))->assertForbidden();
    }

    #[Test]
    public function it_resolves_a_customer_through_the_mailbox_api(): void
    {
        Customer::create(['email' => 'ada@example.com', 'name' => 'Ada Lovelace']);

        config()->set('helpscout-sidebar.api.enabled', true);
        config()->set('helpscout-sidebar.api.app_id', 'app-id');
        config()->set('helpscout-sidebar.api.app_secret', 'app-secret');

        Http::fake([
            '*/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 172800]),
            '*/customers/4821' => Http::response(['_embedded' => ['emails' => [['value' => 'ada@example.com']]]]),
        ]);

        $this->get($this->signedUrl($this->callbackParameters()))
            ->assertOk()
            ->assertSee('Record')
            ->assertSee('#128');
    }

    #[Test]
    public function it_renders_the_no_match_state_when_nothing_resolves(): void
    {
        $this->get($this->signedUrl($this->callbackParameters()))
            ->assertOk()
            ->assertSee('No customer found')
            ->assertSee('no matching record', false);
    }

    #[Test]
    public function it_never_returns_a_server_error_when_the_api_is_down(): void
    {
        config()->set('helpscout-sidebar.api.enabled', true);
        config()->set('helpscout-sidebar.api.app_id', 'app-id');
        config()->set('helpscout-sidebar.api.app_secret', 'app-secret');

        Http::fake(['*' => Http::response(['error' => 'service unavailable'], 503)]);

        $this->get($this->signedUrl($this->callbackParameters()))
            ->assertOk()
            ->assertSee('No customer found');
    }

    #[Test]
    public function it_allows_help_scout_to_frame_the_response(): void
    {
        $response = $this->get($this->signedUrl($this->callbackParameters()));

        $response->assertHeader('Content-Security-Policy', 'frame-ancestors https://secure.helpscout.net');
        $this->assertFalse($response->headers->has('X-Frame-Options'));
    }

    #[Test]
    public function it_never_exposes_the_signature_to_the_browser(): void
    {
        $url = $this->signedUrl($this->callbackParameters());
        $signature = urldecode((string) parse_url($url, PHP_URL_QUERY));

        $response = $this->get($url)->assertOk();

        $this->assertStringNotContainsString('X-HelpScout-Signature', $response->getContent());
        $this->assertNotEmpty($signature);
    }

    #[Test]
    public function diagnostics_explain_why_nothing_matched(): void
    {
        config()->set('helpscout-sidebar.debug', true);

        $this->get($this->signedUrl($this->callbackParameters()))
            ->assertOk()
            ->assertSee('No customer matched')
            ->assertSee('MailboxApiEmailProvider')
            ->assertSee('4821')
            ->assertSee('API lookups are not running');
    }

    #[Test]
    public function diagnostics_are_hidden_by_default(): void
    {
        $this->get($this->signedUrl($this->callbackParameters()))
            ->assertOk()
            ->assertDontSee('MailboxApiEmailProvider');
    }

    #[Test]
    public function an_unsigned_parameter_cannot_choose_the_customer(): void
    {
        Customer::create(['email' => 'victim@example.com', 'name' => 'Victim']);

        config()->set('helpscout-sidebar.signature.ignore', ['email']);

        // The signature covers only Help Scout's own parameters, so `email` can
        // be anything at all and still verify. It must not be resolvable.
        $parameters = $this->callbackParameters();
        $url = $this->signedUrl($parameters).'&email=victim%40example.com';

        $this->get($url)
            ->assertOk()
            ->assertSee('No customer found')
            ->assertDontSee('Victim');
    }
}
