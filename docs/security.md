# Security model

How the package decides what to trust, and what that decision protects against. Read this before extending the package.

## Trust boundaries

Help Scout calculates `X-HelpScout-Signature` over the parameters it sends. A verified signature means those parameters came from Help Scout and were not altered in transit. That is the only reason `customer-id` can be believed.

**Anything outside the signature is attacker-controlled.** Two categories fall outside it:

1. Parameters you appended to the callback URL yourself. Help Scout cannot sign them, which is why they must be listed in `signature.ignore` for verification to succeed at all. Anyone who can load the iframe can edit them and the signature still verifies.
2. Anything sent from the browser, including values read via Help Scout's JavaScript SDK.

The package enforces this in code. `HelpScoutContext` keeps signed and unsigned parameters apart: `get()` returns anything, for display; `trusted()` returns only signed values; every customer-resolution path reads through `trusted()`. An unsigned `email` parameter is visible to your views and invisible to the resolver.

**Why it matters:** if an unsigned address could select the record, this route would become an arbitrary lookup oracle over your user table. Anyone able to load the iframe (a compromised agent account, a captured signed URL) could substitute any address and read whatever your sidebar renders for it, including addresses belonging to people who were never Help Scout customers. Signature verification would not catch it, because the tampered field was never part of the signed payload.

That risk is why the package depends on the Mailbox API instead of the free and much easier JavaScript SDK. See [Design decisions](design-decisions.md#why-not-read-the-customer-email-from-the-help-scout-javascript-sdk) for the comparison.

## Other security notes

- Keep `signature.enabled` on in production. With it off, the package treats every parameter as unsigned, so resolution stops working instead of silently trusting input.
- The route deliberately has no auth middleware; Help Scout loads it with no session. The signature is what protects it.
- Signed callback URLs carry no expiry. A captured URL keeps working; scope what the sidebar displays accordingly.
- The response sets `Content-Security-Policy: frame-ancestors https://secure.helpscout.net` and removes `X-Frame-Options`, so a global `X-Frame-Options: DENY` elsewhere in your app does not silently break the sidebar.
- Access tokens live in your cache store and are never rendered.

## Properties pinned by tests

Two behaviours are covered by the test suite specifically because they are security properties rather than features:

- An unsigned parameter cannot select a customer, even when the rest of the request verifies.
- The route never returns a `5xx` when the Mailbox API misbehaves; failures log and degrade to the no-match state.

## Reporting an issue

If you have any concerns, please [open an issue](https://github.com/iMazed/laravel-helpscout-sidebar/issues).
