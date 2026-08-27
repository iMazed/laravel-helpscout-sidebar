<?php

namespace Imazed\HelpScoutSidebar\Support;

/**
 * How the Help Scout JavaScript SDK reaches the sidebar document.
 *
 * Help Scout does not size app frames to their content, and does not inject
 * its own SDK into them. The packaged bridge reports a height by calling
 * HelpScout.setAppHeight(), which exists only once that SDK is present.
 *
 * The package ships a pinned build of the SDK and inlines it by default, so
 * rendering a sidebar makes no request to a third-party origin: the document
 * an agent sees carries customer data, and what executes alongside that data
 * should be code this package versions, not whatever a CDN serves today. A
 * change on Help Scout's side arrives with an upgrade of this package, while
 * `sdk_url` stays available for anyone who serves their own build.
 */
class HelpScoutSdk
{
    /**
     * The @helpscout/javascript-sdk release the packaged bundle was built
     * from. The bundle itself records its provenance in its header comment.
     */
    public const VERSION = '0.10.0';

    /**
     * Whether the packaged SDK bundle should be inlined.
     *
     * An unset `sdk_url` means "use what this package ships with".
     */
    public static function packaged(mixed $configured): bool
    {
        return $configured === null;
    }

    /**
     * The configured remote URL, or null when there is none to load.
     *
     * A non-empty string means "load this build instead of the packaged one".
     * An explicit false or empty string means "load nothing", which is the
     * switch for installations that bundle the SDK into their own published
     * view.
     */
    public static function url(mixed $configured): ?string
    {
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return null;
    }
}
