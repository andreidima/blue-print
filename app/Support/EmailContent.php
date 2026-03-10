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
        $html = self::repairMisencodedUtf8($html);

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
        $document->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        self::removeProcessingInstructions($document);
        libxml_clear_errors();

        $thisNode = $document->documentElement;
        if ($thisNode instanceof DOMElement) {
            self::sanitizeNode($thisNode, $allowedTags, $allowedAttrs, $allowedStyles);
        }

        $output = $document->saveHTML() ?? '';
        $output = preg_replace('/^<div>|<\/div>$/', '', $output);

        return trim(self::decodeNonStructuralEntities((string) $output));
    }

    public static function repairMisencodedUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $candidate = strtr($value, self::misencodedHtmlEntityMap());
        $candidate = preg_match('/&(?:[A-Za-z]+|#\d+);/', $candidate)
            ? html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : $candidate;

        $repaired = strtr($candidate, self::misencodedUtf8Map());

        if ($repaired === $candidate) {
            return $candidate === $value ? $value : $candidate;
        }

        return $repaired;
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

    private static function removeProcessingInstructions(DOMDocument $document): void
    {
        $nodesToRemove = [];
        foreach ($document->childNodes as $child) {
            if ($child->nodeType === XML_PI_NODE) {
                $nodesToRemove[] = $child;
            }
        }

        foreach ($nodesToRemove as $child) {
            $document->removeChild($child);
        }
    }

    private static function decodeNonStructuralEntities(string $html): string
    {
        return preg_replace_callback(
            '/&(?:#x[0-9A-Fa-f]+|#\d+|[A-Za-z][A-Za-z0-9]+);/',
            static function (array $matches): string {
                $entity = $matches[0];
                $decoded = html_entity_decode($entity, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return match ($decoded) {
                    '&', '<', '>', '"', "'" => $entity,
                    default => $decoded,
                };
            },
            $html
        ) ?? $html;
    }

    private static function misencodedUtf8Map(): array
    {
        return [
            "\u{00C4}\u{0192}" => "\u{0103}",
            "\u{00C4}\u{201A}" => "\u{0102}",
            "\u{00C3}\u{00A2}" => "\u{00E2}",
            "\u{00C3}\u{201A}" => "\u{00C2}",
            "\u{00C3}\u{00AE}" => "\u{00EE}",
            "\u{00C3}\u{017D}" => "\u{00CE}",
            "\u{00C8}\u{2122}" => "\u{0219}",
            "\u{00C8}\u{02DC}" => "\u{0218}",
            "\u{00C8}\u{203A}" => "\u{021B}",
            "\u{00C8}\u{0160}" => "\u{021A}",
            "\u{00C5}\u{0178}" => "\u{0219}",
            "\u{00C5}\u{017D}" => "\u{0218}",
            "\u{00C5}\u{00A3}" => "\u{021B}",
            "\u{00C5}\u{00A2}" => "\u{021A}",
        ];
    }

    private static function misencodedHtmlEntityMap(): array
    {
        return [
            '&Auml;&#131;' => "\u{0103}",
            '&Auml;&#8218;' => "\u{0102}",
            '&Atilde;&cent;' => "\u{00E2}",
            '&Atilde;&#8218;' => "\u{00C2}",
            '&Atilde;&reg;' => "\u{00EE}",
            '&Atilde;&#381;' => "\u{00CE}",
            '&Egrave;&#153;' => "\u{0219}",
            '&Egrave;&#152;' => "\u{0218}",
            '&Egrave;&#155;' => "\u{021B}",
            '&Egrave;&#154;' => "\u{021A}",
        ];
    }
}
