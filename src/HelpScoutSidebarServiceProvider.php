<?php

namespace Imazed\HelpScoutSidebar;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use Imazed\HelpScoutSidebar\Api\AccessTokenRepository;
use Imazed\HelpScoutSidebar\Api\MailboxApiClient;
use Imazed\HelpScoutSidebar\Api\MailboxApiConfig;
use Imazed\HelpScoutSidebar\Contracts\BuildsSidebar;
use Imazed\HelpScoutSidebar\Contracts\CustomerResolver;
use Imazed\HelpScoutSidebar\Contracts\ProvidesCustomerEmails;
use Imazed\HelpScoutSidebar\Emails\ContextEmailProvider;
use Imazed\HelpScoutSidebar\Emails\FallbackEmailProvider;
use Imazed\HelpScoutSidebar\Emails\MailboxApiEmailProvider;
use Imazed\HelpScoutSidebar\Resolvers\EmailCustomerResolver;
use Imazed\HelpScoutSidebar\Support\SignatureVerifier;
use Psr\Log\LoggerInterface;

class HelpScoutSidebarServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings.
     *
     * Everything is wired here rather than read from `config()` inside the
     * classes themselves, so each piece can be constructed directly in a test
     * without booting the framework's configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/helpscout-sidebar.php', 'helpscout-sidebar');

        $this->registerSignatureVerification();
        $this->registerMailboxApi();
        $this->registerEmailProviders();
        $this->registerResolution();
    }

    /**
     * Register views, routes, and publishable assets.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'helpscout-sidebar');
        $this->loadRoutesFrom(__DIR__.'/../routes/helpscout-sidebar.php');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/helpscout-sidebar.php' => config_path('helpscout-sidebar.php'),
        ], 'helpscout-sidebar-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/helpscout-sidebar'),
        ], 'helpscout-sidebar-views');

        $this->publishes([
            __DIR__.'/../resources/css/helpscout-sidebar.css' => public_path('vendor/helpscout-sidebar/helpscout-sidebar.css'),
            __DIR__.'/../resources/js/helpscout-sidebar.js' => public_path('vendor/helpscout-sidebar/helpscout-sidebar.js'),
            __DIR__.'/../resources/js/vendor/helpscout-javascript-sdk.js' => public_path('vendor/helpscout-sidebar/helpscout-javascript-sdk.js'),
        ], 'helpscout-sidebar-assets');
    }

    protected function registerSignatureVerification(): void
    {
        $this->app->singleton(SignatureVerifier::class, function (Application $app): SignatureVerifier {
            $signature = (array) $app['config']->get('helpscout-sidebar.signature', []);

            return new SignatureVerifier(
                secret: $app['config']->get('helpscout-sidebar.secret'),
                parameter: (string) ($signature['parameter'] ?? 'X-HelpScout-Signature'),
                ignore: array_values((array) ($signature['ignore'] ?? [])),
            );
        });
    }

    protected function registerMailboxApi(): void
    {
        $this->app->singleton(MailboxApiConfig::class, function (Application $app): MailboxApiConfig {
            return MailboxApiConfig::fromArray((array) $app['config']->get('helpscout-sidebar.api', []));
        });

        $this->app->singleton(AccessTokenRepository::class, function (Application $app): AccessTokenRepository {
            return new AccessTokenRepository(
                http: $app->make(Factory::class),
                cache: $this->cacheStore($app),
                config: $app->make(MailboxApiConfig::class),
                logger: $app->make(LoggerInterface::class),
            );
        });

        $this->app->singleton(MailboxApiClient::class, function (Application $app): MailboxApiClient {
            return new MailboxApiClient(
                http: $app->make(Factory::class),
                tokens: $app->make(AccessTokenRepository::class),
                config: $app->make(MailboxApiConfig::class),
                logger: $app->make(LoggerInterface::class),
            );
        });
    }

    /**
     * Bind the shipped email providers.
     *
     * Each is bound explicitly because they take constructor arguments the
     * container cannot guess. Your own providers only need to implement
     * {@see ProvidesCustomerEmails} and be resolvable from the container.
     */
    protected function registerEmailProviders(): void
    {
        $this->app->bind(ContextEmailProvider::class, fn (): ContextEmailProvider => new ContextEmailProvider);

        $this->app->bind(MailboxApiEmailProvider::class, function (Application $app): MailboxApiEmailProvider {
            return new MailboxApiEmailProvider(
                client: $app->make(MailboxApiClient::class),
                cache: $this->cacheStore($app),
                config: $app->make(MailboxApiConfig::class),
            );
        });

        $this->app->bind(FallbackEmailProvider::class, function (Application $app): FallbackEmailProvider {
            return new FallbackEmailProvider($app['config']->get('helpscout-sidebar.fallback_email'));
        });
    }

    protected function registerResolution(): void
    {
        $this->app->bind(EmailCustomerResolver::class, function (Application $app): EmailCustomerResolver {
            return new EmailCustomerResolver(
                providers: $this->emailProviderChain($app),
                model: $app['config']->get('helpscout-sidebar.customer_model'),
                column: (string) $app['config']->get('helpscout-sidebar.customer_email_column', 'email'),
                logger: $app->make(LoggerInterface::class),
            );
        });

        $this->app->bind(CustomerResolver::class, function (Application $app): CustomerResolver {
            return $app->make($app['config']->get('helpscout-sidebar.resolver'));
        });

        $this->app->bind(BuildsSidebar::class, function (Application $app): BuildsSidebar {
            return $app->make($app['config']->get('helpscout-sidebar.builder'));
        });
    }

    /**
     * The configured email providers, instantiated in order.
     *
     * @return array<int, ProvidesCustomerEmails>
     */
    protected function emailProviderChain(Application $app): array
    {
        return array_map(
            static fn (string $provider): ProvidesCustomerEmails => $app->make($provider),
            array_values((array) $app['config']->get('helpscout-sidebar.email_providers', [])),
        );
    }

    /**
     * The cache store used for API tokens and customer lookups.
     */
    protected function cacheStore(Application $app): CacheRepository
    {
        return $app->make(CacheFactory::class)->store(
            $app['config']->get('helpscout-sidebar.api.cache.store'),
        );
    }
}
