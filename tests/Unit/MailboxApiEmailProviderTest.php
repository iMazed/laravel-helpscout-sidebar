<?php

namespace Imazed\HelpScoutSidebar\Tests\Unit;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Imazed\HelpScoutSidebar\Api\AccessTokenRepository;
use Imazed\HelpScoutSidebar\Api\MailboxApiClient;
use Imazed\HelpScoutSidebar\Api\MailboxApiConfig;
use Imazed\HelpScoutSidebar\Emails\MailboxApiEmailProvider;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;
use Imazed\HelpScoutSidebar\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;

class MailboxApiEmailProviderTest extends TestCase
{
    #[Test]
    public function it_extracts_embedded_email_addresses(): void
    {
        $this->fakeCustomer(['_embedded' => ['emails' => [
            ['id' => 1, 'value' => 'ada@example.com', 'type' => 'work'],
            ['id' => 2, 'value' => 'ada.lovelace@example.com', 'type' => 'home'],
        ]]]);

        $this->assertSame(
            ['ada@example.com', 'ada.lovelace@example.com'],
            $this->provider()->emails($this->context()),
        );
    }

    #[Test]
    public function it_accepts_a_top_level_emails_collection(): void
    {
        $this->fakeCustomer(['emails' => [['value' => 'ada@example.com']]]);

        $this->assertSame(['ada@example.com'], $this->provider()->emails($this->context()));
    }

    #[Test]
    public function it_discards_values_that_are_not_email_addresses(): void
    {
        $this->fakeCustomer(['_embedded' => ['emails' => [
            ['value' => 'not-an-email'],
            ['value' => 'ada@example.com'],
            ['value' => null],
        ]]]);

        $this->assertSame(['ada@example.com'], $this->provider()->emails($this->context()));
    }

    #[Test]
    public function it_caches_a_successful_lookup(): void
    {
        $this->fakeCustomer(['_embedded' => ['emails' => [['value' => 'ada@example.com']]]]);

        $provider = $this->provider();

        $provider->emails($this->context());
        $provider->emails($this->context());

        // One token exchange plus one customer call: the second lookup was served
        // from the cache rather than spending rate limit again.
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_caches_a_customer_with_no_addresses(): void
    {
        $this->fakeCustomer(['_embedded' => ['emails' => []]]);

        $provider = $this->provider();

        $this->assertSame([], $provider->emails($this->context()));
        $this->assertSame([], $provider->emails($this->context()));

        Http::assertSentCount(2);
    }

    #[Test]
    public function it_does_not_cache_a_failed_lookup(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 172800]),
            '*/customers/*' => Http::sequence()
                ->push(['error' => 'boom'], 500)
                ->push(['_embedded' => ['emails' => [['value' => 'ada@example.com']]]], 200),
        ]);

        $provider = $this->provider();

        $this->assertSame([], $provider->emails($this->context()));
        $this->assertSame(['ada@example.com'], $provider->emails($this->context()));
    }

    #[Test]
    public function it_does_nothing_without_a_customer_id(): void
    {
        Http::fake();

        $context = HelpScoutContext::fromRequest(Request::create('/helpscout/sidebar'));

        $this->assertSame([], $this->provider()->emails($context));

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    protected function fakeCustomer(array $customer): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 172800]),
            '*/customers/*' => Http::response($customer),
        ]);
    }

    protected function provider(): MailboxApiEmailProvider
    {
        $config = new MailboxApiConfig(enabled: true, appId: 'app-id', appSecret: 'app-secret');
        $cache = $this->app['cache']->store('array');

        return new MailboxApiEmailProvider(
            client: new MailboxApiClient(
                $this->app->make(HttpFactory::class),
                new AccessTokenRepository($this->app->make(HttpFactory::class), $cache, $config, new NullLogger),
                $config,
                new NullLogger,
            ),
            cache: $cache,
            config: $config,
        );
    }

    protected function context(): HelpScoutContext
    {
        return HelpScoutContext::fromRequest(
            Request::create('/helpscout/sidebar', 'GET', ['customer-id' => '4821']),
        );
    }
}
