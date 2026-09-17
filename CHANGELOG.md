# Changelog

What changed in each release, and what you have to do about it.

Versions are `MAJOR.MINOR.PATCH`. Before 1.0 a breaking change raises the
minor. A change to what a number means, or to the JSON shape, is breaking.

## 0.1.1 - unreleased

Marketplace readiness. Nothing reported changes.

### Fixed

**A brace in content can no longer reach Vue.** The utility page is compiled
by Vue as a template, so an orphan key or field path holding `{{` would have
been read as an expression. Every content value the page prints now writes
braces as entities. No editor can create such a key through the control
panel; a file on disk can.

**A stray file is gone from the package.** An early test run wrote a JSON
report to a file named `1` in the repository root, and it was committed.

### Changed

**Requires Statamic 6.31 or newer**, was 6.27. Releases 6.27 to 6.30 carry a
security advisory that Composer refuses by default, and the suite was never
run on them. It is green on 6.31.0, 6.32.0, and 6.33.0.

**The Composer artifact is smaller.** The build documents, the test suite,
and the review skill are export-ignored.

### Added

`LICENSE.md`, a Support section in the README, and a `support` block in
`composer.json`.

## 0.1.0 - 2026-09-14

First release. Everything in the build spec, and nothing outside it.

### Added

**`php please fallow:audit`.** A table per blueprint (path, type, filled,
denominator, rate, status), the orphan keys under it, and a summary line.
`--collection` and `--site` repeat and default to everything; a handle that
does not exist exits 2. `--min-fill` sets the sparse threshold for one run.
`--json` puts the report on stdout with nothing else, `--json=path` writes it
to a file. `--fail-on=dead,sparse,orphans` exits 1 when any named condition is
found, for CI.

**A Fallow page under Tools.** The same numbers, read-only, with a filter per
collection. Gated by its own permission, **Access Fallow utility**. Above
`cp_entry_limit` entry localizations (5,000 by default) it points at the
command instead of analyzing.

**The analyzer.** Every entry localization in scope is read once, in chunks
of 100, against the blueprint it declares. Inheritance is resolved up the
origin chain by hand, to the nearest entry whose own data has the key, so an
empty override stays empty and a two-hop chain resolves. Grid and group
children are counted per row, set fields per enabled set instance, and a set
with `enabled: false` counts for nothing. A bard value is filled when its
text is more than whitespace or a set sits anywhere in it. Nothing is
augmented: every number comes from raw stored data.

**What is left out, on purpose.** Fields Statamic never stores in the entry's
data: `slug`, `date` in a dated collection, fields with `visibility: computed`,
and the `section`, `html`, and `revealer` fieldtypes. Fields an addon adds to
a blueprint at runtime are counted, because the editor sees them.

**Config**, `config/fallow.php`: `min_fill`, `ignored_keys` (merged with the
built-in list, never replacing it), `cp_entry_limit`.

### Known

A third-party fieldtype that draws a panel and stores nothing shows as dead.
There is no list of those yet.
