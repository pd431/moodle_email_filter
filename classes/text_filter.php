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
 * Architecture mirrors filter_urltolink, core's closest filter in kind, and reuses the same
 * two core mechanisms rather than a bespoke parser:
 *  - filter_save_ignore_tags() extracts whole ignore-regions (the same tag list
 *    filter_phrases() uses by default, see lib/filterlib.php, plus <style>, which that list
 *    omits) so an existing <a> (mailto or otherwise), <script>, <style>, form control, or an
 *    author's own <nolink>/class="nolink" escape hatch is never touched.
 *  - What is left is tokenized into tag/non-tag chunks and every tag chunk is skipped outright,
 *    so a standalone tag with no closing pair (e.g. <img alt="user@example.com">) never leaks
 *    its attributes to the email pattern either.
 * The email pattern only ever runs against genuine text nodes.
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

    /**
     * Maximum length of a single space-delimited "word" that will be run
     * through the email pattern. ReDoS/pathological-input safety net,
     * mirrors filter_urltolink's identical per-word length check.
     */
    private const MAX_WORD_LENGTH = 4096;

    #[\Override]
    public function filter($text, array $options = []) {
        if (!isset($options['originalformat'])) {
            // No originalformat means we were most likely called from format_string()
            // (e.g. for a title), where tags are usually stripped afterwards. Linking here
            // would be wasted work and risks leaking a stray <a> into a plain-string context.
            // Mirrors the same guard in filter_urltolink.
            return $text;
        }
        if ($options['originalformat'] == FORMAT_PLAIN) {
            // Plain text is never rendered as HTML, so injecting a link would just leak markup
            // into the visible output.
            return $text;
        }
        if (stripos($text, '@') === false) {
            return $text;
        }

        // Protect existing markup using the same mechanism, and largely the same tag list,
        // that core's filter_phrases() uses by default (see lib/filterlib.php): <a> so existing
        // links (mailto or otherwise) are never touched or nested, <script>/<style> so code and
        // CSS are never rewritten, <textarea>/<select> so form content is left alone, and
        // <nolink>/<span class="nolink"> so authors keep their usual escape hatch from filters.
        $filterignoretagsopen = [
            '<head>', '<nolink>', '<span(\s[^>]*?)?class="nolink"(\s[^>]*?)?>',
            '<script(\s[^>]*?)?>', '<style(\s[^>]*?)?>', '<textarea(\s[^>]*?)?>',
            '<select(\s[^>]*?)?>', '<a(\s[^>]*?)?>',
        ];
        $filterignoretagsclose = [
            '</head>', '</nolink>', '</span>',
            '</script>', '</style>', '</textarea>',
            '</select>', '</a>',
        ];
        $ignoretags = [];
        filter_save_ignore_tags($text, $filterignoretagsopen, $filterignoretagsclose, $ignoretags);

        // filter_save_ignore_tags() only protects paired regions (open tag ... close tag). A
        // standalone tag with no closing pair, e.g. <img alt="user@example.com">, would still
        // have its attributes exposed to the email pattern below. So, exactly like
        // filter_urltolink, additionally tokenize what is left into tag/non-tag chunks and skip
        // every tag chunk outright: only genuine text nodes are ever passed to link_emails().
        $chunks = preg_split('/(<[^<|>]*>)/i', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        foreach ($chunks as $index => $chunk) {
            if (strpos(trim($chunk), '<') === 0) {
                continue;
            }
            $chunks[$index] = $this->link_emails($chunk);
        }
        $text = implode('', $chunks);

        if (!empty($ignoretags)) {
            // Reversed so "progressive" str_replace() will solve some nesting problems, same
            // idiom filter_phrases() and filter_urltolink use to restore their ignored tags.
            $ignoretags = array_reverse($ignoretags);
            $text = str_replace(array_keys($ignoretags), $ignoretags, $text);
        }

        return $text;
    }

    /**
     * Wrap every plain-text email address with a mailto: link.
     *
     * Called only with a single tag-free chunk of text, so every match here is genuine
     * visible text - never markup or an attribute value.
     *
     * @param string $text text to link.
     * @return string the text with emails linked.
     */
    private function link_emails(string $text): string {
        $words = explode(' ', $text);
        foreach ($words as $index => $word) {
            if (strlen($word) < self::MAX_WORD_LENGTH) {
                $words[$index] = preg_replace_callback(self::EMAIL_PATTERN, function (array $match): string {
                    return '<a href="mailto:' . $match[0] . '">' . $match[0] . '</a>';
                }, $word);
            }
        }
        return implode(' ', $words);
    }
}
