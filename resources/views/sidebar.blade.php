{{--
    Default sidebar document.

    Rendered inside Help Scout's app iframe, so it is a complete HTML document
    rather than a fragment. Publishable via the helpscout-sidebar-views tag —
    a published copy takes precedence over this one permanently, so re-publish
    with --force after upgrades to pick up changes.

    Available variables:
      $sidebar   Imazed\HelpScoutSidebar\Sidebar\Sidebar  — call toHtml()
      $context   Imazed\HelpScoutSidebar\Support\HelpScoutContext
      $customer  mixed|null — the resolved record, or null when nothing matched
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="referrer" content="no-referrer">
        <title>{{ config('app.name') }}</title>
        <style>{!! \Imazed\HelpScoutSidebar\Support\PackagedAssets::styles() !!}</style>
    </head>
    <body>
        <main data-helpscout-sidebar-root>
            {!! $sidebar->toHtml() !!}
        </main>

        {{--
            Identifiers only. Help Scout sends no customer details in the
            callback, and this package deliberately adds none here: anything the
            browser can read, the browser can also alter.
        --}}
        <script>
            window.HelpScoutSidebarContext = @json($context->toArray());
        </script>
        <script>{!! \Imazed\HelpScoutSidebar\Support\PackagedAssets::script() !!}</script>

        {{--
            Help Scout does not size app frames to their content, and does not
            inject its own SDK into them. Without HelpScout.setAppHeight() the
            frame keeps its default height and a taller sidebar scrolls inside
            it. The packaged build is inlined by default so this document,
            which carries customer data, never loads code from a third-party
            origin; `sdk_url` swaps in a different build or switches loading
            off.
        --}}
        @php($helpScoutSdkUrl = \Imazed\HelpScoutSidebar\Support\HelpScoutSdk::url(config('helpscout-sidebar.sdk_url')))

        @if (\Imazed\HelpScoutSidebar\Support\HelpScoutSdk::packaged(config('helpscout-sidebar.sdk_url')))
            <script>{!! \Imazed\HelpScoutSidebar\Support\PackagedAssets::sdk() !!}</script>
            <script>
                (function () {
                    var sdk = window.__helpScoutSidebarSdk || {};

                    // The SDK has shipped its entry point as a default export,
                    // a named one, and the namespace itself. Accept all three
                    // rather than break sizing on a minor release.
                    var helpScout = sdk.default || sdk.HelpScout || sdk;

                    if (!helpScout || typeof helpScout.setAppHeight !== 'function') {
                        console.warn(
                            '[helpscout-sidebar] The packaged Help Scout SDK exposed no ' +
                            'setAppHeight(). The iframe will keep its default height.'
                        );

                        return;
                    }

                    window.HelpScout = window.HelpScout || helpScout;

                    // The observers are already attached; they simply had
                    // nothing to report to until now.
                    if (window.HelpScoutSidebar) {
                        window.HelpScoutSidebar.resize();
                    }
                })();
            </script>
        @elseif ($helpScoutSdkUrl !== null)
            <script type="module">
                // A module script fails silently in the console when the import
                // throws, and sizing then does nothing for no visible reason.
                // Say what happened instead.
                try {
                    const sdk = await import({{ Illuminate\Support\Js::from($helpScoutSdkUrl) }});

                    // The SDK has shipped its entry point as a default export, a
                    // named one, and the namespace itself. Accept all three
                    // rather than break sizing on a minor release.
                    const helpScout = sdk.default || sdk.HelpScout || sdk;

                    if (!helpScout || typeof helpScout.setAppHeight !== 'function') {
                        console.warn(
                            '[helpscout-sidebar] Loaded the SDK but found no setAppHeight(). ' +
                            'The iframe will keep its default height. Exports seen:',
                            Object.keys(sdk)
                        );
                    } else {
                        window.HelpScout = window.HelpScout || helpScout;

                        // The observers are already attached; they simply had
                        // nothing to report to until now.
                        if (window.HelpScoutSidebar) {
                            window.HelpScoutSidebar.resize();
                        }
                    }
                } catch (error) {
                    console.warn(
                        '[helpscout-sidebar] Could not load the Help Scout SDK from ' +
                        {{ Illuminate\Support\Js::from($helpScoutSdkUrl) }} +
                        '. The iframe will keep its default height.',
                        error
                    );
                }
            </script>
        @endif
    </body>
</html>
