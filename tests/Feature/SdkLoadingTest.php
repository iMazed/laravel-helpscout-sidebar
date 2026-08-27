<?php

namespace Imazed\HelpScoutSidebar\Tests\Feature;

use Imazed\HelpScoutSidebar\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * How the Help Scout JavaScript SDK reaches the document. The scripts render
 * on every response, so these tests need no customer to resolve.
 */
class SdkLoadingTest extends TestCase
{
    #[Test]
    public function it_inlines_the_packaged_sdk_by_default(): void
    {
        $this->get($this->signedUrl($this->callbackParameters()))
            ->assertOk()
            ->assertSee('__helpScoutSidebarSdk', false)
            // Present only inside the bundle itself, so this fails when the
            // vendored file is missing rather than when the wiring is.
            ->assertSee('SET_APP_HEIGHT', false)
            // The default document must not fetch code from anywhere.
            ->assertDontSee('type="module"', false)
            ->assertDontSee('esm.sh', false);
    }

    #[Test]
    public function a_configured_url_overrides_the_packaged_sdk(): void
    {
        config()->set('helpscout-sidebar.sdk_url', 'https://assets.example.test/helpscout-sdk.js');

        $this->get($this->signedUrl($this->callbackParameters()))
            ->assertOk()
            ->assertSee('type="module"', false)
            ->assertSee('assets.example.test', false)
            ->assertDontSee('__helpScoutSidebarSdk', false);
    }

    #[Test]
    public function it_loads_nothing_when_sdk_loading_is_switched_off(): void
    {
        config()->set('helpscout-sidebar.sdk_url', false);

        $this->get($this->signedUrl($this->callbackParameters()))
            ->assertOk()
            ->assertDontSee('type="module"', false)
            ->assertDontSee('__helpScoutSidebarSdk', false);
    }
}
