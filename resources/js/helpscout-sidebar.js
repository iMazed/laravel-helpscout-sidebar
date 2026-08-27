/**
 * Help Scout sidebar bridge.
 *
 * A dependency-free helper for the iframe this package renders into. It exists
 * mainly to keep the iframe the right height: Help Scout does not size app
 * frames to their content, so an app that does not report its height is either
 * clipped or padded with empty space.
 *
 * Deliberately absent: any way to read the customer's email address. The
 * Help Scout JavaScript SDK can expose it, but a value that reaches the server
 * from the browser is not covered by the request signature and can be edited by
 * anyone who can load the iframe. Customer identity is established server-side
 * through the Mailbox API instead. See the README's "Trust boundaries" section.
 */
(function () {
    'use strict';

    var listeners = [];
    var currentContext = window.HelpScoutSidebarContext || {};

    /**
     * Measure the rendered sidebar, including margins that escape the root.
     *
     * @returns {number} Height in CSS pixels, never less than 1.
     */
    function measure() {
        var root = document.querySelector('[data-helpscout-sidebar-root]') || document.body;

        return Math.max(1, Math.ceil(Math.max(
            root.scrollHeight || 0,
            root.offsetHeight || 0,
            document.body.scrollHeight || 0,
            document.documentElement.scrollHeight || 0
        )));
    }

    /**
     * Report a height to Help Scout, if the host SDK is present.
     *
     * @param {number} height
     * @returns {boolean} Whether Help Scout accepted the height.
     */
    function setAppHeight(height) {
        var next = Math.max(1, Math.ceil(Number(height) || measure()));

        if (window.HelpScout && typeof window.HelpScout.setAppHeight === 'function') {
            window.HelpScout.setAppHeight(next);
            document.documentElement.classList.add('hs-sidebar-height-managed');

            return true;
        }

        return false;
    }

    function resize() {
        return setAppHeight(measure());
    }

    /**
     * Keep the reported height in step with the rendered content.
     */
    function observeHeight() {
        var root = document.querySelector('[data-helpscout-sidebar-root]') || document.body;

        resize();

        window.addEventListener('load', resize);
        window.addEventListener('resize', resize);

        // Late layout shifts — web fonts, images — land after the first paint.
        window.setTimeout(resize, 100);
        window.setTimeout(resize, 500);

        if (typeof window.ResizeObserver === 'function') {
            new window.ResizeObserver(resize).observe(root);
        }
    }

    window.HelpScoutSidebar = {
        /**
         * The signed callback parameters, as the server received them.
         *
         * Identifiers only — Help Scout does not send customer details here.
         *
         * @returns {Object}
         */
        context: function () {
            return currentContext;
        },

        /**
         * Subscribe to context changes. Fires immediately with the current value.
         *
         * @param {Function} listener
         * @returns {Function} Unsubscribe.
         */
        onContext: function (listener) {
            if (typeof listener !== 'function') {
                return function () {};
            }

            listeners.push(listener);
            listener(currentContext);

            return function () {
                listeners = listeners.filter(function (item) {
                    return item !== listener;
                });
            };
        },

        /**
         * Replace the context and notify subscribers.
         *
         * @param {Object} context
         */
        refresh: function (context) {
            currentContext = context || {};

            listeners.slice().forEach(function (listener) {
                listener(currentContext);
            });
        },

        resize: resize,
        setAppHeight: setAppHeight,
        observeHeight: observeHeight,

        /**
         * Open a URL in Help Scout's side panel, falling back to a new tab.
         *
         * @param {string} url
         */
        openSidePanel: function (url) {
            if (window.HelpScout && typeof window.HelpScout.openSidePanel === 'function') {
                window.HelpScout.openSidePanel(url);

                return;
            }

            window.open(url, '_blank', 'noopener,noreferrer');
        }
    };

    window.dispatchEvent(new CustomEvent('helpscout-sidebar:ready', { detail: currentContext }));

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observeHeight);
    } else {
        observeHeight();
    }
}());
