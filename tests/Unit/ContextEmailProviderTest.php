<?php

namespace Imazed\HelpScoutSidebar\Tests\Unit;

use Illuminate\Http\Request;
use Imazed\HelpScoutSidebar\Emails\ContextEmailProvider;
use Imazed\HelpScoutSidebar\Emails\FallbackEmailProvider;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;
use Imazed\HelpScoutSidebar\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ContextEmailProviderTest extends TestCase
{
    #[Test]
    public function it_reads_a_signed_email_parameter(): void
    {
        $context = $this->context(['email' => 'ada@example.com']);

        $this->assertSame(['ada@example.com'], (new ContextEmailProvider)->emails($context));
    }

    #[Test]
    public function it_refuses_an_unsigned_email_parameter(): void
    {
        // The whole point: a parameter excluded from signature verification can
        // be edited by anyone who can load the iframe, so it must never decide
        // which customer is displayed.
        $context = $this->context(['email' => 'victim@example.com'], untrusted: ['email']);

        $this->assertSame([], (new ContextEmailProvider)->emails($context));
    }

    #[Test]
    public function it_discards_malformed_addresses(): void
    {
        $this->assertSame([], (new ContextEmailProvider)->emails($this->context(['email' => 'nope'])));
    }

    #[Test]
    public function the_fallback_provider_yields_nothing_until_configured(): void
    {
        $context = $this->context([]);

        $this->assertSame([], (new FallbackEmailProvider(null))->emails($context));
        $this->assertSame([], (new FallbackEmailProvider(''))->emails($context));
        $this->assertSame([], (new FallbackEmailProvider('not-an-email'))->emails($context));
        $this->assertSame(['dev@example.com'], (new FallbackEmailProvider(' dev@example.com '))->emails($context));
    }

    /**
     * @param  array<string, scalar>  $parameters
     * @param  array<int, string>  $untrusted
     */
    protected function context(array $parameters, array $untrusted = []): HelpScoutContext
    {
        return HelpScoutContext::fromRequest(
            Request::create('/helpscout/sidebar', 'GET', $parameters),
            $untrusted,
        );
    }
}
