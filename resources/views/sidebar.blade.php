{{--
    Default sidebar document.

    Rendered inside Help Scout's app iframe, so it is a complete HTML document
    rather than a fragment. Publish it with

        php artisan vendor:publish --tag=helpscout-sidebar-views

    if you want control over the markup.

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
    </body>
</html>
