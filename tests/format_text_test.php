<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace filter_emailautolink;

use advanced_testcase;
use core\context\system;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(text_filter::class)]
/**
 * Tests for the filter running inside format_text(), after HTML cleaning and next to core filters.
 *
 * @package    filter_emailautolink
 * @category   test
 * @copyright  2026 Auto-link email addresses filter contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_emailautolink\text_filter
 */
final class format_text_test extends advanced_testcase {
    /**
     * Enable the filter on the whole site.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        filter_set_global_state('emailautolink', TEXTFILTER_ON);
    }

    /**
     * Render text the way course content is rendered.
     *
     * @param string $text
     * @return string
     */
    private function render(string $text): string {
        \filter_manager::reset_caches();
        return format_text($text, FORMAT_HTML, ['context' => system::instance()]);
    }

    public function test_address_in_content_is_linked(): void {
        $this->assertStringContainsString(
            '<a href="mailto:jane@example.com">jane@example.com</a>',
            $this->render('<p>Contact jane@example.com</p>')
        );
    }

    public function test_pipe_in_attribute_does_not_break_out_after_cleaning(): void {
        $out = $this->render('<p>See <img src="a.png" alt="a|jane@example.com"> and <span title="b|jane@example.com">x</span></p>');
        $this->assertStringNotContainsString('mailto:', $out);
        $this->assertStringContainsString('alt="a|jane@example.com"', $out);
        $this->assertStringContainsString('title="b|jane@example.com"', $out);
    }

    public function test_address_in_url_is_not_linked_with_url_filter_after(): void {
        filter_set_global_state('urltolink', TEXTFILTER_ON);
        $out = $this->render('<p>Go to https://example.com/?to=jane@example.com now</p>');
        $this->assertStringNotContainsString('mailto:', $out);
        $this->assertSame(1, substr_count($out, '<a '));
    }

    public function test_address_in_url_is_not_linked_with_url_filter_before(): void {
        filter_set_global_state('urltolink', TEXTFILTER_ON);
        filter_set_global_state('emailautolink', TEXTFILTER_ON, 1);
        $out = $this->render('<p>Go to https://example.com/?to=jane@example.com and mail bob@example.com</p>');
        $this->assertStringNotContainsString('mailto:jane', $out);
        $this->assertStringContainsString('<a href="mailto:bob@example.com">bob@example.com</a>', $out);
    }

    public function test_quoted_name_and_address_after_cleaning(): void {
        $out = $this->render('<p>Jane Doe &lt;jane@example.com&gt;, "bob@example.com"</p>');
        $this->assertStringContainsString('<a href="mailto:jane@example.com">jane@example.com</a>', $out);
        $this->assertStringContainsString('<a href="mailto:bob@example.com">bob@example.com</a>', $out);
    }

    public function test_apostrophe_address_after_cleaning(): void {
        $out = $this->render("<p>Write to o'brien@example.com.</p>");
        $this->assertStringContainsString('href="mailto:o&#039;brien@example.com"', $out);
    }

    public function test_nothing_is_linked_when_email_protection_runs_first(): void {
        filter_set_global_state('emailprotect', TEXTFILTER_ON);
        filter_set_global_state('emailprotect', TEXTFILTER_ON, -100);
        $out = $this->render('<p>Contact jane@example.com</p>');
        $this->assertStringNotContainsString('mailto:jane@example.com', $out);
    }

    public function test_link_is_protected_when_email_protection_runs_after(): void {
        filter_set_global_state('emailprotect', TEXTFILTER_ON);
        $out = $this->render('<p>Contact jane@example.com</p>');
        $this->assertSame(1, substr_count($out, '<a href="'));
        $this->assertStringNotContainsString('jane@example.com', $out);
    }

    public function test_titles_are_not_linked(): void {
        $out = format_string('Ask jane@example.com', true, ['context' => system::instance()]);
        $this->assertSame('Ask jane@example.com', $out);
    }
}
