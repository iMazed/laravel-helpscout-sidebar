# Diagnostics and troubleshooting

## Diagnostics

```dotenv
HELPSCOUT_SIDEBAR_DEBUG=true
```

Replaces the no-match error screen with what the callback contained, whether the API is enabled and holding a token, which provider produced which addresses, and what was queried.

> Keep this off in production. It discloses your model and column names and lists customer email addresses.

## Reading the Mailbox API section

**Access token: Not cached** after a failed resolution means the token request itself failed, not that the lookup came up empty. Resolution runs before this screen renders, so a successful fetch would already be cached. The reason is in `storage/logs/laravel.log`:

| Log line | Cause |
| --- | --- |
| could not reach the token endpoint | Network, DNS, or a wrong `token_url` |
| was refused an access token | Credentials rejected; the status code is logged with it |
| token response without an access token | Reached Help Scout, got something unexpected back |

## When Help Scout is refusing the token

**Reinstalling the app in Help Scout issues new credentials.** Uninstalling and reinstalling resets the App ID and secret, and the old pair stops being accepted. Nothing visibly breaks: the app still loads in the inbox and the callback still arrives correctly signed, because that half is protected by a different secret. Only the API stops answering, which surfaces as a refused token and a sidebar that matches nothing. Copy both values across again after any reinstall.

## Local development

You will not have signed Help Scout requests on your machine, and you may not want to spend API calls while building a sidebar layout:

```dotenv
HELPSCOUT_SIDEBAR_VERIFY_SIGNATURE=false
HELPSCOUT_SIDEBAR_FALLBACK_EMAIL=practice@example.com
HELPSCOUT_SIDEBAR_DEBUG=true
```

Then open `yoursite.com/helpscout/sidebar` in a browser.

**Neither setting belongs in production.** Disabling verification makes the route publicly readable. The fallback address resolves the same record for every conversation, so agents would see one customer's data on everybody else's tickets. Both ship disabled.

## Iframe height

Help Scout does not size app frames to their content. An app reports its own height by calling `HelpScout.setAppHeight()`, which exists only once [the Help Scout JavaScript SDK](https://developer.helpscout.com/apps/javascript-sdk/) is present in the iframe, and Help Scout does not inject it. Without it the frame keeps its default height and taller sidebars scroll inside a fixed box.

This works without configuration: the packaged bridge measures the content and calls `setAppHeight()` on load, on resize, and through a `ResizeObserver`, and the package inlines a pinned build of `@helpscout/javascript-sdk` into the rendered document. No code is fetched from a third-party origin.

The bundle lives at `resources/js/vendor/helpscout-javascript-sdk.js`, rebuilt verbatim from Help Scout's published npm tarball; its header records the source, integrity hash, and build command. It is Help Scout's code, not covered by this package's MIT license.

`sdk_url` replaces it when you need something else:

```php
// Serve a build yourself instead of using the inlined one.
'sdk_url' => 'https://assets.example.com/helpscout-sdk.js',

// Load nothing. Sizing stops working unless you provide the SDK another way.
'sdk_url' => false,
```

A `sdk_url` build is loaded with a dynamic `import()`, so point it at an ES module build. Publishing the assets (see [Installation](../README.md#installation)) also puts a copy of the packaged bundle in `public/vendor/helpscout-sidebar/`.

When sizing does not work, open the iframe's console. The loader reports a failed import, and reports an SDK that loaded without a `setAppHeight()` method.

The SDK is used for sizing only. Customer identity is never read from the browser; see the [security model](security.md).