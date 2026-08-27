<?php

namespace Imazed\HelpScoutSidebar\Support;

use Illuminate\Http\Request;

/**
 * Verifies the X-HelpScout-Signature query parameter on sidebar requests.
 *
 * Help Scout signs the parameters it sends with the secret configured on your
 * app. Verification is what makes the callback payload trustworthy: without it,
 * anyone who knows the route URL can render the sidebar for any conversation.
 */
class SignatureVerifier
{
    /**
     * @param  string|null  $secret  The app secret shared with Help Scout.
     * @param  string  $parameter  Query parameter carrying the signature.
     * @param  array<int, string>  $ignore  Parameters you appended to the callback URL yourself.
     */
    public function __construct(
        protected ?string $secret = null,
        protected string $parameter = 'X-HelpScout-Signature',
        protected array $ignore = [],
    ) {}

    /**
     * Whether this request carries a signature matching the configured secret.
     *
     * Returns false rather than throwing when the package is misconfigured, so
     * a missing secret fails closed.
     */
    public function isValid(Request $request): bool
    {
        $signature = $request->query($this->parameter);

        if (! is_string($this->secret) || $this->secret === '') {
            return false;
        }

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        return hash_equals($this->expected($request), $signature);
    }

    /**
     * The signature this request should carry.
     *
     * Exposed for testing and for building signed URLs in local development.
     */
    public function expected(Request $request): string
    {
        $payload = $request->query->all();

        unset($payload[$this->parameter]);

        foreach ($this->ignore as $key) {
            unset($payload[$key]);
        }

        return self::calculate($payload, (string) $this->secret);
    }

    /**
     * Calculate a Help Scout signature: base64-encoded HMAC-SHA1 over the
     * JSON-encoded parameters.
     *
     * @param  array<string, scalar|null>  $payload
     */
    public static function calculate(array $payload, string $secret): string
    {
        return base64_encode(hash_hmac('sha1', (string) json_encode($payload), $secret, true));
    }

    /**
     * The parameter names excluded from verification, and therefore untrusted.
     *
     * @return array<int, string>
     */
    public function ignoredParameters(): array
    {
        return $this->ignore;
    }
}
