<?php

namespace Imazed\HelpScoutSidebar\Tests\Unit;

use Illuminate\Http\Request;
use Imazed\HelpScoutSidebar\Contracts\ProvidesCustomerEmails;
use Imazed\HelpScoutSidebar\Resolvers\EmailCustomerResolver;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;
use Imazed\HelpScoutSidebar\Tests\Fixtures\Models\Customer;
use Imazed\HelpScoutSidebar\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EmailCustomerResolverTest extends TestCase
{
    #[Test]
    public function it_returns_the_first_record_matching_any_candidate(): void
    {
        Customer::create(['email' => 'ada@example.com', 'name' => 'Ada']);

        $resolver = $this->resolver([
            $this->provider(['nobody@example.com']),
            $this->provider(['ada@example.com']),
        ]);

        $this->assertSame('Ada', $resolver->resolve($this->context())?->name);
    }

    #[Test]
    public function it_consults_providers_in_order(): void
    {
        Customer::create(['email' => 'first@example.com', 'name' => 'First']);
        Customer::create(['email' => 'second@example.com', 'name' => 'Second']);

        $resolver = $this->resolver([
            $this->provider(['first@example.com']),
            $this->provider(['second@example.com']),
        ]);

        $this->assertSame('First', $resolver->resolve($this->context())?->name);
    }

    #[Test]
    public function it_deduplicates_candidates_case_insensitively(): void
    {
        $resolver = $this->resolver([
            $this->provider(['Ada@Example.com']),
            $this->provider(['ada@example.com', 'grace@example.com']),
        ]);

        $this->assertSame(
            ['Ada@Example.com', 'grace@example.com'],
            $resolver->candidateEmails($this->context()),
        );
    }

    #[Test]
    public function it_returns_null_without_querying_when_no_candidates_exist(): void
    {
        Customer::create(['email' => 'ada@example.com', 'name' => 'Ada']);

        $resolver = $this->resolver([$this->provider([])]);

        $this->assertNull($resolver->resolve($this->context()));
        $this->assertSame([], $resolver->candidateEmails($this->context()));
    }

    #[Test]
    public function it_reports_an_unusable_model_instead_of_throwing(): void
    {
        $resolver = new EmailCustomerResolver([$this->provider(['ada@example.com'])], 'App\\Models\\DoesNotExist');

        $this->assertFalse($resolver->isQueryable());
        $this->assertNull($resolver->resolve($this->context()));
    }

    #[Test]
    public function a_broken_column_degrades_instead_of_throwing(): void
    {
        $resolver = new EmailCustomerResolver(
            [$this->provider(['ada@example.com'])],
            Customer::class,
            'no_such_column',
        );

        $this->assertNull($resolver->resolve($this->context()));
    }

    #[Test]
    public function it_groups_candidates_by_provider_for_diagnostics(): void
    {
        $resolver = $this->resolver([$this->provider(['ada@example.com']), $this->provider([])]);

        $candidates = $resolver->candidatesByProvider($this->context());

        $this->assertCount(2, $candidates);
        $this->assertSame(['ada@example.com'], $candidates[0]['emails']);
        $this->assertSame([], $candidates[1]['emails']);
    }

    #[Test]
    public function two_instances_of_the_same_provider_class_both_contribute(): void
    {
        // Providers are held as a list, not a map keyed by class name: the same
        // provider class can legitimately appear twice with different settings.
        $resolver = $this->resolver([
            $this->provider(['first@example.com']),
            $this->provider(['second@example.com']),
        ]);

        $this->assertSame(
            ['first@example.com', 'second@example.com'],
            $resolver->candidateEmails($this->context()),
        );
    }

    /**
     * @param  array<int, ProvidesCustomerEmails>  $providers
     */
    protected function resolver(array $providers): EmailCustomerResolver
    {
        return new EmailCustomerResolver($providers, Customer::class, 'email');
    }

    /**
     * An anonymous provider returning fixed addresses.
     *
     * @param  array<int, string>  $emails
     */
    protected function provider(array $emails): ProvidesCustomerEmails
    {
        return new class($emails) implements ProvidesCustomerEmails
        {
            /**
             * @param  array<int, string>  $emails
             */
            public function __construct(protected array $emails) {}

            public function emails(HelpScoutContext $context): array
            {
                return $this->emails;
            }
        };
    }

    protected function context(): HelpScoutContext
    {
        return HelpScoutContext::fromRequest(
            Request::create('/helpscout/sidebar', 'GET', ['customer-id' => '4821']),
        );
    }
}
