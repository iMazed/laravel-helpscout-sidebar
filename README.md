# Laravel Help Scout Sidebar

Render data from your Laravel application inside the Help Scout conversation sidebar, so support agents see who they are talking to without leaving the inbox.

---

## Contents

In this file: [Requirements](#requirements) · [Installation](#installation) · [Help Scout setup](#help-scout-setup) · [Security](#security) · [Testing](#testing) · [Scope and versioning](#scope-and-versioning)

Guides:

- [Sections](docs/sections.md): the configuration reference: item types, formats, conditions, and copyable examples
- [Builders, header links, and resolution](docs/builders-and-resolution.md): `BuildsSidebar`, the header icons, and customizing how customers are matched
- [Diagnostics and troubleshooting](docs/troubleshooting.md): the debug screen, local development, and iframe height
- [Security model](docs/security.md): the trust boundary, what is attacker-controlled, and the properties the tests pin
- [Design decisions](docs/design-decisions.md): why the Mailbox API, why not the JavaScript SDK, and the paid-plan requirement

---

## Requirements

- PHP `8.3+`
- Laravel `12.x` or `13.x`
- A Help Scout plan that includes **API access** (Standard and above at the time of writing)

---

## Installation

```bash
composer require imazed/laravel-helpscout-sidebar
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=helpscout-sidebar-config
```

Publish the resources:

```bash
php artisan vendor:publish --provider="Imazed\HelpScoutSidebar\HelpScoutSidebarServiceProvider"
```

Add your credentials to `.env`:

```dotenv
# Signs the incoming iframe request
HELPSCOUT_SIDEBAR_SECRET=your-help-scout-app-secret

# Authenticates outgoing Mailbox API requests
HELPSCOUT_SIDEBAR_API_ENABLED=true
HELPSCOUT_SIDEBAR_APP_ID=your-oauth-app-id
HELPSCOUT_SIDEBAR_APP_SECRET=your-oauth-app-secret
```

Then point `config/helpscout-sidebar.php` at your customer model, if it is not `App\Models\User`:

```php
'customer_model' => App\Models\Account::class,
'customer_email_column' => 'billing_email',
```

**Sidebar content is not published.** To change what the sidebar shows, implement `BuildsSidebar` (see [Building the sidebar](docs/builders-and-resolution.md#building-the-sidebar)). Publishing the view is for the surrounding HTML, and it is not required in order to use your own view: point `view` in `config/helpscout-sidebar.php` at any view of your own.

```php
'view' => 'helpscout::my-sidebar',
```

## Help Scout setup

You need two different sets of Help Scout credentials, and they do different jobs: the sidebar app's secret verifies requests coming *in*, the OAuth app's credentials authenticate requests going *out* to the Mailbox API. They are not interchangeable.

### 1. The sidebar app

Create a custom app in Help Scout and point its callback URL at your route:

```text
https://your-site.com/helpscout/sidebar
```

Set a secret on the app and put the same value in `HELPSCOUT_SIDEBAR_SECRET`.

If your callback URL carries static query parameters of your own, list them under `signature.ignore` so they are excluded before verification:

```php
'signature' => [
    'ignore' => ['tenant'],
],
```

Read the [security model](docs/security.md) before you use an ignored parameter for anything that matters.

### 2. The OAuth app

Under **Your Profile → My Apps** in Help Scout, you will find a Mailbox API Application with your app name. Copy the App ID and App Secret you find here into `HELPSCOUT_SIDEBAR_APP_ID` and `HELPSCOUT_SIDEBAR_APP_SECRET`.

The package uses the OAuth2 **client credentials** grant, which Help Scout documents for server-to-server integrations. There is no redirect URL, no consent screen, and no refresh token to manage: the package exchanges the credentials for an access token, caches it, and renews it when it expires.

Both values change if you ever uninstall and reinstall the app, and the symptoms are easy to misread. See [Reading the Mailbox API section](docs/troubleshooting.md#reading-the-mailbox-api-section).

## Security

Customer identity is established server-side. The callback's signature is verified before anything else runs, and the customer is resolved through the Mailbox API from signed identifiers only; nothing the browser sends can select a record.

A full description of the threat model (what the signature does and does not cover, what counts as attacker-controlled, and the design decisions that follow) is documented in [Security model](docs/security.md). To report a security issue, [please open an issue](https://github.com/iMazed/laravel-helpscout-sidebar/issues).

## Testing

```bash
composer test
```

```bash
composer format
```

## License

MIT. See [LICENSE.md](LICENSE.md).
