# Auto-link email addresses (filter_emailautolink)

A Moodle text filter that turns plain-text email addresses appearing in
course content (labels, pages, forum posts, and anywhere else `format_text()`
runs) into clickable `mailto:` links, so users don't have to copy/paste them.

## What it does

- Wraps every plain-text email address it finds with `<a href="mailto:...">...</a>`,
  keeping the visible text unchanged.
- Links an address only when it stands as its own word: preceded by a space,
  the start of the text or opening punctuation, and followed by a space, the
  end of the text or closing punctuation. So addresses inside web addresses
  (`https://user@example.com/x`, `?to=jane@example.com`), mailto parameters
  (`a?subject=Hi&cc=b@example.com`), file names (`logo@2x.png`), versions
  (`react@18.3.1`) and git remotes (`git@github.com:org/repo`) are left as
  plain text.
- Links valid accented addresses as a whole (`tést@example.com`,
  `jöe@exämple.de`). In the link, the local part is percent-encoded and an
  accented domain is converted to punycode, as RFC 6068 requires.
- Links every occurrence, including repeats of the same address.
- Leaves existing markup alone, using the same tag-protection mechanism as
  core's own `filter_urltolink` and `filter_phrases()` (see `lib/filterlib.php`)
  rather than a bespoke parser:
  - Text inside `<a>...</a>`, `<script>`, `<style>`, `<textarea>`,
    `<select>`, `<pre>`, `<code>` and `<head>` is never touched.
  - An author's own `<nolink>...</nolink>` or `<span class="nolink">...</span>`
    escape hatch is honoured, including when `nolink` is one of several
    classes.
  - Text inside HTML attribute values (e.g. `alt="..."`) is never touched.
- Only runs when called with a known `originalformat` (as `format_text()`
  does), and skips `FORMAT_PLAIN`, so it can't leak a stray `<a>` into a
  plain-string context such as an activity or course name rendered via
  `format_string()`. This mirrors `filter_urltolink`'s own guard.

## Settings

*Site administration ▸ Plugins ▸ Filters ▸ Auto-link email addresses*

- **Override existing email links** (off by default). When on, an existing
  `mailto:` link whose whole visible text is one valid address is changed to
  send to that address. Anything after the address in the link (subject, cc)
  is kept. Web links, and `mailto:` links with any other text, are not
  changed.

## Filter order

Put this filter **above Email protection** on *Manage filters*, so it runs
first. Email protection then encodes the link this filter made, so the
address stays protected and clickable. If Email protection runs first, it
encodes the address before this filter sees it, and nothing is linked.

Order against **Convert URLs into links and images** does not matter: an
address inside a web address is never linked by this filter, whichever runs
first.

## Known limits

- `<span class="nolink">` protection ends at the first `</span>`, so an
  address after a nested span inside it is linked. Core filters behave the
  same way.
- Single-quoted `class='nolink'` is only seen in content that skips HTML
  cleaning; cleaned content always uses double quotes.
- Addresses with quoted local parts or IP-literal domains are not linked.
- On pages with thousands of existing links and addresses the filter costs
  a few seconds, about the same as core's URL filter.

## What it deliberately does not do

- No flagging/badge/visual indicator for `mailto:` links whose href doesn't
  match their visible text (see the override setting instead).
- No Atto/TinyMCE editor integration or live detection while typing.
- No admin-configurable regex pattern or exclusion list.

## Installation

Copy (or clone) this repository into your Moodle installation at:

```
<moodledir>/filter/emailautolink/          (Moodle 4.5 to 5.0)
<moodledir>/public/filter/emailautolink/   (Moodle 5.1 and later)
```

Then visit *Site administration ▸ Notifications* to complete the install,
and enable the filter under *Site administration ▸ Plugins ▸ Filters ▸
Manage filters*.

## Requirements

Moodle 4.5 LTS to 5.3. PHP as required by your Moodle version.

## Running the tests

From your Moodle codebase root, with PHPUnit and Behat initialised:

```
vendor/bin/phpunit --testsuite filter_emailautolink_testsuite
php admin/tool/behat/cli/run.php --tags=@filter_emailautolink
```

On Moodle 5.1 and later the plugin lives under `public/filter/emailautolink/`.

## License

GPL v3 or later, http://www.gnu.org/copyleft/gpl.html
