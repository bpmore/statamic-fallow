# Fallow for Statamic

Fallow reports content model drift: which blueprint fields your content
actually fills, which are dead weight, and which keys sit in entries with no
blueprint field to claim them.

It reads. It never writes. No content, blueprint, or config is changed by
anything in this package, and nothing leaves the machine.

Statamic 6.31 or newer and PHP 8.2 or newer. The suite runs green on
Statamic 6.31.0, 6.32.0, and 6.33.0 under PHP 8.4. Earlier 6.x releases carry
a security advisory that Composer refuses by default, so they are not
supported.

## Install

```
composer require bpmore/statamic-fallow
```

Optionally publish the config file:

```
php artisan vendor:publish --tag=fallow-config
```

## The command

```
php please fallow:audit
```

Prints a table per blueprint (path, type, filled, denominator, rate, status),
the orphan keys under it, and one summary line.

```
php please fallow:audit --collection=pages --collection=articles
php please fallow:audit --site=en
php please fallow:audit --min-fill=0.1
php please fallow:audit --json                  JSON on stdout, nothing else
php please fallow:audit --json=storage/fallow.json
php please fallow:audit --fail-on=dead,orphans  for CI
```

`--collection` and `--site` repeat, and default to everything. A handle that
does not exist is an error, not an empty result.

Exit codes: `0` finished and nothing in `--fail-on` was found, `1` finished
and at least one of `dead`, `sparse`, or `orphans` was found, `2` the input
was wrong.

With `--json` and no path, stdout is the JSON document and only the JSON
document, so `php please fallow:audit --json | jq .summary` works.

Drafts count. Every entry localization in scope is read, published or not.

## The control panel page

Under Tools, a page called Fallow shows the same numbers, with a filter per
collection. It is read-only: there is nothing on it to press.

Access is its own permission, **Access Fallow utility**, under Utilities in a
role's permissions. Super users have it. Nobody else does until a role grants
it.

The analysis runs while the page loads. Above `cp_entry_limit` entry
localizations (5,000 by default) the page shows a notice pointing at the
command instead, so a large site cannot time out a request. Narrowing to one
collection with the filter, or `?collection=`, counts only that collection
against the limit.

## What the numbers mean

The unit is the entry localization: an entry in two sites is two units. Each
is counted against the blueprint it declares, and a collection with two
blueprints gets two blocks that are never merged.

A value is **filled** unless it is null or absent, a string that is blank
once trimmed, or an empty array. `0`, `"0"`, `0.0`, and `false` are filled:
somebody chose them. A bard value is filled when the text in it is more than
whitespace or a set sits anywhere in it; one empty paragraph is empty.

**Denominators.** A top-level field is counted once per entry localization.
A field inside a grid or group is counted once per row of that parent, across
every entry. A field inside a replicator or bard set is counted once per
enabled instance of that set. A set instance with `enabled: false` counts for
nothing at all.

**Inheritance.** A localized entry stores only what it overrides and points
at an origin. Fallow resolves each top-level field to the nearest entry in
the origin chain whose own data has the key, however long the chain is. A key
present in the entry's own data wins even when its value is empty: an empty
override is an empty field, not a fall-through to the origin. Top-level rows
split `filled` into `filled_own` and `filled_inherited`. Nested rows do not
split.

**Status.** `never_instantiated` when the denominator is zero (a set nobody
has ever added, a blueprint nobody has used). `dead` when the fill rate is
zero. `sparse` below `min_fill`. `healthy` otherwise. A toggle also reports
`always_false` when every counted value is `false`.

**Orphan keys** are keys in an entry's own data with no top-level field in
its blueprint. Statamic's own keys (`id`, `slug`, `published`, `blueprint`,
`updated_by`, and the rest) are never reported, nor is any key starting with
two underscores. Only the top level of an entry's data is inspected.

**What is not counted.** Fields Statamic never stores a value for are left
out: `slug` (the filename), `date` in a dated collection (also the filename),
fields with `visibility: computed`, and the `section`, `html`, and `revealer`
fieldtypes. Fields an addon adds to a blueprint at runtime are counted,
because the editor sees them. A third-party fieldtype that draws a panel and
stores nothing will show as dead; there is no list of those.

## Config

`config/fallow.php`:

| Key | Default | Meaning |
|---|---|---|
| `min_fill` | `0.05` | Fill rate below which a field is sparse. `--min-fill` overrides it for one run. |
| `ignored_keys` | `[]` | Keys never reported as orphans, on top of the built-in list. For keys another addon writes to every entry on purpose. |
| `cp_entry_limit` | `5000` | Above this many entry localizations, the control panel page declines to analyze and points at the command. |

## JSON

```json
{
  "fallow_version": "1.0",
  "generated_at": "2026-09-14T14:03:00+00:00",
  "scope": { "collections": ["pages"], "sites": ["en", "fr"], "min_fill": 0.05 },
  "collections": [
    {
      "handle": "pages",
      "blueprints": [
        {
          "handle": "default",
          "entries_analyzed": 3,
          "fields": [
            {
              "path": "subtitle",
              "type": "text",
              "depth": 0,
              "denominator": 3,
              "filled": 2,
              "filled_own": 1,
              "filled_inherited": 1,
              "fill_rate": 0.6667,
              "status": "healthy"
            }
          ],
          "orphan_keys": [
            { "key": "legacy_teaser", "entries": 1, "samples": ["entry-id-2"] }
          ]
        }
      ]
    }
  ],
  "summary": {
    "fields_total": 11,
    "healthy": 8,
    "sparse": 0,
    "dead": 2,
    "never_instantiated": 1,
    "orphan_keys": 1
  }
}
```

Paths: a top-level field is its handle (`subtitle`), a grid or group child is
`parent.child` (`links.url`), a field in a set is `parent[set].child`
(`blocks[hero].heading`), and nesting composes left to right. Nested rows omit
`filled_own` and `filled_inherited`. `samples` holds at most five entry ids.
Everything is sorted by byte order, so two runs over the same content differ
only in `generated_at`.

## Not in scope

Fieldsets as a unit, taxonomies, globals, assets, users, and forms are not
audited. Only entries in collections. Fallow does not check that a stored
value's shape matches the fieldtype, and it never augments a value: every
number comes from raw stored data.

## Support

Report a bug or ask a question by opening an issue:

<https://github.com/bpmore/statamic-fallow/issues>

Every bug report gets a reply. Aim for a few working days. There is no
guaranteed response time, and nothing here phones home or runs on a schedule,
so a support problem is never an outage.

A useful report says which version of the addon and of Statamic you are on,
and includes the JSON from `php please fallow:audit --json` for the collection
in question if you can share it. `composer show bpmore/statamic-fallow
statamic/cms` gives the versions.

## Licence

Proprietary. See `LICENSE.md`. One licence covers one production site; local
development and CI are free.

## Tests

```
composer install
vendor/bin/pest
```

The fixtures under `tests/__fixtures__` and the expected numbers in
`tests/Feature/PagesFixtureTest.php` and `tests/Feature/OriginChainTest.php`
are the specification. `NOTES.md` records what in Statamic's source those
numbers rest on.
