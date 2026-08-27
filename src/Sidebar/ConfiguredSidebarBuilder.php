<?php

namespace Imazed\HelpScoutSidebar\Sidebar;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Imazed\HelpScoutSidebar\Contracts\BuildsSidebar;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;
use Imazed\HelpScoutSidebar\Support\RecordValues;
use Imazed\HelpScoutSidebar\Support\SidebarLinks;
use Imazed\HelpScoutSidebar\Support\ValueFormat;
use Psr\Log\LoggerInterface;
use Traversable;

/**
 * Builds the sidebar from the `sections` configuration.
 *
 * Most of what a sidebar shows is a label and a value read off a record, which
 * does not need to be code. This builder covers that case so that an
 * installation can put its customer data in front of an agent by editing a
 * config file, and reach for {@see BuildsSidebar} only when it has outgrown
 * that.
 *
 * The two do not combine. Point `builder` at your own class and the `sections`
 * configuration is ignored entirely: one place decides what the sidebar
 * contains, and there is never a question of what order two of them ran in.
 *
 * The format is bounded on purpose. Paths, formats, and truthiness — no
 * operators, no arithmetic, no interpolation. Everything past that line is a
 * builder, or an invokable class named as a single value. A configuration file
 * that grows conditionals is a programming language with no debugger.
 */
class ConfiguredSidebarBuilder implements BuildsSidebar
{
    public function __construct(
        protected ConfigRepository $config,
        protected RecordValues $values,
        protected ?LoggerInterface $logger = null,
        protected ?DefaultSidebarBuilder $fallback = null,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function build(Sidebar $sidebar, mixed $customer, HelpScoutContext $context): Sidebar
    {
        $sections = (array) $this->config->get('helpscout-sidebar.sections', []);

        if ($sections === []) {
            // Nothing configured yet. Show that resolution worked, so a fresh
            // install can tell the difference between "no sections" and "no
            // customer" without reading the source.
            return $this->fallback()->build($sidebar, $customer, $context);
        }

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $title = $section['title'] ?? null;

            if (! is_string($title) || trim($title) === '') {
                $this->logger?->warning('Help Scout sidebar skipped a section with no title.');

                continue;
            }

            if (array_key_exists('when', $section) && ! $this->values->truthy($section['when'], $customer)) {
                continue;
            }

            $sidebar->section(trim($title), function (Section $target) use ($section, $customer, $context): void {
                foreach ((array) ($section['items'] ?? []) as $item) {
                    if (is_array($item)) {
                        $this->item($target, $item, $customer, $context);
                    }
                }
            });
        }

        if ($sidebar->isEmpty()) {
            // Every configured value came back empty. Rendering the header and
            // nothing else looks like a broken install; showing that the record
            // resolved at least separates "wrong paths" from "wrong customer".
            $this->logger?->warning(
                'Help Scout sidebar rendered no content: sections are configured, but every value was missing on this record.'
            );

            return $this->fallback()->build($sidebar, $customer, $context);
        }

        return $sidebar;
    }

    /**
     * The builder used when there is nothing configured worth rendering.
     *
     * Injected when this class comes out of the container, so a rebound
     * DefaultSidebarBuilder is respected; constructed here only when this
     * class was built by hand without one.
     */
    protected function fallback(): DefaultSidebarBuilder
    {
        return $this->fallback ??= new DefaultSidebarBuilder;
    }

    /**
     * Add one configured item, or nothing at all.
     *
     * An item that resolves to null is dropped rather than rendered empty.
     * Every application has records with half their fields unset, and a column
     * of blank labels is worse than a shorter sidebar.
     *
     * @param  array<string, mixed>  $item
     */
    protected function item(Section $section, array $item, mixed $customer, HelpScoutContext $context): void
    {
        if (array_key_exists('when', $item) && ! $this->values->truthy($item['when'], $customer)) {
            return;
        }

        $type = is_string($item['type'] ?? null) ? $item['type'] : 'row';

        match ($type) {
            'row' => $this->row($section, $item, $customer, $context),
            'metric' => $this->metric($section, $item, $customer),
            'badge' => $this->badge($section, $item, $customer),
            'note' => $this->note($section, $item, $customer),
            'link' => $this->link($section, $item, $customer, $context),
            'list' => $this->list($section, $item, $customer),
            default => $this->logger?->warning('Help Scout sidebar skipped an item of unknown type.', [
                'type' => $type,
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function row(Section $section, array $item, mixed $customer, HelpScoutContext $context): void
    {
        $value = ValueFormat::apply($this->values->get($item['value'] ?? null, $customer), $item);

        if ($value === null) {
            return;
        }

        $section->row($this->label($item), $value, $this->url($item, $customer, $context));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function metric(Section $section, array $item, mixed $customer): void
    {
        $value = ValueFormat::apply($this->values->get($item['value'] ?? null, $customer), $item);

        if ($value === null) {
            return;
        }

        $description = is_string($item['description'] ?? null) ? $item['description'] : null;

        $section->metric($this->label($item), $value, $description);
    }

    /**
     * A badge, with its tone chosen by the value it carries.
     *
     * @param  array<string, mixed>  $item
     */
    protected function badge(Section $section, array $item, mixed $customer): void
    {
        $raw = $this->values->get($item['value'] ?? null, $customer);
        $value = ValueFormat::apply($raw, $item);

        if ($value === null) {
            return;
        }

        $tones = (array) ($item['tones'] ?? []);
        $key = is_scalar($raw) ? (string) $raw : '';
        $tone = $tones[$key] ?? $item['tone'] ?? 'neutral';

        $section->badge(is_string($item['label'] ?? null) ? $item['label'] : $value, is_string($tone) ? $tone : 'neutral');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function note(Section $section, array $item, mixed $customer): void
    {
        // A note is running text, so it may be written in the config directly
        // rather than read off the record.
        $text = array_key_exists('value', $item)
            ? ValueFormat::apply($this->values->get($item['value'], $customer), $item)
            : (is_string($item['text'] ?? null) ? $item['text'] : null);

        if ($text === null || trim($text) === '') {
            return;
        }

        $section->note($text);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function link(Section $section, array $item, mixed $customer, HelpScoutContext $context): void
    {
        $url = $this->url($item, $customer, $context);

        if ($url === null) {
            return;
        }

        $description = is_string($item['description'] ?? null) ? $item['description'] : null;

        $section->link($this->label($item), $url, $description);
    }

    /**
     * Repeat a label and value over a collection on the record.
     *
     * The one shape that is not a flat field: recent orders, recent events,
     * open tickets. Without it, every activity list needs a builder.
     *
     * @param  array<string, mixed>  $item
     */
    protected function list(Section $section, array $item, mixed $customer): void
    {
        $source = $this->values->get($item['source'] ?? null, $customer);

        if (! is_iterable($source)) {
            return;
        }

        $rows = $source instanceof Traversable ? iterator_to_array($source) : $source;
        $limit = is_int($item['limit'] ?? null) ? $item['limit'] : 5;
        $shown = 0;

        foreach ($rows as $row) {
            if ($shown >= $limit) {
                return;
            }

            $label = ValueFormat::apply($this->values->get($item['label'] ?? null, $row), $item);
            $value = ValueFormat::apply($this->values->get($item['value'] ?? null, $row), [
                'format' => $item['value_format'] ?? null,
            ]);

            if ($label === null && $value === null) {
                continue;
            }

            $section->row($label ?? '', $value ?? '');
            $shown++;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function label(array $item): string
    {
        return is_string($item['label'] ?? null) ? $item['label'] : '';
    }

    /**
     * A URL for an item, with the same placeholders the header links use.
     *
     * @param  array<string, mixed>  $item
     */
    protected function url(array $item, mixed $customer, HelpScoutContext $context): ?string
    {
        if (! is_string($item['url'] ?? null)) {
            return null;
        }

        return SidebarLinks::url($item['url'], $customer, $context, SidebarLinks::baseFrom($this->config));
    }
}
