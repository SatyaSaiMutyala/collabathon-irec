<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitiser for admin-authored rich text.
 *
 * The terms an admin types are stored as HTML and rendered inside a WebView on both the
 * broker and developer apps. Storing it raw would make the field a stored-XSS channel:
 * one compromised admin account, and every broker who opens that project runs the script.
 *
 * Allowlist, not blocklist. Blocklists lose — `<img onerror>`, `javascript:` in an href,
 * `<svg><script>`, data: URLs, and the next trick nobody enumerated. Anything not named
 * here is unwrapped (children kept, tag dropped) so sanitising never silently deletes the
 * words someone wrote, only the markup around them.
 */
class RichText
{
    /** Formatting a terms document plausibly needs, and nothing else. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'blockquote', 'a', 'table', 'thead', 'tbody',
        'tr', 'th', 'td', 'hr', 'span', 'div',
    ];

    /** Per-tag attribute allowlist. Everything else — including every on* handler — goes. */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'title'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    /** Schemes an href may use. Notably excludes javascript: and data:. */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function sanitize(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $document = new DOMDocument();

        // Malformed markup is expected from a contenteditable field; parse errors are not
        // a reason to reject the content, so warnings are suppressed and the DOM is used
        // as recovered. The meta charset keeps UTF-8 from being mangled into entities.
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="rt-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();

        $root = $document->getElementById('rt-root');

        if (! $root) {
            return null;
        }

        self::clean($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        $out = trim($out);

        // Markup that carried no words is nothing, not an empty paragraph the app then
        // has to decide whether to render.
        return trim(strip_tags($out)) === '' ? null : $out;
    }

    /** Depth-first, iterating over a snapshot because the walk mutates the child list. */
    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    // <script>/<style> carry payload in their text, so those are removed
                    // outright; anything else is unwrapped and its words survive.
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                        $child->parentNode->removeChild($child);
                        continue;
                    }

                    self::clean($child);
                    self::unwrap($child);
                    continue;
                }

                self::stripAttributes($child, $tag);
                self::clean($child);
            } elseif (! ($child instanceof \DOMText)) {
                // Comments, CDATA, processing instructions — no reason for any of them.
                $child->parentNode->removeChild($child);
            }
        }
    }

    private static function stripAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! in_array(strtolower($attribute->name), $allowed, true)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($element->hasAttribute('href') && ! self::isSafeUrl($element->getAttribute('href'))) {
            $element->removeAttribute('href');
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        // Relative and anchor links carry no scheme and cannot execute.
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, self::ALLOWED_SCHEMES, true);
    }

    /** Replaces an element with its own children, preserving order. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    /** A plain-text preview for list rows and previews, with tags and entities resolved. */
    public static function excerpt(?string $html, int $length = 160): ?string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text);

        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
    }
}
