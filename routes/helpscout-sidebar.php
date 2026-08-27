<?php

use Illuminate\Support\Facades\Route;
use Imazed\HelpScoutSidebar\Http\Controllers\SidebarController;

/*
|--------------------------------------------------------------------------
| Help Scout Sidebar Route
|--------------------------------------------------------------------------
|
| Registered automatically by the service provider. Set `route.enabled` to
| false in the published config if you would rather declare the route in your
| own application, pointing at SidebarController.
|
| The route intentionally has no auth middleware: Help Scout loads it in an
| iframe with no session. Signature verification is what protects it.
|
*/

if (config('helpscout-sidebar.route.enabled', true)) {
    Route::get(config('helpscout-sidebar.route.path', 'helpscout/sidebar'), SidebarController::class)
        ->middleware(config('helpscout-sidebar.route.middleware', ['web']))
        ->name(config('helpscout-sidebar.route.name', 'helpscout.sidebar'));
}
