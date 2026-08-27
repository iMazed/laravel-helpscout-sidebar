# Design decisions

The [security model](security.md) is a companion piece; most of these decisions follow from it.

## Why not read the customer email from the Help Scout JavaScript SDK?

**Considered and rejected on security grounds.**

The [JavaScript SDK](https://developer.helpscout.com/apps/javascript-sdk/context-object/) exposes the full context object inside the iframe, including `customer.emails[].value`. It works on every Help Scout plan and needs no API credentials, so it is by some distance the cheapest way to get an address.

It is also unsafe here. The SDK runs in the browser, so the address would reach the server on a second, client-initiated request, outside the signature. That turns the sidebar into an arbitrary lookup oracle over your user table, as described in the [security model](security.md).

The tradeoff is explicit: this package accepts a paid-plan dependency rather than shipping an unauthenticated read primitive over customer data. If your sidebar only ever displays information already visible to every agent, the calculus is different, but that is a decision to make deliberately, not to inherit as a default.

## Why does this require a paid Help Scout plan?

API access is not included in the Help Scout Free plan, and the API is the only trustworthy path from `customer-id` to a customer.

- **Free**: no API access. The package runs, but only the development fallback can resolve anything.
- **Standard and above**: API access and OAuth apps are available.

## Could a background sync avoid the API requirement?

No. It looks like it should, however, so it's helpful to explain why the answer is no. 

The alternative to a lookup at render time is a stored mapping: a `helpscout_customer_id` column on your model, populated ahead of time. But building that mapping means listing Help Scout customers and matching them to your records, which is itself an API operation. A sync moves the API dependency off the hot path; it does not remove it. On a plan with no API access, neither approach is available.

A sync is still worth building if render latency or rate limits become a problem. It is out of scope for this package, which resolves at request time and caches.

## What about legacy Dynamic Apps?

Help Scout's [legacy Dynamic Apps](https://developer.helpscout.com/apps/legacy-custom-apps/dynamic/) POST a signed JSON payload server-to-server that *does* include customer email addresses. Because that payload never passes through the browser, it does not carry the problem above.

They are legacy: Help Scout [encourages migration](https://developer.helpscout.com/apps/guides/migrating-legacy-dynamic-apps/) to the current platform, where apps obtain data through the JavaScript SDK or through query parameters combined with the Mailbox API. This package targets the current platform and does not depend on them.
