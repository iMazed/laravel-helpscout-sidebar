<?php

namespace Imazed\HelpScoutSidebar\Tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Imazed\HelpScoutSidebar\Api\AccessTokenRepository;
use Imazed\HelpScoutSidebar\Api\MailboxApiClient;
use Imazed\HelpScoutSidebar\Api\MailboxApiConfig;
use Imazed\HelpScoutSidebar\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;

class MailboxApiClientTest extends TestCase
{
    #[Test]
    public function it_fetches_a_customer(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response(['access_token' => 'tok-1', 'expires_in' => 172800]),
            '*/customers/4821' => Http::response(['id' => 4821, 'firstName' => 'Ada']),
        ]);

        $this->assertSame(4821, $this->client()->customer(4821)['id']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer tok-1'));
    }

    #[Test]
    public function it_retries_once_with_a_fresh_token_after_a_401(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::sequence()
                ->push(['access_token' => 'stale', 'expires_in' => 172800])
                ->push(['access_token' => 'fresh', 'expires_in' => 172800]),
            '*/customers/4821' => Http::sequence()
                ->push(['error' => 'unauthorized'], 401)
                ->push(['id' => 4821], 200),
        ]);

        $this->assertSame(4821, $this->client()->customer(4821)['id']);

        // Two token exchanges and two customer calls: the stale token was
        // discarded rather than replayed indefinitely.
        Http::assertSentCount(4);
    }

    #[Test]
    public function it_gives_up_after_a_second_401(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 172800]),
            '*/customers/4821' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $this->assertNull($this->client()->customer(4821));
    }

    #[Test]
    public function an_unknown_customer_returns_null(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 172800]),
            '*/customers/4821' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $this->assertNull($this->client()->customer(4821));
    }

    #[Test]
    public function rate_limiting_returns_null_rather_than_throwing(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 172800]),
            '*/customers/4821' => Http::response(['error' => 'Too Many Requests'], 429),
        ]);

        $this->assertNull($this->client()->customer(4821));
    }

    #[Test]
    public function a_network_failure_returns_null_rather_than_throwing(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 172800]),
            '*/customers/4821' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $this->assertNull($this->client()->customer(4821));
    }

    #[Test]
    public function it_does_not_call_out_when_unusable(): void
    {
        Http::fake();

        $client = $this->client(new MailboxApiConfig(enabled: false));

        $this->assertFalse($client->isUsable());
        $this->assertNull($client->customer(4821));

        Http::assertNothingSent();
    }

    #[Test]
    public function it_rejects_a_non_positive_customer_id(): void
    {
        Http::fake();

        $this->assertNull($this->client()->customer(0));

        Http::assertNothingSent();
    }

    protected function client(?MailboxApiConfig $config = null): MailboxApiClient
    {
        $config ??= new MailboxApiConfig(enabled: true, appId: 'app-id', appSecret: 'app-secret');

        return new MailboxApiClient(
            http: $this->app->make(HttpFactory::class),
            tokens: new AccessTokenRepository(
                $this->app->make(HttpFactory::class),
                $this->app['cache']->store('array'),
                $config,
                new NullLogger,
            ),
            config: $config,
            logger: new NullLogger,
        );
    }
}
