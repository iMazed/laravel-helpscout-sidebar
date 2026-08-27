<?php

use App\Models\User;
use Imazed\HelpScoutSidebar\Emails\ContextEmailProvider;
use Imazed\HelpScoutSidebar\Emails\FallbackEmailProvider;
use Imazed\HelpScoutSidebar\Emails\MailboxApiEmailProvider;
use Imazed\HelpScoutSidebar\Resolvers\EmailCustomerResolver;
use Imazed\HelpScoutSidebar\Sidebar\ConfiguredSidebarBuilder;

return [

    /*
    |--------------------------------------------------------------------------
    | App Secret
    |--------------------------------------------------------------------------
    |
    | The secret configured on your Help Scout app. Help Scout signs the iframe
    | callback with it so this package can reject forged requests.
    |
    | This is NOT the same as the Mailbox API credentials below. The secret
    | signs incoming requests; the API credentials authenticate outgoing ones.
    | They are created in different places in Help Scout.
    |
    */

    'secret' => env('HELPSCOUT_SIDEBAR_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    |
    | Help Scout loads this path in an iframe with no session, so do not add
    | auth middleware. Signature verification is what protects the route.
    |
    */

    'route' => [
        'enabled' => true,
        'path' => 'helpscout/sidebar',
        'name' => 'helpscout.sidebar',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature Verification
    |--------------------------------------------------------------------------
    |
    | Help Scout signs the parameters it sends and passes the result as a query
    | parameter. Any parameter you append to the callback URL yourself is not
    | part of that signature, so list it in "ignore" to exclude it before
    | verification.
    |
    | Ignored parameters are, by definition, unsigned: anyone who can load the
    | iframe can edit them. This package therefore refuses to resolve customers
    | from them. See the security model, docs/security.md in the repository.
    |
    | Disabling verification is for local development only.
    |
    */

    'signature' => [
        'enabled' => env('HELPSCOUT_SIDEBAR_VERIFY_SIGNATURE', true),
        'parameter' => 'X-HelpScout-Signature',
        'ignore' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mailbox API
    |--------------------------------------------------------------------------
    |
    | The Help Scout callback identifies the customer by ID only, so the Mailbox
    | API is how this package learns which customer a conversation belongs to.
    | Requires a plan with API access; on the Free plan leave this disabled.
    |
    | These are NOT the same as the app secret above. The secret signs incoming
    | requests; these credentials authenticate outgoing ones. Creating the
    | OAuth app they come from is covered in the documentation:
    |
    |   https://github.com/iMazed/laravel-helpscout-sidebar#help-scout-setup
    |
    */

    'api' => [
        'enabled' => env('HELPSCOUT_SIDEBAR_API_ENABLED', false),

        'app_id' => env('HELPSCOUT_SIDEBAR_APP_ID'),
        'app_secret' => env('HELPSCOUT_SIDEBAR_APP_SECRET'),

        'base_url' => env('HELPSCOUT_SIDEBAR_API_BASE_URL', 'https://api.helpscout.net/v2'),
        'token_url' => env('HELPSCOUT_SIDEBAR_API_TOKEN_URL', 'https://api.helpscout.net/v2/oauth2/token'),

        /*
         * Kept short on purpose. This runs while an agent waits for the sidebar
         * to paint, so a slow API is worse than an absent one.
         */
        'timeout' => (int) env('HELPSCOUT_SIDEBAR_API_TIMEOUT', 5),
        'connect_timeout' => (int) env('HELPSCOUT_SIDEBAR_API_CONNECT_TIMEOUT', 2),

        'cache' => [
            /*
             * Null uses the application's default cache store. A store that
             * supports atomic locks (redis, memcached, database) lets the
             * package avoid a token stampede on a cold cache.
             */
            'store' => env('HELPSCOUT_SIDEBAR_API_CACHE_STORE'),

            'prefix' => 'helpscout-sidebar',

            /*
             * How long a customer's addresses stay cached. Longer is kinder to
             * your rate limit; shorter picks up address changes sooner.
             */
            'customer_ttl' => (int) env('HELPSCOUT_SIDEBAR_API_CACHE_TTL', 600),

            /*
             * A shorter TTL for customers who returned no addresses at all, so
             * they do not cost a request on every single render.
             */
            'missing_ttl' => (int) env('HELPSCOUT_SIDEBAR_API_MISSING_CACHE_TTL', 60),

            /*
             * Seconds shaved off the token's stated lifetime, so a token is
             * never used in the moments around its expiry.
             */
            'token_leeway' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer Resolution
    |--------------------------------------------------------------------------
    |
    | The shipped resolver collects candidate email addresses from the providers
    | below, in order, and queries "customer_model" with each until one matches.
    |
    | Providers are ordered cheapest-first: a signed address on the callback
    | costs nothing, the Mailbox API costs an HTTP request, and the fallback is
    | a development convenience. Reorder or remove entries freely, or bind your
    | own CustomerResolver implementation to skip email matching altogether.
    |
    */

    'resolver' => EmailCustomerResolver::class,

    'email_providers' => [
        ContextEmailProvider::class,
        MailboxApiEmailProvider::class,
        FallbackEmailProvider::class,
    ],

    /*
     * Which model holds your customers, and which column holds their email
     * address. These describe your codebase rather than your environment —
     * they are the same in local, staging and production — so they belong
     * here rather than in .env, the way auth.php names its user model.
     */
    'customer_model' => User::class,
    'customer_email_column' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Development Fallback
    |--------------------------------------------------------------------------
    |
    | Resolves this address when no real one was found, so you can preview the
    | sidebar without Help Scout or API credentials.
    |
    | Do not set this in production. It matches the same record for every
    | conversation, which means agents see one customer's data on everybody
    | else's tickets.
    |
    */

    'fallback_email' => env('HELPSCOUT_SIDEBAR_FALLBACK_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Diagnostics
    |--------------------------------------------------------------------------
    |
    | Replaces the no-match state with an explanation of what resolution tried.
    | Discloses model and column names and customer email addresses, so keep it
    | off outside development.
    |
    */

    'debug' => env('HELPSCOUT_SIDEBAR_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */

    'builder' => ConfiguredSidebarBuilder::class,
    'view' => 'helpscout-sidebar::sidebar',

    /*
    |--------------------------------------------------------------------------
    | Sections
    |--------------------------------------------------------------------------
    |
    | What the sidebar shows, read off the resolved record as dotted paths.
    | Most of a sidebar is a label and a value, which does not need to be code
    | — so this covers that case, and `builder` above covers everything else.
    | The two do not combine: point `builder` at your own BuildsSidebar class
    | and everything here is ignored.
    |
    | Item types, formats, conditions, invokable values, and copyable examples
    | live in the documentation rather than here, so that this file stays
    | something you read rather than something you excavate:
    |
    |   https://github.com/iMazed/laravel-helpscout-sidebar/blob/master/docs/sections.md
    |
    | Attributes listed in the model's $hidden are never rendered, nor are
    | password, remember_token, and their obvious relatives. The sidebar is
    | seen by every agent in the inbox; a typo here should not be able to put
    | a password hash in front of them.
    |
    */

    'sections' => [

        /*
         * A starting point that works on a stock Laravel install, because a
         * sidebar that renders nothing on first run is indistinguishable from
         * one that is broken. Both rows disappear on a model without those
         * columns, so this is safe to leave in place while you replace it.
         */
        [
            'title' => 'Account',
            'items' => [
                ['type' => 'row', 'label' => 'Customer since', 'value' => 'created_at', 'format' => 'date'],
                ['type' => 'row', 'label' => 'Email verified', 'value' => 'email_verified_at', 'format' => 'date'],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Header links
    |--------------------------------------------------------------------------
    |
    | Icons across the top of the sidebar, taking an agent from the
    | conversation to the same customer elsewhere. Applied automatically, so
    | these work without writing a builder.
    |
    | URLs may be relative (resolved against `link_base` below, or app.url)
    | and may carry placeholders filled from the resolved record and the
    | callback; a link that cannot be filled is dropped and logged rather than
    | shown broken. The placeholders, icon names, and logging details:
    |
    |   https://github.com/iMazed/laravel-helpscout-sidebar/blob/master/docs/header-links.md
    |
    | These open in a new tab and are plain GET requests, so point them at
    | pages, never at anything that acts — the security model (docs/security.md)
    | covers why a link in this iframe is not a safe place to put an action.
    |
    */
    'link_base' => env('HELPSCOUT_SIDEBAR_LINK_BASE'),

    'links' => [
        // ['label' => 'Open in admin', 'icon' => 'user', 'url' => '/admin/customers/{id}'],
        // ['label' => 'Billing', 'icon' => 'card', 'url' => '/admin/customers/{id}/billing'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Help Scout JavaScript SDK
    |--------------------------------------------------------------------------
    |
    | Help Scout does not size app frames to their content. An app reports its
    | own height by calling HelpScout.setAppHeight(), which only exists once the
    | Help Scout SDK is loaded in the iframe — and Help Scout does not inject
    | it. Without it the frame keeps its default height and taller sidebars get
    | an internal scrollbar.
    |
    | Leave this null and the package inlines the pinned SDK build it ships
    | with: nothing is fetched from a third-party origin, and a change on Help
    | Scout's side arrives with an upgrade rather than needing an edit here.
    | Set a URL to load a different build instead — one served from your own
    | origin, say. Set it to false to load nothing at all, which is the switch
    | for installations that bundle the SDK into a published view.
    |
    | The SDK is used for sizing only. Customer identity is never read from the
    | browser; see the security model, docs/security.md in the repository.
    |
    */

    'sdk_url' => env('HELPSCOUT_SIDEBAR_SDK_URL'),

    'no_match' => [
        'title' => 'No customer found',
        'message' => 'This conversation has no matching record in the application.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Frame Policy
    |--------------------------------------------------------------------------
    |
    | Help Scout renders the response in an iframe. If your application sets its
    | own CSP elsewhere, make sure frame-ancestors still allows Help Scout.
    |
    */

    'content_security_policy' => 'frame-ancestors https://secure.helpscout.net',

];
