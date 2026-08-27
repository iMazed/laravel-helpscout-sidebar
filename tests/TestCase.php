<?php

namespace Imazed\HelpScoutSidebar\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Imazed\HelpScoutSidebar\HelpScoutSidebarServiceProvider;
use Imazed\HelpScoutSidebar\Support\PackagedAssets;
use Imazed\HelpScoutSidebar\Support\SignatureVerifier;
use Imazed\HelpScoutSidebar\Tests\Fixtures\Models\Customer;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $secret = 'test-app-secret';

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [HelpScoutSidebarServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');

        $app['config']->set('helpscout-sidebar.secret', $this->secret);
        $app['config']->set('helpscout-sidebar.customer_model', Customer::class);
        $app['config']->set('helpscout-sidebar.customer_email_column', 'email');
    }

    protected function setUp(): void
    {
        parent::setUp();

        PackagedAssets::flush();

        Schema::create('customers', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('email')->unique();
            $table->string('name')->nullable();
        });
    }

    /**
     * Build a correctly signed sidebar URL.
     *
     * Mirrors what Help Scout sends: the parameters, followed by a signature
     * calculated over exactly those parameters in that order.
     *
     * @param  array<string, scalar>  $parameters
     */
    protected function signedUrl(array $parameters, ?string $secret = null): string
    {
        $query = $parameters + [
            'X-HelpScout-Signature' => SignatureVerifier::calculate($parameters, $secret ?? $this->secret),
        ];

        return '/helpscout/sidebar?'.http_build_query($query);
    }

    /**
     * A representative Help Scout callback payload: identifiers only.
     *
     * Values are strings because that is what arrives over a query string, and
     * the signature is calculated over exactly what arrives. Signing integers
     * here would produce a payload that never matches a real request.
     *
     * @return array<string, string>
     */
    protected function callbackParameters(int $customerId = 4821): array
    {
        return [
            'conversation-id' => '991',
            'conversation-number' => '128',
            'customer-id' => (string) $customerId,
            'mailbox-id' => '7',
            'user-id' => '33',
            'application-slug' => 'laravel-sidebar',
        ];
    }
}
