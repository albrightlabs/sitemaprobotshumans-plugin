<?php namespace Albrightlabs\SitemapRobotsHumans\Classes;

use App;
use Log;
use Config;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Classes\Controller;
use League\HTMLToMarkdown\HtmlConverter;
use Albrightlabs\SitemapRobotsHumans\Models\Setting;

class LlmsGenerator
{
    /**
     * CSS classes that typically indicate hidden-on-some-viewport content.
     * We strip these nodes before conversion to avoid duplicated mobile/desktop copy.
     */
    protected const HIDDEN_CLASS_PATTERNS = [
        'd-none', 'd-sm-none', 'd-md-none', 'd-lg-none', 'd-xl-none',
        'sr-only', 'visually-hidden', 'hidden', 'is-hidden',
    ];

    public static function generateIndex(): string
    {
        $baseUrl = rtrim(url('/'), '/');
        $siteTitle = static::siteTitle();

        $summary = trim((string) Setting::get('llms_summary', ''));
        $sections = Setting::get('llms_sections', []) ?: [];

        $pages = static::eligiblePages();
        $grouped = static::groupPages($pages, $sections);

        $out = "# {$siteTitle}\n\n";
        if ($summary !== '') {
            $out .= "> {$summary}\n\n";
        }

        foreach ($grouped as $label => $pageList) {
            if (empty($pageList)) {
                continue;
            }
            $out .= "## {$label}\n\n";
            foreach ($pageList as $page) {
                $title = static::pageTitle($page);
                $desc = static::pageDescription($page);
                $url = $baseUrl . $page->url;
                $out .= $desc !== ''
                    ? "- [{$title}]({$url}): {$desc}\n"
                    : "- [{$title}]({$url})\n";
            }
            $out .= "\n";
        }

        return rtrim($out) . "\n";
    }

    public static function generateFull(): string
    {
        $baseUrl = rtrim(url('/'), '/');
        $siteTitle = static::siteTitle();
        $selector = trim((string) Setting::get('llms_full_content_selector', 'main')) ?: 'main';

        $converter = new HtmlConverter([
            'header_style' => 'atx',
            'strip_tags' => true,
            'strip_placeholder_links' => true,
            'remove_nodes' => 'script style noscript img svg picture source button form input select textarea iframe',
            'hard_break' => false,
        ]);

        $theme = Theme::getActiveTheme();
        $controller = new Controller($theme);

        $originalLocale = App::getLocale();
        App::setLocale('en');

        $out = "# {$siteTitle}\n\n";

        try {
            foreach (static::eligiblePages() as $page) {
                try {
                    $response = $controller->render($page->getBaseFileName());
                    $html = is_string($response) ? $response : (method_exists($response, 'getContent') ? $response->getContent() : '');
                    $fragment = static::extractContent($html, $selector);
                    if ($fragment === '') {
                        continue;
                    }
                    $markdown = $converter->convert($fragment);
                    $markdown = static::cleanMarkdown($markdown);
                    if ($markdown === '') {
                        continue;
                    }

                    $title = static::pageTitle($page);
                    $url = $baseUrl . $page->url;

                    $out .= "## {$title}\n\n";
                    $out .= "{$url}\n\n";
                    $out .= $markdown . "\n\n---\n\n";
                } catch (\Throwable $e) {
                    Log::warning('llms-full.txt render failed for page ' . $page->getBaseFileName() . ': ' . $e->getMessage());
                    continue;
                }
            }
        } finally {
            App::setLocale($originalLocale);
        }

        return rtrim($out) . "\n";
    }

    protected static function siteTitle(): string
    {
        if (Config::get('cms.active_theme')) {
            $theme = Theme::getActiveTheme();
            if ($theme && ($name = $theme->getConfigValue('name'))) {
                return $name;
            }
        }
        return config('app.name', 'Site');
    }

    protected static function eligiblePages()
    {
        return Page::all()->filter(function ($page) {
            if (!empty($page->is_hidden)) {
                return false;
            }
            if (empty($page->enabled_in_llms)) {
                return false;
            }
            if (str_contains($page->url ?? '', ':')) {
                return false;
            }
            return true;
        })->sortBy('url')->values();
    }

    protected static function groupPages($pages, array $sections): array
    {
        $grouped = [];
        foreach ($sections as $section) {
            $label = trim((string) ($section['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $grouped[$label] = [];
        }

        $otherLabel = 'Pages';
        if (!isset($grouped[$otherLabel])) {
            $grouped[$otherLabel] = [];
        }

        foreach ($pages as $page) {
            $matched = false;
            foreach ($sections as $section) {
                $label = trim((string) ($section['label'] ?? ''));
                $prefix = trim((string) ($section['url_prefix'] ?? ''));
                if ($label === '' || $prefix === '') {
                    continue;
                }
                if ($page->url === $prefix || str_starts_with($page->url ?? '', rtrim($prefix, '/') . '/')) {
                    $grouped[$label][] = $page;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $grouped[$otherLabel][] = $page;
            }
        }

        return $grouped;
    }

    protected static function pageTitle($page): string
    {
        return trim((string) ($page->title ?? $page->meta_title ?? $page->getFileName()));
    }

    protected static function pageDescription($page): string
    {
        return trim((string) ($page->description ?? $page->meta_description ?? ''));
    }

    protected static function extractContent(string $html, string $selector): string
    {
        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($doc);

        static::stripUnwantedNodes($xpath);

        $expr = static::selectorToXpath($selector);
        $nodes = $xpath->query($expr);

        if (!$nodes || $nodes->length === 0) {
            $body = $xpath->query('//body');
            if ($body && $body->length > 0) {
                return static::nodeInnerHtml($body->item(0));
            }
            return $html;
        }

        $buf = '';
        foreach ($nodes as $node) {
            $buf .= static::nodeInnerHtml($node);
        }
        return $buf;
    }

    /**
     * Remove nodes that are hidden on some viewport (to avoid duplicate content),
     * javascript: links, empty anchors, and pure anchor/fragment links.
     */
    protected static function stripUnwantedNodes(\DOMXPath $xpath): void
    {
        // Hidden-by-class nodes (bootstrap responsive utilities, a11y "sr-only", etc.)
        foreach (self::HIDDEN_CLASS_PATTERNS as $class) {
            $expr = "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
            $nodes = iterator_to_array($xpath->query($expr) ?: []);
            foreach ($nodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        // Anchors with no useful destination: javascript:, empty, or pure fragment (#...)
        $badLinks = $xpath->query("//a[starts-with(@href,'javascript:') or not(@href) or @href='' or @href='#' or starts-with(@href,'#')]");
        if ($badLinks) {
            foreach (iterator_to_array($badLinks) as $node) {
                static::unwrapNode($node);
            }
        }

        // Buttons — nav/CTA noise for LLMs.
        $buttons = $xpath->query("//button");
        if ($buttons) {
            foreach (iterator_to_array($buttons) as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    /**
     * Replace a node with its children (removes the wrapping tag but keeps the text).
     */
    protected static function unwrapNode(\DOMNode $node): void
    {
        if (!$node->parentNode) {
            return;
        }
        while ($node->firstChild) {
            $node->parentNode->insertBefore($node->firstChild, $node);
        }
        $node->parentNode->removeChild($node);
    }

    protected static function nodeInnerHtml(\DOMNode $node): string
    {
        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $node->ownerDocument->saveHTML($child);
        }
        return $inner;
    }

    protected static function selectorToXpath(string $selector): string
    {
        $selector = trim($selector);
        if ($selector === '') {
            return '//main';
        }
        if (str_starts_with($selector, '#')) {
            $id = substr($selector, 1);
            return "//*[@id='{$id}']";
        }
        if (str_starts_with($selector, '.')) {
            $class = substr($selector, 1);
            return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
        }
        return '//' . $selector;
    }

    /**
     * Post-process the generated markdown: decode entities, collapse whitespace,
     * drop lingering placeholder artifacts, de-duplicate repeated blocks.
     */
    protected static function cleanMarkdown(string $md): string
    {
        // Decode HTML entities (e.g. &amp; -> &).
        $md = html_entity_decode($md, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize newlines and strip trailing per-line whitespace.
        $md = str_replace(["\r\n", "\r"], "\n", $md);
        $md = preg_replace('/[ \t]+$/m', '', $md) ?? $md;

        // Drop leftover stray "](javascript:...)" fragments.
        $md = preg_replace('/\]\(javascript:[^)]*\)/', ']', $md) ?? $md;

        // Remove markdown links whose URL is only a # fragment (keep the label).
        $md = preg_replace('/\[([^\]]*)\]\(#[^)]*\)/', '$1', $md) ?? $md;

        // Remove markdown links whose label is whitespace-only or empty.
        $md = preg_replace('/\[\s*\]\([^)]*\)/', '', $md) ?? $md;

        // Collapse immediate word-doubling that responsive CTA patterns produce
        // (e.g. "Start with an AI AuditStart with an AI Audit").
        $md = preg_replace_callback('/([A-Z][\w \-\&\$→]{7,}?)\1/u', fn ($m) => $m[1], $md) ?? $md;

        // Normalize leading whitespace on heading lines so indented headings ("   ## Foo")
        // render as proper ATX headings.
        $md = preg_replace('/^[ \t]+(#{1,6} )/m', '$1', $md) ?? $md;

        // Ensure a blank line before and after ATX headings to prevent heading-text welding.
        $md = preg_replace('/([^\n])\n(#{1,6} )/', "\$1\n\n\$2", $md) ?? $md;

        $lines = explode("\n", $md);

        // Collapse 2+ consecutive blank lines into a single blank line.
        $collapsed = [];
        $previousBlank = false;
        foreach ($lines as $l) {
            $isBlank = (trim($l) === '');
            if ($isBlank && $previousBlank) {
                continue;
            }
            $collapsed[] = $l;
            $previousBlank = $isBlank;
        }

        // De-duplicate non-trivial paragraphs: if the exact same block appears
        // more than once in the document (e.g. card preview + modal long copy),
        // keep only the first occurrence. Operates on paragraph-level blocks
        // separated by blank lines; only dedupes blocks of ≥40 chars to avoid
        // nuking intentional short repetitions.
        $blocks = [];
        $current = [];
        foreach ($collapsed as $line) {
            if (trim($line) === '') {
                if (!empty($current)) {
                    $blocks[] = implode("\n", $current);
                    $current = [];
                }
                $blocks[] = '';
                continue;
            }
            $current[] = $line;
        }
        if (!empty($current)) {
            $blocks[] = implode("\n", $current);
        }

        $seen = [];
        $deduped = [];
        foreach ($blocks as $block) {
            $trimmed = trim($block);
            if ($trimmed === '') {
                $deduped[] = $block;
                continue;
            }
            if (strlen($trimmed) >= 40) {
                $key = preg_replace('/\s+/', ' ', $trimmed);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            }
            $deduped[] = $block;
        }

        $md = implode("\n", $deduped);

        // Final collapse pass (dedup may have left double-blanks).
        $md = preg_replace("/\n{3,}/", "\n\n", $md) ?? $md;

        return trim($md);
    }
}
