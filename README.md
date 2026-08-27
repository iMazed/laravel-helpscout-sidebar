# Laravel Help Scout Sidebar

Render data from your Laravel application inside the Help Scout conversation sidebar, so support agents see who they are talking to without leaving the inbox.

Customer identity is established **server-side, through the Help Scout Mailbox API**. Nothing the browser sends decides which record an agent sees. The reasoning behind that is documented in [Trust boundaries](#trust-boundaries), because it is the design decision that shapes everything else in this package — including its requirements.

---

## Contents

- [How it works](#how-it-works)
- [Requirements](#requirements)
- [Installation](#installation)
- [Publishing resources](#publishing-resources)
- [Help Scout setup](#help-scout-setup)
- [Building the sidebar](#building-the-sidebar)
- [Customising customer resolution](#customising-customer-resolution)
- [Trust boundaries](#trust-boundaries)
- [Diagnostics](#diagnostics)
- [Local development](#local-development)
- [FAQ and design decisions](#faq-and-design-decisions)
- [Testing](#testing)
- [Scope and versioning](#scope-and-versioning)

---

## How it works

Help Scout loads a route from your application in an iframe next to each conversation. The request it sends carries **identifiers only** — a real callback looks like this:

```text
conversation-id, conversation-number, customer-id, mailbox-id,
user-id, installation-ids, application-id, application-slug
```

No name, no email address, nothing else about the customer. So the package trades `customer-id` for something your application recognises:

```text
Help Scout iframe request
  └─ verify X-HelpScout-Signature ......... reject forgeries
     └─ parse identifiers ................. HelpScoutContext
        └─ collect candidate emails ....... ordered provider chain
           ├─ ContextEmailProvider ........ a signed email parameter, if you send one
           ├─ MailboxApiEmailProvider ..... customer-id → Mailbox API → addresses
           └─ FallbackEmailProvider ....... a fixed address, for local development
              └─ query your model ......... first match wins
                 └─ build and render ...... your BuildsSidebar implementation
```

Providers run in order and stop mattering as soon as one produces a match, so the API is only consulted when nothing cheaper was available. Results are cached per customer, because a busy agent re-renders this sidebar constantly and Help Scout's rate limits are per minute.

Every step degrades rather than throws. The only status codes this route emits are `200` and `403` — a support agent should never be shown a stack trace while a customer waits.

## Requirements

- PHP `8.3+`
- Laravel `12.x` or `13.x`
- A Help Scout plan that includes **API access** (Standard and above at the time of writing)

The plan requirement is not incidental — see [Why does this require a paid Help Scout plan?](#why-does-this-require-a-paid-help-scout-plan) On the Free plan the package installs and runs, but cannot resolve real customers.

## Installation

```bash
composer require imazed/laravel-helpscout-sidebar
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=helpscout-sidebar-config
```

Add your credentials to `.env`:

```dotenv
# Signs the incoming iframe request
HELPSCOUT_SIDEBAR_SECRET=your-help-scout-app-secret

# Authenticates outgoing Mailbox API requests
HELPSCOUT_SIDEBAR_API_ENABLED=true
HELPSCOUT_SIDEBAR_APP_ID=your-oauth-app-id
HELPSCOUT_SIDEBAR_APP_SECRET=your-oauth-app-secret

# Which model holds your customers
HELPSCOUT_SIDEBAR_CUSTOMER_MODEL=App\Models\User
HELPSCOUT_SIDEBAR_CUSTOMER_EMAIL_COLUMN=email
```

The service provider is registered through package discovery, and the route is registered for you at `/helpscout/sidebar`.

> **These are two different credentials.** `HELPSCOUT_SIDEBAR_SECRET` verifies requests coming *in* from Help Scout. `HELPSCOUT_SIDEBAR_APP_ID` and `HELPSCOUT_SIDEBAR_APP_SECRET` authenticate requests going *out* to the Mailbox API. They are created in two different places in Help Scout and are not interchangeable. Mixing them up is the most common first-run failure.

## Publishing resources

Three publishable tags are registered. Only the first is needed for a normal install:

```bash
php artisan vendor:publish --tag=helpscout-sidebar-config
php artisan vendor:publish --tag=helpscout-sidebar-views
php artisan vendor:publish --tag=helpscout-sidebar-assets
```

| Tag | Publishes to | When you need it |
| --- | --- | --- |
| `helpscout-sidebar-config` | `config/helpscout-sidebar.php` | Always — credentials, resolution, and route settings live here |
| `helpscout-sidebar-views` | `resources/views/vendor/helpscout-sidebar/` | Changing the document that wraps the sidebar |
| `helpscout-sidebar-assets` | `public/vendor/helpscout-sidebar/` | Forking the stylesheet or the height-reporting bridge |

Or publish everything at once:

```bash
php artisan vendor:publish --provider="Imazed\HelpScoutSidebar\HelpScoutSidebarServiceProvider"
```

**Sidebar content is not published.** To change what the sidebar shows, implement `BuildsSidebar` — see [Building the sidebar](#building-the-sidebar). Publishing the view is for the surrounding HTML, and it is not required in order to use your own view: point `view` in the config at any view of your own.

```php
'view' => 'helpscout::my-sidebar',
```

> **Publishing the assets does not, by itself, change anything.** The stylesheet and the bridge are read from the package directory and inlined into the rendered document, so a fresh install renders correctly and sizes its iframe without anyone running `vendor:publish`. The published copies under `public/vendor/helpscout-sidebar/` are not what the view reads. To serve them statically instead, publish the views as well and replace the two inlined blocks in `sidebar.blade.php` with `asset()` tags. Treat this tag as "give me the source files", not "install the assets".

## Help Scout setup

### 1. The sidebar app

Create a custom app in Help Scout and point its callback URL at your route:

```text
https://your-app.example.com/helpscout/sidebar
```

Set a secret on the app and put the same value in `HELPSCOUT_SIDEBAR_SECRET`.

If your callback URL carries static query parameters of your own, list them under `signature.ignore` so they are excluded before verification:

```php
'signature' => [
    'ignore' => ['tenant'],
],
```

Read [Trust boundaries](#trust-boundaries) before you use an ignored parameter for anything that matters.

### 2. The OAuth app

Create an internal app under **Your Profile → My Apps** in Help Scout and copy its App ID and App Secret into `HELPSCOUT_SIDEBAR_APP_ID` and `HELPSCOUT_SIDEBAR_APP_SECRET`.

The package uses the OAuth2 **client credentials** grant, which Help Scout documents for server-to-server integrations. There is no redirect URL, no consent screen, and no refresh token to manage: the package exchanges the credentials for an access token, caches it, and renews it when it expires.

## Building the sidebar

Implement `BuildsSidebar` and point the config at it:

```php
namespace App\Support\HelpScout;

use Imazed\HelpScoutSidebar\Contracts\BuildsSidebar;
use Imazed\HelpScoutSidebar\Sidebar\Section;
use Imazed\HelpScoutSidebar\Sidebar\Sidebar;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

class CustomerSidebar implements BuildsSidebar
{
    public function build(Sidebar $sidebar, mixed $customer, HelpScoutContext $context): Sidebar
    {
        return $sidebar
            ->title($customer->name)
            ->subtitle($customer->email)
            ->section('Account', function (Section $section) use ($customer): void {
                $section
                    ->row('Plan', $customer->plan)
                    ->row('Signed up', $customer->created_at->toFormattedDateString())
                    ->badge($customer->is_active ? 'Active' : 'Cancelled', $customer->is_active ? 'positive' : 'negative');
            })
            ->section('Billing', function (Section $section) use ($customer): void {
                $section
                    ->metric('Lifetime value', '$'.number_format($customer->lifetime_value))
                    ->link('Open in admin', route('admin.customers.show', $customer));
            });
    }
}
```

```php
'builder' => App\Support\HelpScout\CustomerSidebar::class,
```

Available on a `Section`: `row()`, `metric()`, `badge()`, `link()`, `when()`, plus two escape hatches — `blade()` to render one of your own views, and `html()` for pre-rendered markup.

Everything added through the fluent methods is **escaped on render**. `blade()` and `html()` are not, by design, so pass them only content you control.

## Customising customer resolution

### Adding an email source

Implement `ProvidesCustomerEmails` and add it to the chain:

```php
namespace App\Support\HelpScout;

use Imazed\HelpScoutSidebar\Contracts\ProvidesCustomerEmails;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

class LegacyAliasProvider implements ProvidesCustomerEmails
{
    public function emails(HelpScoutContext $context): array
    {
        return LegacyAlias::query()
            ->where('helpscout_conversation_id', $context->conversationId())
            ->pluck('email')
            ->all();
    }
}
```

```php
'email_providers' => [
    ContextEmailProvider::class,
    App\Support\HelpScout\LegacyAliasProvider::class,
    MailboxApiEmailProvider::class,
    FallbackEmailProvider::class,
],
```

Order is meaningful: put cheap sources first. Providers must swallow their own failures and return an empty array rather than throwing.

### Replacing resolution entirely

If matching by email is wrong for your data model, implement `CustomerResolver` instead:

```php
namespace App\Support\HelpScout;

use App\Models\Account;
use Imazed\HelpScoutSidebar\Contracts\CustomerResolver;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

class AccountResolver implements CustomerResolver
{
    public function resolve(HelpScoutContext $context): ?Account
    {
        return Account::query()
            ->where('helpscout_customer_id', $context->customerId())
            ->first();
    }
}
```

```php
'resolver' => App\Support\HelpScout\AccountResolver::class,
```

This is the right shape if you already store Help Scout customer IDs against your own records. Note that `customerId()` is Help Scout's identifier and has no relationship to your primary keys — you need a column that maps between them, and something that populates it.

## Trust boundaries

This is the section worth reading before extending the package.

**The signature is the trust boundary.** Help Scout calculates `X-HelpScout-Signature` over the parameters it sends. A verified signature means those parameters came from Help Scout and were not altered in transit. That is the only reason `customer-id` can be believed.

**Anything outside the signature is attacker-controlled.** Two categories fall outside it:

1. Parameters you appended to the callback URL yourself. They cannot be signed by Help Scout, which is exactly why they must be listed in `signature.ignore` for verification to succeed at all. Being excluded from the calculation means anyone who can load the iframe can edit them and the signature still verifies.
2. Anything sent from the browser — including values read via Help Scout's JavaScript SDK.

The package enforces this rather than documenting it and hoping. `HelpScoutContext` keeps signed and unsigned parameters apart: `get()` returns anything, for display, while `trusted()` returns only signed values, and every customer-resolution path reads through `trusted()`. An unsigned `email` parameter is visible to your views and invisible to the resolver.

**Why it matters.** If an unsigned address could select the record, this route would become an arbitrary lookup oracle over your user table: anyone able to load the iframe — a compromised agent account, a captured signed URL — could substitute any address and read whatever your sidebar renders for it, including addresses belonging to people who were never Help Scout customers. Signature verification would not catch it, because the tampered field was never part of the signed payload.

That risk is the reason this package depends on the Mailbox API instead of the free and much easier JavaScript SDK. See [the FAQ](#why-not-read-the-customer-email-from-the-help-scout-javascript-sdk) for the full comparison.

### Other security notes

- Keep `signature.enabled` on in production. With it off, the package treats *every* parameter as unsigned, so resolution stops working rather than silently trusting input.
- The route deliberately has no auth middleware — Help Scout loads it with no session. The signature is what protects it.
- Signed callback URLs carry no expiry. A captured URL keeps working; scope what the sidebar displays accordingly.
- The response sets `Content-Security-Policy: frame-ancestors https://secure.helpscout.net` and removes `X-Frame-Options`, so a global `X-Frame-Options: DENY` elsewhere in your app does not silently break the sidebar.
- Access tokens live in your cache store and are never rendered.

## Diagnostics

Resolution can come up empty in several places that all look identical from the outside. Turn on diagnostics to see which one happened:

```dotenv
HELPSCOUT_SIDEBAR_DEBUG=true
```

The no-match screen is then replaced with what the callback contained, whether the API is enabled and holding a token, which provider produced which addresses, and what was queried.

> Keep this off in production. It discloses your model and column names and lists customer email addresses.

## Local development

You will not have signed Help Scout requests on your machine, and you may not want to spend API calls while building a sidebar layout.

```dotenv
HELPSCOUT_SIDEBAR_VERIFY_SIGNATURE=false
HELPSCOUT_SIDEBAR_FALLBACK_EMAIL=practice@example.com
HELPSCOUT_SIDEBAR_DEBUG=true
```

You can then open `/helpscout/sidebar` in a browser and see a realistic render.

> **Neither setting belongs in production.** Disabling verification makes the route publicly readable. The fallback address resolves the *same record for every conversation*, so agents would see one customer's data on everybody else's tickets. Both ship disabled.

## FAQ and design decisions

### Why does the sidebar need the Mailbox API at all?

Because the callback does not include an email address, or anything else that identifies the customer to your application. It sends `customer-id`, which is meaningful only inside Help Scout. Turning that into something you recognise requires asking Help Scout, and the Mailbox API is the way to ask.

### Why not read the customer email from the Help Scout JavaScript SDK?

**Considered and rejected on security grounds.**

The [JavaScript SDK](https://developer.helpscout.com/apps/javascript-sdk/context-object/) exposes the full context object inside the iframe, including `customer.emails[].value`. It works on every Help Scout plan and needs no API credentials, so it is by some distance the cheapest way to get an address.

It is also unsafe here. The SDK runs in the browser, so the address would reach the server on a second, client-initiated request, outside the signature. That turns the sidebar into an arbitrary lookup oracle over your user table, as described in [Trust boundaries](#trust-boundaries).

The tradeoff is explicit: this package accepts a paid-plan dependency rather than shipping an unauthenticated read primitive over customer data. If your sidebar only ever displays information already visible to every agent, the calculus is different — but that is a decision to make deliberately, not to inherit as a default.

### Why does this require a paid Help Scout plan?

Because API access is not included in the Help Scout Free plan, and the API is the only trustworthy path from `customer-id` to a customer.

- **Free** — no API access. The package runs, but only the development fallback can resolve anything.
- **Standard and above** — API access and OAuth apps are available; real resolution works.

### Could a background sync avoid the API requirement?

No, and this is worth stating plainly because it looks like it should.

The alternative to a lookup at render time is a stored mapping — a `helpscout_customer_id` column on your model, populated ahead of time. But building that mapping means listing Help Scout customers and matching them to your records, which is itself an API operation. A sync moves the API dependency off the hot path; it does not remove it. On a plan with no API access, neither approach is available.

A sync is still worth building if render latency or rate limits become a problem. It is out of scope for this package, which resolves at request time and caches.

### What about legacy Dynamic Apps?

Help Scout's [legacy Dynamic Apps](https://developer.helpscout.com/apps/legacy-custom-apps/dynamic/) POST a signed JSON payload server-to-server that *does* include customer email addresses. Because that payload never passes through the browser, it does not carry the problem above.

They are legacy: Help Scout [encourages migration](https://developer.helpscout.com/apps/guides/migrating-legacy-dynamic-apps/) to the current platform, where apps obtain data through the JavaScript SDK or through query parameters combined with the Mailbox API. This package targets the current platform and does not depend on them.

### Why does the JavaScript bridge not expose the customer's email?

It could, and an earlier design did. It was removed so that no part of the package makes the unsafe path look supported. The bridge now does what it is genuinely needed for: reporting the rendered height so Help Scout sizes the iframe correctly.

### What happens when the Mailbox API is slow or down?

The sidebar renders its no-match state. API timeouts are deliberately short (2s connect, 5s total by default) because an agent is waiting on the render, and every failure path logs and degrades rather than throwing.

## Testing

```bash
composer test
```

```bash
composer format
```

The suite covers signature verification including tampering and fail-closed behaviour, the trusted/untrusted parameter split, the token lifecycle including caching and 401 retry, API failure modes (404, 429, network errors, malformed payloads), provider chain ordering and de-duplication, output escaping, and the route end to end.

Two behaviours are pinned by tests specifically because they are security properties rather than features: an unsigned parameter cannot select a customer, and the route never returns a `5xx` when the API misbehaves.

## Scope and versioning

Pre-`1.0`. The public API is:

| Class | Purpose |
| --- | --- |
| `Support\HelpScoutContext` | Parsed callback, with the trusted/untrusted split |
| `Support\SignatureVerifier` | Request signature verification |
| `Contracts\CustomerResolver` | Conversation → your record |
| `Contracts\ProvidesCustomerEmails` | One source of candidate addresses |
| `Contracts\BuildsSidebar` | Record → sidebar content |
| `Resolvers\EmailCustomerResolver` | The shipped provider-chain resolver |
| `Api\MailboxApiClient` | Read-only Mailbox API client |
| `Api\AccessTokenRepository` | Client-credentials token lifecycle |
| `Sidebar\Sidebar`, `Sidebar\Section` | Fluent content builder |
| `Http\Controllers\SidebarController` | The iframe endpoint |

Deliberately out of scope: webhooks, background syncing, the React UI Kit, hosted UI, and general-purpose Mailbox API coverage. This package renders a sidebar and resolves a customer; it is not a Help Scout SDK.

## License

MIT. See [LICENSE.md](LICENSE.md).
