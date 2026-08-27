<?php

namespace Imazed\HelpScoutSidebar\Tests\Unit;

use Imazed\HelpScoutSidebar\Sidebar\Section;
use Imazed\HelpScoutSidebar\Sidebar\Sidebar;
use Imazed\HelpScoutSidebar\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SidebarTest extends TestCase
{
    #[Test]
    public function it_escapes_values_added_through_the_fluent_api(): void
    {
        $html = Sidebar::make()
            ->section('Customer', fn (Section $section) => $section->row('Name', '<script>alert(1)</script>'))
            ->toHtml();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function it_renders_section_titles(): void
    {
        $html = Sidebar::make()
            ->section('Billing', fn (Section $section) => $section->row('Plan', 'Pro'))
            ->toHtml();

        $this->assertStringContainsString('Billing', $html);
        $this->assertStringContainsString('Pro', $html);
    }

    #[Test]
    public function it_omits_sections_with_no_items(): void
    {
        $html = Sidebar::make()->section('Empty')->toHtml();

        $this->assertStringNotContainsString('Empty', $html);
    }

    #[Test]
    public function an_empty_state_replaces_all_content(): void
    {
        $html = Sidebar::make()
            ->section('Customer', fn (Section $section) => $section->row('Name', 'Ada'))
            ->emptyState('Nothing here', 'No matching record.')
            ->toHtml();

        $this->assertStringContainsString('Nothing here', $html);
        $this->assertStringNotContainsString('Ada', $html);
    }

    #[Test]
    public function it_formats_supported_value_types(): void
    {
        $html = Sidebar::make()
            ->section('Types', function (Section $section): void {
                $section->row('True', true)
                    ->row('False', false)
                    ->row('Null', null)
                    ->row('Number', 42);
            })
            ->toHtml();

        $this->assertStringContainsString('Yes', $html);
        $this->assertStringContainsString('No', $html);
        $this->assertStringContainsString('42', $html);
    }

    #[Test]
    public function badge_tones_are_reduced_to_safe_css_class_names(): void
    {
        $html = Sidebar::make()
            ->section('Status', fn (Section $section) => $section->badge('Active', 'positive" onload="x'))
            ->toHtml();

        $this->assertStringContainsString('hs-sidebar-badge-positiveonloadx', $html);
        $this->assertStringNotContainsString('onload="x"', $html);
    }

    #[Test]
    public function when_applies_a_callback_conditionally(): void
    {
        $html = Sidebar::make()
            ->when(true, fn (Sidebar $sidebar) => $sidebar->section('Shown', fn (Section $s) => $s->row('a', 'b')))
            ->when(false, fn (Sidebar $sidebar) => $sidebar->section('Hidden', fn (Section $s) => $s->row('c', 'd')))
            ->toHtml();

        $this->assertStringContainsString('Shown', $html);
        $this->assertStringNotContainsString('Hidden', $html);
    }
}
