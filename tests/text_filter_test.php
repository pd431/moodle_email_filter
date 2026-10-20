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
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(text_filter::class)]
/**
 * Unit tests for the Auto-link email addresses filter.
 *
 * @package    filter_emailautolink
 * @category   test
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_emailautolink\text_filter
 */
final class text_filter_test extends advanced_testcase {
    /**
     * Get a filter instance bound to the system context.
     *
     * @return text_filter
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
            '<a href="mailto:jane@example.com">jane@example.com</a>',
            $out
        );
        $this->assertStringContainsString(
            '<a href="mailto:john@example.org">john@example.org</a>',
            $out
        );
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

    /**
     * With overrideexisting on, only mailto: links that show one valid address are changed.
     *
     * @dataProvider override_existing_provider
     * @param string $in
     * @param string $expected
     */
    #[DataProvider('override_existing_provider')]
    public function test_override_existing_mailto_links(string $in, string $expected): void {
        $this->resetAfterTest();
        set_config('overrideexisting', 1, 'filter_emailautolink');
        $this->assertSame($expected, $this->filter_as_format($this->get_filter(), $in));
    }

    /**
     * Data provider.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function override_existing_provider(): array {
        return [
            'address shown' => [
                '<a href="mailto:old@example.com">jane@example.com</a>',
                '<a href="mailto:jane@example.com">jane@example.com</a>',
            ],
            'subject and cc kept' => [
                '<a href="mailto:old@example.com?subject=Hi&amp;cc=x@example.com">jane@example.com</a>',
                '<a href="mailto:jane@example.com?subject=Hi&amp;cc=x@example.com">jane@example.com</a>',
            ],
            'other attributes and inner markup kept' => [
                '<a class="btn" href="mailto:old@example.com" title="Mail"> <strong>tést@example.com</strong> </a>',
                '<a class="btn" href="mailto:t%C3%A9st@example.com" title="Mail"> <strong>tést@example.com</strong> </a>',
            ],
            'text is not an address' => [
                '<a href="mailto:old@example.com">Email the office</a>',
                '<a href="mailto:old@example.com">Email the office</a>',
            ],
            'text has more than an address' => [
                '<a href="mailto:old@example.com">Email jane@example.com</a>',
                '<a href="mailto:old@example.com">Email jane@example.com</a>',
            ],
            'web link showing an address' => [
                '<a href="https://example.com/">jane@example.com</a>',
                '<a href="https://example.com/">jane@example.com</a>',
            ],
        ];
    }

    public function test_override_existing_is_off_by_default(): void {
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

    /**
     * A '|' in an attribute must not make the tag look like text (regression for link injection).
     *
     * @dataProvider pipe_in_attribute_provider
     * @param string $text
     */
    #[DataProvider('pipe_in_attribute_provider')]
    public function test_email_in_attribute_containing_pipe_is_untouched(string $text): void {
        $this->resetAfterTest();
        $this->assertSame($text, $this->filter_as_format($this->get_filter(), $text));
    }

    /**
     * Data provider.
     *
     * @return array<string, array{0: string}>
     */
    public static function pipe_in_attribute_provider(): array {
        return [
            'void tag' => ['See <img src="a.png" alt="a|jane@example.com"> here'],
            'paired tag' => ['See <span title="a|jane@example.com">this</span> here'],
            'raw < inside attribute' => ['See <img alt="a<b jane@example.com"> here'],
        ];
    }

    public function test_link_is_html_escaped(): void {
        $this->resetAfterTest();
        $out = $this->filter_as_format($this->get_filter(), "Write to o'brien@example.com today");
        $this->assertSame(
            'Write to <a href="mailto:o&#039;brien@example.com">o&#039;brien@example.com</a> today',
            $out
        );
    }

    public function test_email_inside_nolink_span_is_untouched(): void {
        $this->resetAfterTest();
        $in = '<span class="nolink">Contact jane@example.com</span>';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    /**
     * A span with nolink among other classes protects its content.
     *
     * @dataProvider nolink_among_classes_provider
     * @param string $in
     */
    #[DataProvider('nolink_among_classes_provider')]
    public function test_email_inside_span_with_nolink_among_classes_is_untouched(string $in): void {
        $this->resetAfterTest();
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    /**
     * Data provider.
     *
     * @return array<string, array{0: string}>
     */
    public static function nolink_among_classes_provider(): array {
        return [
            'nolink first' => ['<span class="nolink small">Contact jane@example.com</span>'],
            'nolink last' => ['<span class="small nolink">Contact jane@example.com</span>'],
            'nolink in the middle' => ['<span lang="en" class="a nolink b">Contact jane@example.com</span>'],
        ];
    }

    public function test_class_that_only_contains_nolink_does_not_protect(): void {
        $this->resetAfterTest();
        $out = $this->filter_as_format($this->get_filter(), '<span class="nolinks">jane@example.com</span>');
        $this->assertStringContainsString('mailto:jane@example.com', $out);
    }

    public function test_email_inside_nolink_tag_is_untouched(): void {
        $this->resetAfterTest();
        $in = '<nolink>Contact jane@example.com</nolink>';
        $this->assertSame($in, $this->filter_as_format($this->get_filter(), $in));
    }

    public function test_email_inside_code_samples_is_untouched(): void {
        $this->resetAfterTest();
        $in = '<pre>ssh jane@example.com</pre><p>Run <code>git clone bob@example.com</code></p>';
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
     * Malformed or partial addresses are not linked.
     *
     * @dataProvider malformed_email_provider
     */
    #[DataProvider('malformed_email_provider')]
    public function test_malformed_email_like_text_is_not_linked(string $text): void {
        $this->resetAfterTest();
        $this->assertSame($text, $this->filter_as_format($this->get_filter(), $text));
    }

    /**
     * Data provider.
     *
     * @return array<string, array{0: string}>
     */
    public static function malformed_email_provider(): array {
        return [
            'trailing @ with no domain' => ['not.an.email@'],
            'leading @ with no local part' => ['@nodomain'],
            'no @ at all' => ['plainaddress'],
        ];
    }

    /**
     * Text that only contains something address-like, glued to other characters, is not linked.
     *
     * @dataProvider not_an_address_provider
     * @param string $text
     */
    #[DataProvider('not_an_address_provider')]
    public function test_text_that_only_looks_like_an_address_is_not_linked(string $text): void {
        $this->resetAfterTest();
        $this->assertSame($text, $this->filter_as_format($this->get_filter(), $text));
    }

    /**
     * Data provider.
     *
     * @return array<string, array{0: string}>
     */
    public static function not_an_address_provider(): array {
        return [
            'query string in a URL' => ['Go to https://example.com/?to=jane@example.com'],
            'user in a URL' => ['Go to https://user@example.com/x'],
            'mailto parameters' => ['Write to a?subject=Hi&amp;cc=b@example.com'],
            'image file name' => ['Upload logo@2x.png'],
            'package version' => ['Install react@18.3.1'],
            'git remote' => ['Clone git@github.com:org/repo'],
            'domain without a dot' => ['Write to tést@exämple'],
            'domain label starting with a hyphen' => ['Write to tést@-example.com'],
            'two dots in the local part' => ['Write to tést..x@example.com'],
            'local part starting with a dot' => ['Write to .tést@example.com'],
            'glued to a preceding letter' => ['Write to a–jane@example.com'],
            'not valid UTF-8' => ["Write to j\xC3ane@example.com"],
        ];
    }

    /**
     * A valid accented address is linked as a whole, encoded in the href as RFC 6068 requires.
     *
     * @dataProvider accented_address_provider
     * @param string $address
     * @param string $href
     */
    #[DataProvider('accented_address_provider')]
    public function test_accented_address_is_linked_whole(string $address, string $href): void {
        $this->resetAfterTest();
        $out = $this->filter_as_format($this->get_filter(), "Write to {$address} today");
        $this->assertSame("Write to <a href=\"mailto:{$href}\">{$address}</a> today", $out);
    }

    /**
     * Data provider.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function accented_address_provider(): array {
        return [
            'accent inside the local part' => ['tést@example.com', 't%C3%A9st@example.com'],
            'accent at the start of the local part' => ['éva@example.com', '%C3%A9va@example.com'],
            'accented domain' => ['jöe@exämple.de', 'j%C3%B6e@xn--exmple-cua.de'],
        ];
    }

    /**
     * Punctuation and the entities HTML cleaning produces around an address are not linked.
     *
     * @dataProvider surrounding_punctuation_provider
     * @param string $in
     * @param string $expected
     */
    #[DataProvider('surrounding_punctuation_provider')]
    public function test_surrounding_punctuation_is_not_linked(string $in, string $expected): void {
        $this->resetAfterTest();
        $this->assertSame($expected, $this->filter_as_format($this->get_filter(), $in));
    }

    /**
     * Data provider.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function surrounding_punctuation_provider(): array {
        $link = '<a href="mailto:jane@example.com">jane@example.com</a>';
        return [
            'brackets and comma' => ['Email (jane@example.com), please', "Email ({$link}), please"],
            'angle brackets' => ['Jane &lt;jane@example.com&gt;', "Jane &lt;{$link}&gt;"],
            'non-breaking spaces' => ['Email&nbsp;jane@example.com&nbsp;now', "Email&nbsp;{$link}&nbsp;now"],
            'quotes' => ['"jane@example.com"', "\"{$link}\""],
        ];
    }

    public function test_trailing_sentence_punctuation_is_not_swallowed(): void {
        $this->resetAfterTest();
        $out = $this->filter_as_format($this->get_filter(), 'Email jane@example.com.');
        $this->assertSame(
            'Email <a href="mailto:jane@example.com">jane@example.com</a>.',
            $out
        );
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
