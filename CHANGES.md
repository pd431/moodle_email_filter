# Changes

## 0.3.1 (beta)

- Copyright set to Pierre Camaly de Brosses - University of Exeter.

## 0.3.0 (beta)

- Security: an address inside an HTML attribute containing `|` no longer gets a link
  injected into the attribute.
- Privacy: adds a null privacy provider (the plugin stores no personal data).
- Addresses are linked only when they stand as their own word, so URLs, mailto
  parameters, file names, versions and git remotes are no longer partly linked.
- Valid accented addresses are linked as a whole, with an RFC 6068 encoded href.
- Content in `<pre>` and `<code>` is not linked.
- `class="nolink"` is honoured among other classes.
- New setting: Override existing email links (off by default).
- The link is built with `html_writer::link()`, so its href and text are escaped.
- Supports Moodle 4.5 LTS to 5.3. CI on GitHub Actions; each release on `main` is tagged.

## 0.2.0 (beta)

- Matches core's ignore-tag architecture; targets Moodle 4.5.
