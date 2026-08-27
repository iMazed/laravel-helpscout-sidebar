<?php

namespace Imazed\HelpScoutSidebar\Sidebar;

use Illuminate\Contracts\Support\Htmlable;

/**
 * A titled group of items within the sidebar.
 *
 * Sections collect item descriptions rather than HTML strings. Rendering is
 * {@see Sidebar}'s job, which keeps escaping in one place instead of spread
 * across every call site that adds content.
 */
class Section
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $items = [];

    public function __construct(protected string $title) {}

    /**
     * A label and value pair, optionally linked.
     *
     * @param  mixed  $value  Any scalar, Stringable, BackedEnum, bool, or null.
     * @param  string|null  $url  Wraps the value in a link when provided.
     */
    public function row(string $label, mixed $value, ?string $url = null): self
    {
        return $this->push([
            'type' => 'row',
            'label' => $label,
            'value' => $value,
            'url' => $url,
        ]);
    }

    /**
     * A full sentence, rendered as running text.
     *
     * Rows are a label/value pair and align their value to the right, which
     * reads badly for anything longer than a few words. Use this when the
     * content is a statement rather than a field — a summary of account state,
     * an instruction to the agent.
     */
    public function note(string $text): self
    {
        return $this->push([
            'type' => 'note',
            'text' => $text,
        ]);
    }

    /**
     * A single prominent number or short value.
     */
    public function metric(string $label, mixed $value, ?string $description = null): self
    {
        return $this->push([
            'type' => 'metric',
            'label' => $label,
            'value' => $value,
            'description' => $description,
        ]);
    }

    /**
     * A short status pill.
     *
     * @param  string  $tone  Rendered as a CSS modifier; the shipped stylesheet
     *                        styles "neutral", "positive", "warning", "negative".
     */
    public function badge(string $label, string $tone = 'neutral'): self
    {
        return $this->push([
            'type' => 'badge',
            'label' => $label,
            'tone' => $tone,
        ]);
    }

    /**
     * A link out to the host application, opened in a new tab.
     */
    public function link(string $label, string $url, ?string $description = null): self
    {
        return $this->push([
            'type' => 'link',
            'label' => $label,
            'url' => $url,
            'description' => $description,
        ]);
    }

    /**
     * Render one of your own Blade views inside the section.
     *
     * The escape hatch for anything the fluent API cannot express. Output is
     * not escaped by this package, so only pass views you control.
     *
     * @param  array<string, mixed>  $data
     */
    public function blade(string $view, array $data = []): self
    {
        return $this->push([
            'type' => 'blade',
            'view' => $view,
            'data' => $data,
        ]);
    }

    /**
     * Insert pre-rendered markup.
     *
     * Not escaped. Prefer {@see self::row()} and friends for anything derived
     * from user or customer input.
     */
    public function html(Htmlable|string $html): self
    {
        return $this->push([
            'type' => 'html',
            'html' => $html,
        ]);
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

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function push(array $item): self
    {
        $this->items[] = $item;

        return $this;
    }
}
