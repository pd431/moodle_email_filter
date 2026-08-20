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

/**
 * Unit tests for the Auto-link email addresses filter.
 *
 * @package    filter_emailautolink
 * @category   test
 * @copyright  2026 Auto-link email addresses filter contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_emailautolink\text_filter
 */
final class text_filter_test extends advanced_testcase {

    /**
     * @return text_filter a filter instance bound to the system context.
     */
    private function get_filter(): text_filter {
        return new text_filter(system::instance(), []);
    }

    /**
     * Filter as format_text() would call it: with an originalformat present.
     *
     * @param text_filter $filter
     * @param string $text
     * @param int $format
     * @return string
     */
    private function filter_as_format(text_filter $filter, string $text, int $format = FORMAT_HTML): string {
        return $filter->filter($text, ['originalformat' => $format]);
    }

    public function test_plain_email_in_body_text_is_linked(): void {
        $this->resetAfterTest();
        $out = $this->filter_as_format($this->get_filter(), 'Contact me at jane.doe@example.com for details');
        $this->assertSame(
            'Contact me at <a href="mailto:jane.doe@example.com">jane.doe@example.com</a> for details',
            $out
        );
    }

    public function test_multiple_distinct_addresses_are_all_linked(): void {
        $this->resetAfterTest();
        $out = $this->filter_as_format($this->get_filter(), '<p>jane@example.com</p><p>john@example.org</p>');
        $this->assertStringContainsString(
            '<a href="mailto:jane@example.com">jane@example.com</a>', $out);
        $this->assertStringContainsString(
            '<a href="mailto:john@example.org">john@example.org</a>', $out);
    }

    public function test_repeated_address_is_linked_every_time(): void {
        $this->resetAfterTest();
        $in = 'jane@example.com jane@example.com jane@example.com';
        $out = $this->filter_as_format($this->get_filter(), $in);
        $this->assertSame(3, substr_count($out, '<a href="mailto:jane@example.com">jane@example.com</a>'));
    }

    public function test_email_inside_existing_anchor_is_left_alone(): void {
        $this->resetAfterTest();
        $in = '<a href="https://example.com/contact">jane@example.com</a>';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    public function test_matching_mailto_link_is_left_alone(): void {
        $this->resetAfterTest();
        $in = '<a href="mailto:jane@example.com">jane@example.com</a>';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    public function test_mismatched_mailto_link_is_left_alone(): void {
        $this->resetAfterTest();
        $in = '<a href="mailto:old@example.com">jane@example.com</a>';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    public function test_email_inside_script_block_is_untouched(): void {
        $this->resetAfterTest();
        $in = '<script>var x = "user@example.com";</script>';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    public function test_email_inside_style_block_is_untouched(): void {
        $this->resetAfterTest();
        $in = '<style>/* contact: user@example.com */</style>';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    public function test_email_like_text_inside_attribute_is_untouched(): void {
        $this->resetAfterTest();
        $in = '<img alt="contact user@example.com">';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    public function test_email_inside_nolink_span_is_untouched(): void {
        $this->resetAfterTest();
        $in = '<span class="nolink">Contact jane@example.com</span>';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    public function test_email_inside_nolink_tag_is_untouched(): void {
        $this->resetAfterTest();
        $in = '<nolink>Contact jane@example.com</nolink>';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    public function test_email_inside_textarea_is_untouched(): void {
        $this->resetAfterTest();
        $in = '<textarea>jane@example.com</textarea>';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    public function test_email_inside_select_option_is_untouched(): void {
        $this->resetAfterTest();
        $in = '<select><option>jane@example.com</option></select>';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    /**
     * @dataProvider malformed_email_provider
     */
    public function test_malformed_email_like_text_is_not_linked(string $text): void {
        $this->resetAfterTest();
        $this->assertSame($text, $this->filter_as_format($this->get_filter(), $text));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function malformed_email_provider(): array {
        return [
            'trailing @ with no domain' => ['not.an.email@'],
            'leading @ with no local part' => ['@nodomain'],
            'no @ at all' => ['plainaddress'],
        ];
    }

    public function test_trailing_sentence_punctuation_is_not_swallowed(): void {
        $this->resetAfterTest();
        $out = $this->filter_as_format($this->get_filter(), 'Email jane@example.com.');
        $this->assertSame(
            'Email <a href="mailto:jane@example.com">jane@example.com</a>.', $out);
    }

    public function test_large_page_links_all_addresses(): void {
        $this->resetAfterTest();
        $body = '';
        for ($i = 0; $i < 200; $i++) {
            $body .= "<p>Contact person{$i}@example.com about item {$i}.</p>\n";
        }
        $out = $this->filter_as_format($this->get_filter(), $body);
        $this->assertSame(200, substr_count($out, 'mailto:'));
    }

    /**
     * When called without 'originalformat' (as happens from format_string()), the filter must
     * do nothing, since the caller is not expecting HTML back and tags are usually stripped.
     */
    public function test_no_originalformat_option_is_left_untouched(): void {
        $this->resetAfterTest();
        $in = 'Contact jane@example.com';
        $this->assertSame($in, $this->get_filter()->filter($in));
        $this->assertSame($in, $this->get_filter()->filter($in, []));
    }

    /**
     * FORMAT_PLAIN output is never rendered as HTML, so no link should be injected.
     */
    public function test_format_plain_is_left_untouched(): void {
        $this->resetAfterTest();
        $in = 'Contact jane@example.com';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in, FORMAT_PLAIN));
    }
}
