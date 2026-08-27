<?php

namespace Imazed\HelpScoutSidebar\Tests\Unit;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Imazed\HelpScoutSidebar\Api\AccessTokenRepository;
use Imazed\HelpScoutSidebar\Api\MailboxApiConfig;
use Imazed\HelpScoutSidebar\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;

class AccessTokenRepositoryTest extends TestCase
{
    #[Test]
    public function it_exchanges_client_credentials_for_a_token(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response(['access_token' => 'tok-1', 'expires_in' => 172800]),
        ]);

        $this->assertSame('tok-1', $this->repository()->token());

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $request->url() === 'https://api.helpscout.net/v2/oauth2/token'
                && $body['grant_type'] === 'client_credentials'
                && $body['client_id'] === 'app-id'
                && $body['client_secret'] === 'app-secret';
        });
    }

    #[Test]
    public function it_caches_the_token_across_calls(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response(['access_token' => 'tok-1', 'expires_in' => 172800]),
        ]);

        $repository = $this->repository();

        $repository->token();
        $repository->token();
        $repository->token();

        Http::assertSentCount(1);
    }

    #[Test]
    public function forgetting_the_token_forces_a_new_exchange(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::sequence()
                ->push(['access_token' => 'tok-1', 'expires_in' => 172800])
                ->push(['access_token' => 'tok-2', 'expires_in' => 172800]),
        ]);

        $repository = $this->repository();

        $this->assertSame('tok-1', $repository->token());

        $repository->forget();

        $this->assertSame('tok-2', $repository->token());
        $this->assertTrue($repository->isCached());
    }

    #[Test]
    public function it_returns_null_when_the_credentials_are_rejected(): void
    {
        Http::fake(['*/oauth2/token' => Http::response(['error' => 'invalid_client'], 401)]);

        $this->assertNull($this->repository()->token());
        $this->assertFalse($this->repository()->isCached());
    }

    #[Test]
    public function it_returns_null_without_calling_out_when_unconfigured(): void
    {
        Http::fake();

        $repository = $this->repository(new MailboxApiConfig(enabled: true, appId: null, appSecret: null));

        $this->assertNull($repository->token());

        Http::assertNothingSent();
    }

    #[Test]
    public function it_returns_null_without_calling_out_when_disabled(): void
    {
        Http::fake();

        $repository = $this->repository(
            new MailboxApiConfig(enabled: false, appId: 'app-id', appSecret: 'app-secret'),
        );

        $this->assertNull($repository->token());

        Http::assertNothingSent();
    }

    #[Test]
    public function a_response_without_an_access_token_is_treated_as_a_failure(): void
    {
        Http::fake(['*/oauth2/token' => Http::response(['expires_in' => 172800])]);

        $this->assertNull($this->repository()->token());
    }

    protected function repository(?MailboxApiConfig $config = null): AccessTokenRepository
    {
        return new AccessTokenRepository(
            http: $this->app->make(HttpFactory::class),
            cache: $this->app['cache']->store('array'),
            config: $config ?? $this->config(),
            logger: new NullLogger,
        );
    }

    protected function config(): MailboxApiConfig
    {
        return new MailboxApiConfig(enabled: true, appId: 'app-id', appSecret: 'app-secret');
    }
}
