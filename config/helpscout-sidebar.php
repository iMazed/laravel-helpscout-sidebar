<?php

use Imazed\HelpScoutSidebar\Emails\ContextEmailProvider;
use Imazed\HelpScoutSidebar\Emails\FallbackEmailProvider;
use Imazed\HelpScoutSidebar\Emails\MailboxApiEmailProvider;
use Imazed\HelpScoutSidebar\Resolvers\EmailCustomerResolver;
use Imazed\HelpScoutSidebar\Sidebar\DefaultSidebarBuilder;

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
    | from them. See the "Trust boundaries" section of the README.
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
    |
    | Create an internal OAuth2 app in Help Scout (Your Profile -> My Apps) and
    | put its App ID and App Secret here. The package uses the client
    | credentials grant, so there is no redirect URL and no user consent step.
    |
    | Requires a Help Scout plan that includes API access. On the Free plan
    | leave this disabled; see the README FAQ.
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

    'customer_model' => env('HELPSCOUT_SIDEBAR_CUSTOMER_MODEL', 'App\\Models\\User'),
    'customer_email_column' => env('HELPSCOUT_SIDEBAR_CUSTOMER_EMAIL_COLUMN', 'email'),

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

    'builder' => DefaultSidebarBuilder::class,
    'view' => 'helpscout-sidebar::sidebar',

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
