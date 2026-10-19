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
 *    so a standalone tag with no closing pair (e.g. an <img> with an address in its alt) never leaks
 *    its attributes to the email pattern either.
 * The email pattern only ever runs against genuine text nodes.
 *
 * @package    filter_emailautolink
 * @copyright  2026 Auto-link email addresses filter contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \core_filters\text_filter {
    /**
     * Local part of an address: letters (any script) and digits, with _ + - and single . or '
     * between them. Characters such as / ? = & % that RFC 5322 allows but real addresses do not
     * use are left out, so query strings and URLs never become part of a match.
     */
    private const LOCAL_PART = '[\p{L}\p{M}\p{N}_+-]+(?:[.\'][\p{L}\p{M}\p{N}_+-]+)*';

    /**
     * Domain of an address: dot-separated labels of letters (any script), digits and inner
     * hyphens, ending in a label of at least two letters (so react@18.3.1 is not an address).
     */
    private const DOMAIN = '(?:[\p{L}\p{N}](?:[\p{L}\p{M}\p{N}-]{0,61}[\p{L}\p{M}\p{N}])?\.)+'
        . '\p{L}[\p{L}\p{M}]{1,62}';

    /**
     * A plain-text email address standing as its own word.
     *
     * It must be preceded by the start of the text, a space or opening punctuation, and followed
     * by the end of the text or a space, optionally after closing punctuation. So an address glued
     * to other characters, such as inside a URL (as its user or in its query string) or followed
     * by more text (a git remote), is not linked at all rather than linked in part. The entities
     * HTML cleaning produces for spaces, quotes and angle brackets count as the characters they
     * stand for.
     */
    private const EMAIL_PATTERN = '/(?:(?<![^\s\x{00A0}(\[{"\'\x{201C}\x{2018}\x{00AB}:,])|(?<=&nbsp;|&quot;|&lt;))'
        . '(' . self::LOCAL_PART . ')@(' . self::DOMAIN . ')'
        . '(?=[.,;:!?)\]}"\'\x{201D}\x{2019}\x{00BB}]*(?:[\s\x{00A0}]|$|&nbsp;|&quot;|&gt;))/u';

    /**
     * Final domain labels that are image file extensions rather than top-level domains, so a file
     * name such as logo@2x.png is not linked. None of these is a real top-level domain.
     */
    private const NOT_TLDS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'bmp', 'ico'];

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

        if (get_config('filter_emailautolink', 'overrideexisting') && stripos($text, 'mailto:') !== false) {
            $text = $this->override_mailto_links($text);
        }

        // Protect existing markup using the same mechanism, and largely the same tag list,
        // that core's filter_phrases() uses by default (see lib/filterlib.php): <a> so existing
        // links (mailto or otherwise) are never touched or nested, <script>/<style> so code and
        // CSS are never rewritten, <textarea>/<select> so form content is left alone, and
        // <nolink>/<span class="nolink"> (also among other classes) so authors keep their usual
        // escape hatch from filters,
        // and <pre>/<code> so addresses in code samples (ssh jane@host, git remotes) stay as typed.
        $filterignoretagsopen = [
            '<head>', '<nolink>', '<span(\s[^>]*?)?class="(?:[^"]*\s)?nolink(?:\s[^"]*)?"(\s[^>]*?)?>',
            '<script(\s[^>]*?)?>', '<style(\s[^>]*?)?>', '<textarea(\s[^>]*?)?>',
            '<select(\s[^>]*?)?>', '<a(\s[^>]*?)?>', '<pre(\s[^>]*?)?>', '<code(\s[^>]*?)?>',
        ];
        $filterignoretagsclose = [
            '</head>', '</nolink>', '</span>',
            '</script>', '</style>', '</textarea>',
            '</select>', '</a>', '</pre>', '</code>',
        ];
        $ignoretags = [];
        filter_save_ignore_tags($text, $filterignoretagsopen, $filterignoretagsclose, $ignoretags);

        // The filter_save_ignore_tags() call only protects paired regions (open tag ... close tag). A
        // standalone tag with no closing pair, e.g. <img alt="user@example.com">, would still
        // have its attributes exposed to the email pattern below. So, exactly like
        // filter_urltolink, additionally tokenize what is left into tag/non-tag chunks and skip
        // every tag chunk outright: only genuine text nodes are ever passed to link_emails().
        // Unlike filter_urltolink, '|' is not excluded from the tag pattern: excluding it would make
        // a tag whose attribute contains '|' (e.g. <img alt="a|jane@example.com">) look like text,
        // and a link would be injected into the attribute. Filters run after HTML cleaning, so
        // nothing would repair that.
        $chunks = preg_split('/(<[^<>]*>)/', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        foreach ($chunks as $index => $chunk) {
            if (strpos(trim($chunk), '<') === 0) {
                continue;
            }
            if (strpbrk($chunk, '<>') !== false) {
                // Cleaned text nodes never contain a raw < or >, so a chunk that still does is
                // markup the tokenizer could not split (e.g. a < inside an attribute value). It
                // may be inside a tag, so leave it alone rather than risk linking into it.
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
                // Null means the word is not valid UTF-8; leave it as it was.
                $words[$index] = preg_replace_callback(self::EMAIL_PATTERN, [self::class, 'link_email'], $word) ?? $word;
            }
        }
        return implode(' ', $words);
    }

    /**
     * Make each mailto: link whose whole visible text is one valid address send to that address.
     *
     * Only the address in the href changes: the link's other attributes, its text and anything
     * after the address (?subject=, cc) are kept. Links with any other text are left alone.
     *
     * @param string $text text to process.
     * @return string the text with existing mailto: links pointing at the address they show.
     */
    private function override_mailto_links(string $text): string {
        $pattern = '/(<a\s[^>]*?\bhref=")mailto:[^"?]*((?:\?[^"]*)?"[^>]*>)(.*?)(<\/a>)/is';
        return preg_replace_callback($pattern, function (array $m): string {
            $visible = html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $visible = preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $visible) ?? '';
            $address = '/^(' . self::LOCAL_PART . ')@(' . self::DOMAIN . ')$/u';
            if (!preg_match($address, $visible, $parts)) {
                return $m[0];
            }
            $href = self::mailto_address($parts[1], $parts[2]);
            if ($href === null) {
                return $m[0];
            }
            return $m[1] . 'mailto:' . s($href) . $m[2] . $m[3] . $m[4];
        }, $text) ?? $text;
    }

    /**
     * Wrap a single matched email address in a mailto: link.
     *
     * One match at a time, same as filter_emailprotect's alter_email()/alter_mailto(): this is
     * the preg_replace_callback callback for a single EMAIL_PATTERN match, not a loop over all
     * of them.
     *
     * @param array $matches regex match: [0] the whole address, [1] its local part, [2] its domain.
     * @return string the address wrapped in a mailto: link, or unchanged if it is not linkable.
     */
    private function link_email($matches) {
        $href = self::mailto_address($matches[1], $matches[2]);
        if ($href === null) {
            return $matches[0];
        }
        return \html_writer::link('mailto:' . $href, s($matches[0]));
    }

    /**
     * Build the address part of a mailto: URI, as RFC 6068 requires.
     *
     * Non-ASCII characters in the local part are percent-encoded as UTF-8, and an accented
     * domain is converted to its ASCII (punycode) form.
     *
     * @param string $local local part of the address.
     * @param string $domain domain of the address.
     * @return string|null the encoded address, or null if it should not be linked.
     */
    private static function mailto_address(string $local, string $domain): ?string {
        $labels = explode('.', $domain);
        if (in_array(\core_text::strtolower(end($labels)), self::NOT_TLDS, true)) {
            return null;
        }
        if (preg_match('/[^\x00-\x7F]/', $domain)) {
            $domain = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            if ($domain === false) {
                return null;
            }
        }
        $local = preg_replace_callback('/[^\x00-\x7F]+/', fn($m) => rawurlencode($m[0]), $local);
        return $local . '@' . $domain;
    }
}
