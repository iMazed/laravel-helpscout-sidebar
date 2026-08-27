<?php

namespace Imazed\HelpScoutSidebar\Support;

/**
 * Supplies the packaged stylesheet and JavaScript bridge to the default view.
 *
 * Both assets live in one place each, under `resources/`, and are inlined into
 * the rendered document. Inlining means a fresh install renders correctly and
 * sizes its iframe properly without anyone having to run `vendor:publish`
 * first, and it saves the iframe two extra round trips on every conversation
 * view.
 *
 * The same files are publishable for anyone who would rather serve them
 * statically or edit them:
 *
 *     php artisan vendor:publish --tag=helpscout-sidebar-assets
 */
class PackagedAssets
{
    /**
     * Cached file contents, keyed by absolute path.
     *
     * @var array<string, string>
     */
    protected static array $cache = [];

    /**
     * The packaged stylesheet, ready to place inside a <style> tag.
     */
    public static function styles(): string
    {
        return static::read(static::stylesPath());
    }

    /**
     * The packaged bridge, ready to place inside a <script> tag.
     */
    public static function script(): string
    {
        return static::read(static::scriptPath());
    }

    /**
     * The packaged Help Scout SDK bundle, ready to place inside a <script>
     * tag. See {@see HelpScoutSdk} for why it is shipped rather than loaded
     * from a CDN.
     */
    public static function sdk(): string
    {
        return static::read(static::sdkPath());
    }

    public static function stylesPath(): string
    {
        return __DIR__.'/../../resources/css/helpscout-sidebar.css';
    }

    public static function scriptPath(): string
    {
        return __DIR__.'/../../resources/js/helpscout-sidebar.js';
    }

    public static function sdkPath(): string
    {
        return __DIR__.'/../../resources/js/vendor/helpscout-javascript-sdk.js';
    }

    /**
     * Clear the in-process cache. Intended for tests.
     */
    public static function flush(): void
    {
        static::$cache = [];
    }

    /**
     * Read a packaged file once per process.
     *
     * A missing file yields an empty string rather than an exception: a broken
     * stylesheet should degrade the sidebar's looks, not take it down.
     */
    protected static function read(string $path): string
    {
        if (array_key_exists($path, static::$cache)) {
            return static::$cache[$path];
        }

        $contents = is_readable($path) ? file_get_contents($path) : false;

        return static::$cache[$path] = $contents === false ? '' : $contents;
    }
}
