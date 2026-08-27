<?php

namespace Imazed\HelpScoutSidebar\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Imazed\HelpScoutSidebar\Contracts\CustomerResolver;
use Imazed\HelpScoutSidebar\Contracts\ProvidesCustomerEmails;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;
use Psr\Log\LoggerInterface;

/**
 * Resolves a host application record by email address.
 *
 * The resolver itself knows nothing about Help Scout. It asks an ordered list
 * of {@see ProvidesCustomerEmails} for candidate addresses and queries the
 * configured Eloquent model with each in turn, returning the first match.
 *
 * Ordering is the whole design. Providers are arranged cheapest-first, so the
 * Mailbox API is only consulted when no address was available for free, and the
 * development fallback is only consulted when nothing real was found at all.
 */
class EmailCustomerResolver implements CustomerResolver
{
    /**
     * @param  array<int, ProvidesCustomerEmails>  $providers  Email sources, in preference order.
     * @param  class-string<Model>|string|null  $model  The Eloquent model to query.
     * @param  string  $column  The column holding the customer's email address.
     */
    public function __construct(
        protected array $providers,
        protected ?string $model,
        protected string $column = 'email',
        protected ?LoggerInterface $logger = null,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolve(HelpScoutContext $context): mixed
    {
        if (! $this->isQueryable()) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = $this->model;

        foreach ($this->candidateEmails($context) as $email) {
            try {
                $customer = $model::query()->where($this->column, $email)->first();
            } catch (QueryException $e) {
                // A misconfigured model or column is a deployment mistake, not
                // something an agent mid-conversation should see as a 500.
                $this->logger?->error('Help Scout sidebar could not query the customer model.', [
                    'model' => $model,
                    'column' => $this->column,
                    'exception' => $e->getMessage(),
                ]);

                return null;
            }

            if ($customer !== null) {
                return $customer;
            }
        }

        return null;
    }

    /**
     * Every candidate address for this conversation, de-duplicated and ordered.
     *
     * @return array<int, string>
     */
    public function candidateEmails(HelpScoutContext $context): array
    {
        $emails = [];

        foreach ($this->candidatesByProvider($context) as $candidate) {
            $emails = array_merge($emails, $candidate['emails']);
        }

        return $this->deduplicate($emails);
    }

    /**
     * Candidate addresses paired with the provider that supplied them.
     *
     * Returned as an ordered list rather than a map keyed by class name,
     * because the same provider class may legitimately appear more than once in
     * the chain and keying by name would silently discard all but the last.
     *
     * Used by the diagnostics screen to show which source produced what. Note
     * that calling this runs every provider, so it may perform an API request;
     * the Mailbox API provider caches its results, which keeps the diagnostics
     * screen from doubling the request count.
     *
     * @return array<int, array{provider: class-string, emails: array<int, string>}>
     */
    public function candidatesByProvider(HelpScoutContext $context): array
    {
        $candidates = [];

        foreach ($this->providers as $provider) {
            $candidates[] = [
                'provider' => $provider::class,
                'emails' => $this->deduplicate($provider->emails($context)),
            ];
        }

        return $candidates;
    }

    /**
     * Whether the configured model can actually be queried.
     *
     * Returns false — rather than throwing — for an unset, non-existent, or
     * non-Eloquent model, so an incomplete install renders the no-match state
     * with diagnostics instead of a stack trace.
     */
    public function isQueryable(): bool
    {
        return is_string($this->model)
            && $this->model !== ''
            && is_a($this->model, Model::class, true)
            && $this->column !== '';
    }

    /**
     * The Eloquent model this resolver queries, for diagnostics.
     */
    public function model(): ?string
    {
        return $this->model;
    }

    /**
     * The column this resolver matches against, for diagnostics.
     */
    public function column(): string
    {
        return $this->column;
    }

    /**
     * What a candidate address is matched against, for diagnostics.
     *
     * A subclass that resolves against something other than an Eloquent model
     * should say so here, so the diagnostics screen does not report a missing
     * model as a fault when there was never meant to be one.
     */
    public function target(): string
    {
        if (! is_string($this->model) || $this->model === '') {
            return 'Not configured';
        }

        return $this->model.'.'.$this->column;
    }

    /**
     * Remove duplicates case-insensitively while preserving order and the
     * casing of the first occurrence.
     *
     * @param  array<int, string>  $emails
     * @return array<int, string>
     */
    protected function deduplicate(array $emails): array
    {
        $seen = [];

        foreach ($emails as $email) {
            $key = mb_strtolower(trim($email));

            if ($key !== '' && ! array_key_exists($key, $seen)) {
                $seen[$key] = trim($email);
            }
        }

        return array_values($seen);
    }
}
