<?php

namespace Tests\Unit;

use App\Support\EmailContent;
use PHPUnit\Framework\TestCase;

class EmailContentTest extends TestCase
{
    public function test_sanitize_html_stores_literal_romanian_diacritics(): void
    {
        $html = "<p>Bun\u{0103} ziua, mul\u{021B}umim pentru \u{00EE}ncredere.</p>";

        $sanitized = EmailContent::sanitizeHtml($html);

        $this->assertSame($html, $sanitized);
    }

    public function test_sanitize_html_repairs_misencoded_romanian_diacritics(): void
    {
        $html = '<div>&Auml;&#131;&icirc;&Egrave;&#153;&Egrave;&#155;</div>';

        $sanitized = EmailContent::sanitizeHtml($html);

        $this->assertSame("<div>\u{0103}\u{00EE}\u{0219}\u{021B}</div>", $sanitized);
    }

    public function test_sanitize_html_keeps_reserved_entities_escaped_in_text(): void
    {
        $html = '<p>5 &lt; 7 &amp; 9 &gt; 3</p>';

        $sanitized = EmailContent::sanitizeHtml($html);

        $this->assertSame($html, $sanitized);
    }
}
