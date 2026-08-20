# Auto-link email addresses (filter_emailautolink)

A Moodle text filter that turns plain-text email addresses appearing in
course content (labels, pages, forum posts, and anywhere else `format_text()`
runs) into clickable `mailto:` links, so users don't have to copy/paste them.

## What it does

- Wraps every plain-text email address it finds with `<a href="mailto:...">...</a>`,
  keeping the visible text unchanged.
- Links every occurrence, including repeats of the same address.
- Leaves existing markup alone, using the same tag-protection mechanism as
  core's own `filter_urltolink` and `filter_phrases()` (see `lib/filterlib.php`)
  rather than a bespoke parser:
  - Text inside `<a>...</a>` (any `href`, including an existing `mailto:`
    link, matching or not), `<script>`, `<style>`, `<textarea>`, `<select>`,
    and `<head>` is never touched.
  - An author's own `<nolink>...</nolink>` or `<span class="nolink">...</span>`
    escape hatch — the same one every other Moodle text filter respects — is
    honoured.
  - Text inside HTML attribute values (e.g. `alt="..."`), including on
    standalone tags with no closing pair such as `<img>`, is never touched,
    since it's never treated as visible text.
- Only runs when called with a known `originalformat` (as `format_text()`
  does), and skips `FORMAT_PLAIN`, so it can't leak a stray `<a>` into a
  plain-string context such as an activity or course name rendered via
  `format_string()`. This mirrors `filter_urltolink`'s own guard.
- Ignores malformed/partial matches such as `not.an.email@`, `@nodomain`, or
  `plainaddress`.
- Has no dependency on, or interaction with, the core Email Protection
  filter — it works whether that filter is enabled, disabled, or absent.

## What it deliberately does not do (out of scope for v1)

- No flagging/badge/visual indicator for `mailto:` links whose href doesn't
  match their visible text.
- No Atto/TinyMCE editor integration or live detection while typing.
- No admin-configurable regex pattern or exclusion list — the email pattern
  is fixed and sane by design.

## Installation

Copy (or clone) this repository into your Moodle installation at:

```
<moodledir>/filter/emailautolink/
```

Then visit *Site administration ▸ Notifications* to complete the install,
and enable the filter under *Site administration ▸ Plugins ▸ Filters ▸
Manage filters*.

## Requirements

Moodle 4.5 or later, PHP 8.1 or later. (Moodle 4.1 LTS reached end of life on
2025-11-11; this plugin now targets 4.5, the current LTS at time of writing.)

## Running the tests

From your Moodle codebase root, with PHPUnit already initialised
(`php admin/tool/phpunit/cli/init.php`):

```
vendor/bin/phpunit --filter filter_emailautolink filter/emailautolink/tests/text_filter_test.php
```

## License

GPL v3 or later, http://www.gnu.org/copyleft/gpl.html
