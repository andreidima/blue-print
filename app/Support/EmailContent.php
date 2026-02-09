<?php

namespace App\Support;

use DOMDocument;
use DOMElement;

class EmailContent
{
    public static function replacePlaceholders(string $text, array $placeholders): string
    {
        return strtr($text, $placeholders);
    }

    public static function formatBody(string $body, array $placeholders): string
    {
        $body = self::replacePlaceholders($body, $placeholders);
        $body = trim($body);

        if ($body === '') {
            return '';
        }

        if (self::looksLikeHtml($body)) {
            return self::sanitizeHtml($body);
        }

        $escaped = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return nl2br($escaped, false);
    }

    public static function looksLikeHtml(string $value): bool
    {
        return $value !== strip_tags($value);
    }

    public static function sanitizeHtml(string $html): string
    {
        $allowedTags = [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u',
            'ul', 'ol', 'li', 'a', 'span', 'div',
            'table', 'thead', 'tbody', 'tr', 'td', 'th',
            'hr', 'h1', 'h2', 'h3', 'h4', 'blockquote',
        ];

        $allowedAttrs = [
            'a' => ['href', 'target', 'rel'],
            'td' => ['colspan', 'rowspan', 'align'],
            'th' => ['colspan', 'rowspan', 'align'],
            'table' => ['border', 'cellpadding', 'cellspacing'],
            'p' => ['style', 'align'],
            'span' => ['style'],
            'div' => ['style'],
        ];

        $allowedStyles = [
            'color',
            'background-color',
            'text-align',
            'font-weight',
            'font-style',
            'text-decoration',
        ];

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $thisNode = $document->documentElement;
        if ($thisNode instanceof DOMElement) {
            self::sanitizeNode($thisNode, $allowedTags, $allowedAttrs, $allowedStyles);
        }

        $output = $document->saveHTML() ?? '';
        $output = preg_replace('/^<div>|<\/div>$/', '', $output);

        return trim((string) $output);
    }

    private static function sanitizeNode(DOMElement $node, array $allowedTags, array $allowedAttrs, array $allowedStyles): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (!in_array($tag, $allowedTags, true)) {
                    $text = $node->ownerDocument->createTextNode($child->textContent ?? '');
                    $node->replaceChild($text, $child);
                    continue;
                }

                $allowed = $allowedAttrs[$tag] ?? [];
                $remove = [];
                foreach ($child->attributes as $attr) {
                    $attrName = strtolower($attr->name);
                    if (!in_array($attrName, $allowed, true)) {
                        $remove[] = $attrName;
                        continue;
                    }

                    if ($tag === 'a' && $attrName === 'href') {
                        $href = trim($attr->value);
                        if ($href === '' || stripos($href, 'javascript:') === 0) {
                            $remove[] = $attrName;
                        }
                    }

                    if ($attrName === 'style') {
                        $clean = self::sanitizeStyle($attr->value, $allowedStyles);
                        if ($clean === '') {
                            $remove[] = $attrName;
                        } else {
                            $child->setAttribute('style', $clean);
                        }
                    }
                }

                foreach ($remove as $attrName) {
                    $child->removeAttribute($attrName);
                }

                if ($tag === 'a') {
                    $target = $child->getAttribute('target');
                    if ($target === '_blank') {
                        $rel = $child->getAttribute('rel');
                        $relParts = array_filter(preg_split('/\s+/', $rel));
                        foreach (['noopener', 'noreferrer'] as $relValue) {
                            if (!in_array($relValue, $relParts, true)) {
                                $relParts[] = $relValue;
                            }
                        }
                        $child->setAttribute('rel', implode(' ', $relParts));
                    }
                }

                self::sanitizeNode($child, $allowedTags, $allowedAttrs, $allowedStyles);
            }
        }
    }

    private static function sanitizeStyle(string $style, array $allowedStyles): string
    {
        $clean = [];
        $parts = preg_split('/;\s*/', $style) ?: [];

        foreach ($parts as $part) {
            if ($part === '' || !str_contains($part, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $part, 2));
            $property = strtolower($property);

            if (!in_array($property, $allowedStyles, true)) {
                continue;
            }

            if ($value === '' || stripos($value, 'expression') !== false || stripos($value, 'url(') !== false) {
                continue;
            }

            $clean[] = $property . ': ' . $value;
        }

        return implode('; ', $clean);
    }
}
