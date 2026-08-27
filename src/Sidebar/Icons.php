<?php

namespace Imazed\HelpScoutSidebar\Sidebar;

/**
 * The small set of icons available to header links.
 *
 * Inline SVG rather than an icon font or a sprite sheet, because the sidebar
 * is a self-contained document rendered into an iframe and every external
 * request costs an agent time on a page they open constantly.
 *
 * Paths are drawn on a 16x16 grid, stroked rather than filled, so they inherit
 * the surrounding text colour and stay legible at the one size they are used.
 */
class Icons
{
    /**
     * @var array<string, string>
     */
    protected const PATHS = [
        'user' => '<circle cx="8" cy="5" r="2.75"/><path d="M2.75 14c0-2.9 2.35-4.5 5.25-4.5s5.25 1.6 5.25 4.5"/>',
        'card' => '<rect x="1.75" y="3.75" width="12.5" height="8.5" rx="1.5"/><path d="M1.75 6.75h12.5"/>',
        'chart' => '<path d="M2.5 13.5v-4M6.5 13.5v-8M10.5 13.5v-5M14 13.5v-10"/>',
        'cog' => '<circle cx="8" cy="8" r="2.25"/><path d="M8 1.75v1.5M8 12.75v1.5M14.25 8h-1.5M3.25 8h-1.5M12.4 3.6l-1 1M4.6 11.4l-1 1M12.4 12.4l-1-1M4.6 4.6l-1-1"/>',
        'mail' => '<rect x="1.75" y="3.25" width="12.5" height="9.5" rx="1.5"/><path d="M2.5 4.5L8 8.75l5.5-4.25"/>',
        'ticket' => '<path d="M2.25 6.25V4.5a.75.75 0 01.75-.75h10a.75.75 0 01.75.75v1.75a1.75 1.75 0 000 3.5v1.75a.75.75 0 01-.75.75H3a.75.75 0 01-.75-.75V9.75a1.75 1.75 0 000-3.5z"/>',
        'external' => '<path d="M9.25 2.75h4v4M13.25 2.75L7.5 8.5"/><path d="M11.5 9.5v3.25a.75.75 0 01-.75.75h-7.5a.75.75 0 01-.75-.75v-7.5a.75.75 0 01.75-.75H6.5"/>',
    ];

    /**
     * The default when a link names no icon, or names one that does not exist.
     */
    public const FALLBACK = 'external';

    /**
     * Every icon name available to the `links` configuration.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(static::PATHS);
    }

    public static function has(string $name): bool
    {
        return array_key_exists($name, static::PATHS);
    }

    /**
     * One icon as an inline SVG element.
     *
     * The markup is a fixed string from the table above — never anything
     * supplied through configuration — so it is safe to render unescaped.
     */
    public static function svg(?string $name): string
    {
        $paths = static::PATHS[$name] ?? static::PATHS[static::FALLBACK];

        return '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" '
            .'stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            .$paths
            .'</svg>';
    }
}
