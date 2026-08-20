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

/**
 * Converts plain-text email addresses into clickable mailto: links.
 *
 * Text is split into alternating HTML-tag / non-tag chunks so that the
 * email pattern is only ever applied to actual visible text: it never
 * touches attribute values, and it is suppressed entirely while inside
 * an existing <a>, <script> or <style> element so links, markup and
 * scripting are never rewritten or nested.
 *
 * @package    filter_emailautolink
 * @copyright  2026 Auto-link email addresses filter contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \core_filters\text_filter {

    /**
     * A sane, fixed pattern for a plain-text email address.
     *
     * Local part: the common set of unquoted RFC 5322 atext characters.
     * Domain: one or more dot-separated labels, each starting and ending
     * with an alphanumeric character, so trailing sentence punctuation
     * (e.g. "email me at jane@example.com.") is never swallowed.
     */
    private const EMAIL_PATTERN =
        '/\b[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?'
        . '(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+\b/';

    /** Tag names that suppress linking anywhere inside them. */
    private const SKIP_TAGS = 'a|script|style';

    /**
     * Filter the given HTML fragment, wrapping plain-text email addresses
     * in mailto: links.
     *
     * @param string $text HTML fragment to filter.
     * @param array $options filter options (unused by this filter).
     * @return string filtered text.
     */
    public function filter($text, array $options = []) {
        if (!is_string($text) || $text === '' || stripos($text, '@') === false) {
            return $text;
        }

        // Split into alternating [text, tag, text, tag, ...] chunks.
        $chunks = preg_split('/(<[^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($chunks === false) {
            return $text;
        }
        if (count($chunks) === 1) {
            // No markup at all: filter the whole string directly.
            return $this->link_emails($text);
        }

        $skipdepth = 0; // > 0 while inside a <a>, <script> or <style> element.
        $output = '';

        foreach ($chunks as $chunk) {
            if ($chunk === '') {
                continue;
            }

            if ($chunk[0] === '<') {
                if (preg_match('/^<\s*(' . self::SKIP_TAGS . ')(?:[\s>\/]|$)/i', $chunk)) {
                    $skipdepth++;
                } else if (preg_match('/^<\s*\/\s*(' . self::SKIP_TAGS . ')\s*>/i', $chunk)) {
                    $skipdepth = max(0, $skipdepth - 1);
                }
                $output .= $chunk;
                continue;
            }

            $output .= $skipdepth > 0 ? $chunk : $this->link_emails($chunk);
        }

        return $output;
    }

    /**
     * Wrap every plain-text email address found in a text-only chunk
     * (no markup, no attributes) with a mailto: link.
     *
     * @param string $text plain text chunk.
     * @return string the chunk with emails linked.
     */
    private function link_emails(string $text): string {
        if (stripos($text, '@') === false) {
            return $text;
        }

        return preg_replace_callback(self::EMAIL_PATTERN, function (array $match): string {
            return '<a href="mailto:' . $match[0] . '">' . $match[0] . '</a>';
        }, $text);
    }
}
