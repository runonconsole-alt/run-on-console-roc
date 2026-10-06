<?php
/**
 * Run On Console CMS — HTML sanitiser and SEO helpers.
 *
 * The previous approach used two regular expressions:
 *
 *   preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
 *   preg_replace('/on\w+="[^"]*"/i', '', $content);
 *
 * Both are bypassable. `<script src="x.js">` with no closing tag survives the
 * first, and `onerror='alert(1)'` or `onerror=alert(1)` survive the second,
 * because the pattern only matches double quotes. Since index.php prints blog
 * content into the page unescaped, anything that gets through becomes stored
 * XSS on the live site — and the editor invites pasting HTML from other pages.
 *
 * This parses the HTML and rebuilds it from an allowlist instead, so anything
 * not explicitly permitted is removed rather than pattern-matched.
 *
 * Require this from any endpoint that accepts rich content.
 */

if (!function_exists('rocCmsAllowedTags')) {

/** Tags kept, mapped to the tag actually emitted. */
function rocCmsAllowedTags(): array {
    return [
        'p' => 'p', 'br' => 'br', 'hr' => 'hr',
        'h1' => 'h2', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h5',
        'strong' => 'strong', 'b' => 'strong', 'em' => 'em', 'i' => 'em',
        'u' => 'u', 's' => 's', 'strike' => 's', 'del' => 's',
        'mark' => 'mark', 'sub' => 'sub', 'sup' => 'sup',
        'blockquote' => 'blockquote', 'ul' => 'ul', 'ol' => 'ol', 'li' => 'li',
        'a' => 'a', 'img' => 'img', 'figure' => 'figure', 'figcaption' => 'figcaption',
        'table' => 'table', 'thead' => 'thead', 'tbody' => 'tbody', 'tfoot' => 'tfoot',
        'tr' => 'tr', 'th' => 'th', 'td' => 'td', 'caption' => 'caption',
        'pre' => 'pre', 'code' => 'code',
    ];
}

/** Attributes kept, per emitted tag. Everything else is dropped. */
function rocCmsAllowedAttributes(): array {
    return [
        'a'   => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'th'  => ['colspan', 'rowspan', 'scope'],
        'td'  => ['colspan', 'rowspan'],
    ];
}

/** Tags removed together with their contents. */
function rocCmsStripWithContent(): array {
    return ['script', 'style', 'noscript', 'iframe', 'object', 'embed', 'applet',
            'form', 'input', 'button', 'select', 'textarea', 'link', 'meta',
            'base', 'frame', 'frameset', 'svg', 'math', 'template'];
}

function rocCmsSafeUrl(string $url, bool $allowRelative = true): ?string {
    $url = trim($url);
    if ($url === '') return null;

    // Reject anything that decodes to a script-bearing scheme.
    $probe = strtolower(preg_replace('/[\s\x00-\x1F]+/', '', $url));
    foreach (['javascript:', 'vbscript:', 'data:text/html', 'data:application'] as $bad) {
        if (str_starts_with($probe, $bad)) return null;
    }
    // data: images are allowed but capped, so a page is not stuffed with base64.
    if (str_starts_with($probe, 'data:image/')) {
        return strlen($url) <= 200000 ? $url : null;
    }
    if (preg_match('#^https?://#i', $url)) return $url;
    if (preg_match('#^(mailto:|tel:)#i', $url)) return $url;
    if ($allowRelative && (str_starts_with($url, '/') || str_starts_with($url, '#'))) return $url;
    if ($allowRelative && !str_contains($url, ':')) return $url;   // relative path
    return null;
}

/**
 * Rebuild HTML from an allowlist.
 *
 * @param string $html  untrusted HTML from the editor
 * @param string $siteHost  host treated as internal when deciding rel/target
 */
function rocCmsSanitizeHtml(string $html, string $siteHost = 'runonconsole.com'): string {
    $html = trim($html);
    if ($html === '') return '';

    $allowedTags = rocCmsAllowedTags();
    $allowedAttrs = rocCmsAllowedAttributes();
    $stripEntirely = rocCmsStripWithContent();

    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);

    // Wrap so the fragment parses predictably, and force UTF-8 interpretation.
    $wrapped = '<?xml encoding="UTF-8"?><!DOCTYPE html><html><body><div id="roc-root">'
             . $html . '</div></body></html>';
    $ok = $doc->loadHTML($wrapped, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$ok) return '';

    $root = $doc->getElementById('roc-root');
    if (!$root) {
        $divs = $doc->getElementsByTagName('div');
        $root = $divs->length ? $divs->item(0) : null;
    }
    if (!$root) return '';

    // Remove dangerous subtrees first, contents included.
    foreach ($stripEntirely as $tagName) {
        $nodes = iterator_to_array($root->getElementsByTagName($tagName));
        foreach ($nodes as $node) {
            if ($node->parentNode) $node->parentNode->removeChild($node);
        }
    }
    // Comments can carry conditional-comment payloads.
    $xpath = new DOMXPath($doc);
    foreach (iterator_to_array($xpath->query('//comment()', $root)) as $comment) {
        if ($comment->parentNode) $comment->parentNode->removeChild($comment);
    }

    rocCmsCleanNode($doc, $root, $allowedTags, $allowedAttrs, $siteHost);

    // Serialise children of the wrapper only.
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }

    // Tidy the empties left behind by unwrapping.
    $out = preg_replace('#<(p|li|h2|h3|h4|h5|blockquote)>\s*</\1>#i', '', $out);
    return trim($out);
}

/** Recursively enforce the allowlist. Disallowed elements are unwrapped. */
function rocCmsCleanNode(DOMDocument $doc, DOMNode $node, array $allowedTags,
                        array $allowedAttrs, string $siteHost): void {
    // Snapshot, because the list mutates while we walk it.
    $children = iterator_to_array($node->childNodes);

    foreach ($children as $child) {
        if ($child->nodeType === XML_TEXT_NODE) continue;

        if ($child->nodeType !== XML_ELEMENT_NODE) {
            if ($child->parentNode) $child->parentNode->removeChild($child);
            continue;
        }

        /** @var DOMElement $child */
        $name = strtolower($child->nodeName);

        // Clean descendants before deciding what to do with this element.
        rocCmsCleanNode($doc, $child, $allowedTags, $allowedAttrs, $siteHost);

        if (!isset($allowedTags[$name])) {
            // Not allowed: lift the children into this element's place, drop the tag.
            $parent = $child->parentNode;
            while ($child->firstChild) {
                $parent->insertBefore($child->firstChild, $child);
            }
            $parent->removeChild($child);
            continue;
        }

        $target = $allowedTags[$name];

        // Strip every attribute that is not on the allowlist for the target tag.
        $permitted = $allowedAttrs[$target] ?? [];
        foreach (iterator_to_array($child->attributes ?? []) as $attr) {
            $attrName = strtolower($attr->nodeName);
            if (!in_array($attrName, $permitted, true)) {
                $child->removeAttribute($attr->nodeName);
                continue;
            }
            if ($attrName === 'href' || $attrName === 'src') {
                $safe = rocCmsSafeUrl($attr->nodeValue);
                if ($safe === null) {
                    $child->removeAttribute($attr->nodeName);
                } else {
                    $child->setAttribute($attrName, $safe);
                }
            }
            if (($attrName === 'width' || $attrName === 'height')
                && !ctype_digit((string)$attr->nodeValue)) {
                $child->removeAttribute($attr->nodeName);
            }
            if ($attrName === 'target' && $attr->nodeValue !== '_blank') {
                $child->removeAttribute($attr->nodeName);
            }
        }

        // Links: an anchor with no usable href becomes plain text.
        if ($target === 'a') {
            if (!$child->hasAttribute('href')) {
                $parent = $child->parentNode;
                while ($child->firstChild) $parent->insertBefore($child->firstChild, $child);
                $parent->removeChild($child);
                continue;
            }
            $href = $child->getAttribute('href');
            $isExternal = preg_match('#^https?://#i', $href)
                && !str_contains(strtolower(parse_url($href, PHP_URL_HOST) ?? ''), $siteHost);
            if ($isExternal) {
                $child->setAttribute('target', '_blank');
                $child->setAttribute('rel', 'noopener noreferrer');
            } else {
                $child->removeAttribute('target');
                $child->removeAttribute('rel');
            }
        }

        // Images without alt are still valid HTML, but give them an empty alt
        // so screen readers skip them instead of reading the file name.
        if ($target === 'img' && !$child->hasAttribute('alt')) {
            $child->setAttribute('alt', '');
        }
        if ($target === 'img') {
            $child->setAttribute('loading', 'lazy');
        }

        // Rename when the allowlist maps to a different tag (h1 -> h2, b -> strong).
        if ($target !== $name) {
            $replacement = $doc->createElement($target);
            foreach (iterator_to_array($child->attributes ?? []) as $attr) {
                $replacement->setAttribute($attr->nodeName, $attr->nodeValue);
            }
            while ($child->firstChild) {
                $replacement->appendChild($child->firstChild);
            }
            $child->parentNode->replaceChild($replacement, $child);
        }
    }
}

/** Plain text from HTML, for word counts and description fallbacks. */
function rocCmsTextFromHtml(string $html): string {
    $text = preg_replace('/<(br|\/p|\/h[1-6]|\/li|\/tr)>/i', ' ', $html);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $text));
}

/** Truncate on a word boundary, for generated meta descriptions. */
function rocCmsTruncate(string $text, int $limit): string {
    $text = trim($text);
    if (mb_strlen($text) <= $limit) return $text;
    $cut = mb_substr($text, 0, $limit);
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > $limit * 0.6) $cut = mb_substr($cut, 0, $space);
    return rtrim($cut, " \t\n\r\0\x0B.,;:") . '…';
}

/** Build the JSON-LD node for a blog row. A stored override always wins. */
function rocCmsBlogSchema(array $blog, string $baseUrl): ?array {
    if (!empty($blog['schema_json'])) {
        $decoded = json_decode($blog['schema_json'], true);
        if (is_array($decoded)) return $decoded;
    }
    $baseUrl = rtrim($baseUrl, '/');
    $url = $baseUrl . '/blogs/' . ($blog['slug'] ?? '') . '/';
    $image = $blog['og_image'] ?: ($blog['image'] ?? '');
    if ($image && str_starts_with($image, '/')) $image = $baseUrl . $image;

    $node = [
        '@context' => 'https://schema.org',
        '@type'    => $blog['schema_type'] ?: 'BlogPosting',
        'headline' => mb_substr($blog['meta_title'] ?: ($blog['title'] ?? ''), 0, 110),
        'url'      => $url,
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
    ];
    if (!empty($blog['meta_description'])) $node['description'] = $blog['meta_description'];
    if ($image) $node['image'] = [$image];
    if (!empty($blog['published_at']))        $node['datePublished'] = date(DATE_ATOM, strtotime($blog['published_at']));
    if (!empty($blog['content_modified_at'])) $node['dateModified']  = date(DATE_ATOM, strtotime($blog['content_modified_at']));
    elseif (!empty($blog['updated_at']))      $node['dateModified']  = date(DATE_ATOM, strtotime($blog['updated_at']));
    if (!empty($blog['author_name'])) {
        $node['author'] = ['@type' => 'Person', 'name' => $blog['author_name']];
    }
    $node['publisher'] = [
        '@type' => 'Organization',
        'name'  => 'Run On Console',
        'logo'  => ['@type' => 'ImageObject', 'url' => $baseUrl . '/social-logo.svg'],
    ];
    return $node;
}

}   // function_exists guard
