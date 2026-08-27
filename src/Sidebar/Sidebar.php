<?php

namespace Imazed\HelpScoutSidebar\Sidebar;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Stringable;

/**
 * A fluent description of the sidebar, rendered to iframe-friendly HTML.
 *
 * Everything added through the fluent methods is escaped on render. The only
 * unescaped paths are {@see Section::html()} and {@see Section::blade()}, which
 * exist for content you produce yourself and are documented as such.
 */
class Sidebar implements Htmlable
{
    /**
     * @var array<int, Section>
     */
    protected array $sections = [];

    /**
     * @var array{title: string, message: string}|null
     */
    protected ?array $emptyState = null;

    protected mixed $subtitle = null;

    public function __construct(protected ?string $title = null) {}

    public static function make(?string $title = null): self
    {
        return new self($title);
    }

    public function title(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function subtitle(mixed $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /**
     * Replace the whole sidebar with a title and message.
     *
     * Used for the no-match state. Any sections added are ignored while an
     * empty state is set.
     */
    public function emptyState(string $title, string $message): self
    {
        $this->emptyState = ['title' => $title, 'message' => $message];

        return $this;
    }

    /**
     * Add a titled section.
     *
     * @param  Closure(Section): void|null  $callback
     */
    public function section(string $title, ?Closure $callback = null): self
    {
        $section = new Section($title);

        if ($callback !== null) {
            $callback($section);
        }

        $this->sections[] = $section;

        return $this;
    }

    /**
     * Apply a callback only when a condition holds.
     *
     * @param  callable(self, mixed): void  $callback
     */
    public function when(mixed $condition, callable $callback): self
    {
        if ($condition) {
            $callback($this, $condition);
        }

        return $this;
    }

    /**
     * @return array<int, Section>
     */
    public function sections(): array
    {
        return $this->sections;
    }

    /**
     * Render the sidebar to HTML.
     */
    public function toHtml(): string
    {
        if ($this->emptyState !== null) {
            return '<div class="hs-sidebar-empty">'
                .'<h1>'.e($this->emptyState['title']).'</h1>'
                .'<p>'.e($this->emptyState['message']).'</p>'
                .'</div>';
        }

        $html = '<div class="hs-sidebar-content">'.$this->renderHeader();

        foreach ($this->sections as $section) {
            $html .= $this->renderSection($section);
        }

        return $html.'</div>';
    }

    protected function renderHeader(): string
    {
        if ($this->title === null && $this->subtitle === null) {
            return '';
        }

        $html = '<header class="hs-sidebar-header">';

        if ($this->title !== null) {
            $html .= '<h1>'.e($this->title).'</h1>';
        }

        if ($this->subtitle !== null) {
            $html .= '<p>'.e($this->stringValue($this->subtitle)).'</p>';
        }

        return $html.'</header>';
    }

    protected function renderSection(Section $section): string
    {
        if ($section->isEmpty()) {
            return '';
        }

        $html = '<section class="hs-sidebar-section">'
            .'<h2 class="hs-sidebar-section-title">'.e($section->title()).'</h2>';

        foreach ($section->items() as $item) {
            $html .= $this->renderItem($item);
        }

        return $html.'</section>';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function renderItem(array $item): string
    {
        return match ($item['type']) {
            'row' => $this->renderRow($item),
            'metric' => $this->renderMetric($item),
            'badge' => $this->renderBadge($item),
            'link' => $this->renderLink($item),
            'blade' => view($item['view'], $item['data'])->render(),
            'html' => $item['html'] instanceof Htmlable ? $item['html']->toHtml() : (string) $item['html'],
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function renderRow(array $item): string
    {
        $value = e($this->stringValue($item['value']));

        if (is_string($item['url']) && $item['url'] !== '') {
            $value = '<a href="'.e($item['url']).'" target="_blank" rel="noopener noreferrer">'.$value.'</a>';
        }

        return '<dl class="hs-sidebar-row">'
            .'<dt>'.e($item['label']).'</dt>'
            .'<dd>'.$value.'</dd>'
            .'</dl>';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function renderMetric(array $item): string
    {
        $html = '<div class="hs-sidebar-metric">'
            .'<span>'.e($item['label']).'</span>'
            .'<strong>'.e($this->stringValue($item['value'])).'</strong>';

        if (is_string($item['description']) && $item['description'] !== '') {
            $html .= '<small>'.e($item['description']).'</small>';
        }

        return $html.'</div>';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function renderBadge(array $item): string
    {
        return '<span class="hs-sidebar-badge hs-sidebar-badge-'.e($this->cssToken($item['tone'])).'">'
            .e($item['label'])
            .'</span>';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function renderLink(array $item): string
    {
        $html = '<a class="hs-sidebar-link" href="'.e($item['url']).'" target="_blank" rel="noopener noreferrer">'
            .'<strong>'.e($item['label']).'</strong>';

        if (is_string($item['description']) && $item['description'] !== '') {
            $html .= '<span>'.e($item['description']).'</span>';
        }

        return $html.'</a>';
    }

    /**
     * Coerce any supported value into a displayable string.
     */
    protected function stringValue(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof Stringable => (string) $value,
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }

    /**
     * Reduce a value to characters safe to interpolate into a CSS class name.
     */
    protected function cssToken(mixed $value): string
    {
        $token = preg_replace('/[^a-z0-9_-]+/', '', mb_strtolower($this->stringValue($value)));

        return is_string($token) && $token !== '' ? $token : 'neutral';
    }
}
