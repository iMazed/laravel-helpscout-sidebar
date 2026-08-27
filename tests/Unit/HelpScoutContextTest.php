<?php

namespace Imazed\HelpScoutSidebar\Tests\Unit;

use Illuminate\Http\Request;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;
use Imazed\HelpScoutSidebar\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class HelpScoutContextTest extends TestCase
{
    #[Test]
    public function it_reads_help_scout_identifiers_from_the_callback(): void
    {
        $context = $this->context(['customer-id' => '4821', 'conversation-id' => '991', 'user-id' => '33']);

        $this->assertSame(4821, $context->customerId());
        $this->assertSame(991, $context->conversationId());
        $this->assertSame(33, $context->userId());
    }

    #[Test]
    public function it_strips_the_signature_so_it_never_reaches_a_view(): void
    {
        $context = HelpScoutContext::fromRequest(
            Request::create('/helpscout/sidebar', 'GET', [
                'customer-id' => '1',
                'X-HelpScout-Signature' => 'secret-looking-value',
            ]),
        );

        $this->assertArrayNotHasKey('X-HelpScout-Signature', $context->toArray());
        $this->assertSame(['customer-id'], $context->keys());
    }

    #[Test]
    public function it_rejects_identifiers_that_are_not_positive_integers(): void
    {
        $this->assertNull($this->context(['customer-id' => 'abc'])->customerId());
        $this->assertNull($this->context(['customer-id' => '0'])->customerId());
        $this->assertNull($this->context(['customer-id' => '-5'])->customerId());
        $this->assertNull($this->context([])->customerId());
    }

    #[Test]
    public function unsigned_parameters_are_readable_but_not_trusted(): void
    {
        $context = $this->context(
            ['customer-id' => '4821', 'tenant' => 'acme'],
            untrusted: ['tenant'],
        );

        // Available for display...
        $this->assertSame('acme', $context->get('tenant'));

        // ...but never for deciding which customer to show.
        $this->assertNull($context->trusted('tenant'));
        $this->assertSame(['tenant'], $context->untrustedKeys());
    }

    #[Test]
    public function an_unsigned_customer_id_is_not_trusted(): void
    {
        $context = $this->context(['customer-id' => '4821'], untrusted: ['customer-id']);

        $this->assertNull($context->customerId());
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
