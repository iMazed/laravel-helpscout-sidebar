<?php

namespace Imazed\HelpScoutSidebar\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;

/**
 * The Help Scout conversation context, parsed from the signed callback query
 * string.
 *
 * Help Scout's current app platform sends identifiers only — no email address
 * — which is why this package resolves customers through the Mailbox API
 * rather than from the callback.
 *
 * Signed and unsigned parameters are kept apart: {@see self::get()} returns
 * anything, for display, while {@see self::trusted()} returns only values
 * covered by the signature, and customer resolution reads exclusively through
 * trusted(). A parameter listed in `signature.ignore` is editable by anyone
 * who can load the iframe — docs/security.md carries the full argument.
 *
 * @implements Arrayable<string, scalar|null>
 */
class HelpScoutContext implements Arrayable
{
    /**
     * @param  array<string, scalar|null>  $data  Every callback parameter, signed or not.
     * @param  array<int, string>  $untrustedKeys  Keys excluded from signature verification.
     */
    public function __construct(
        protected array $data = [],
        protected array $untrustedKeys = [],
    ) {}

    /**
     * Build the context from an incoming sidebar request.
     *
     * @param  array<int, string>  $untrustedKeys  Usually the `signature.ignore` config value.
     * @param  string  $signatureParameter  Stripped so the signature never reaches a view.
     */
    public static function fromRequest(
        Request $request,
        array $untrustedKeys = [],
        string $signatureParameter = 'X-HelpScout-Signature',
    ): self {
        $payload = $request->query->all();

        unset($payload[$signatureParameter]);

        return new self($payload, array_values($untrustedKeys));
    }

    /**
     * Read any callback parameter, signed or not.
     *
     * Safe for display. Never use this to decide which customer to show — use
     * {@see self::trusted()} for that.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Read a callback parameter only if it was covered by the signature.
     *
     * Returns $default for keys listed in `signature.ignore`, even when the
     * request carried a value for them.
     */
    public function trusted(string $key, mixed $default = null): mixed
    {
        if (in_array($key, $this->untrustedKeys, true)) {
            return $default;
        }

        return $this->get($key, $default);
    }

    /**
     * The Help Scout customer ID for this conversation.
     *
     * This is Help Scout's own identifier and bears no relationship to any ID
     * in the host application. It is the input to a Mailbox API lookup, not a
     * primary key you can query directly.
     */
    public function customerId(): ?int
    {
        return $this->trustedInteger('customer-id');
    }

    public function conversationId(): ?int
    {
        return $this->trustedInteger('conversation-id');
    }

    public function conversationNumber(): ?int
    {
        return $this->trustedInteger('conversation-number');
    }

    public function mailboxId(): ?int
    {
        return $this->trustedInteger('mailbox-id');
    }

    /**
     * The ID of the Help Scout user (the support agent) viewing the sidebar.
     */
    public function userId(): ?int
    {
        return $this->trustedInteger('user-id');
    }

    /**
     * Every parameter name present on the request, in the order received.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    /**
     * Parameter names excluded from signature verification.
     *
     * @return array<int, string>
     */
    public function untrustedKeys(): array
    {
        return $this->untrustedKeys;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Read a signed parameter as a positive integer, or null when absent,
     * non-numeric, or unsigned.
     */
    protected function trustedInteger(string $key): ?int
    {
        $value = $this->trusted($key);

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        return (int) $value > 0 ? (int) $value : null;
    }
}
