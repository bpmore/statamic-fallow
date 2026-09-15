# Fallow: Content Model Drift Auditor for Statamic 6

**Build specification.**

`fallow-build-brief.md` covers workflow, repo location, build order, and handoff. This
document is the normative specification: definitions, schema, and expected results.
Where the two conflict, the brief wins.

Where this document is silent, choose the simplest behavior that satisfies the
acceptance tests in section 9, and record the choice in your handoff. Where this
document appears to be wrong, stop and say so rather than working around it.

Do not use em dashes in any code comment, README, or CP copy you write. Use a colon
for introductions, a comma or parentheses for asides, or split into two sentences.

---

## 1. What you are building

A Statamic 6 addon named **Fallow** that reports content model drift: which blueprint
fields are actually filled in across real content, which are dead weight, and which
data keys exist in entries with no matching blueprint field.

It ships three things:

1. A pure PHP analyzer service with no framework coupling beyond Statamic's read APIs.
2. An Artisan command (`php please fallow:audit`) with human and JSON output, and CI exit codes.
3. A read-only Control Panel utility that renders the same report.

Package name: `bpmore/statamic-fallow`. Addon namespace: `Bpmore\Fallow`.

---

## 2. Non-goals

Do not implement any of the following. Implementing them counts against you.

- Writing, editing, or deleting any content, blueprint, or config.
- A Vue or Inertia page. The CP utility must be a Blade view in v1.
- Any network call, AI call, or external service.
- Caching, queues, or scheduled jobs.
- Field *type* mismatch detection (stored value shape versus declared fieldtype).
- Fieldsets, taxonomies, globals, assets, users, or forms. Entries in collections only.

---

## 3. Statamic API surface

Establish this yourself from `vendor/statamic/cms`. Do not work from memory or from any
list in this document: v6 moved things, and the source is right there.

Two claims below are load-bearing for section 4 and are worth confirming or refuting
before you write analyzer code.

**Claim 1.** `$entry->data()` returns the entry's own data only, while `$entry->values()`
applies inheritance from its origin. A localized entry stores only the values it
overrides, plus an `origin` reference. Start at `Statamic\Data\HasOrigin`.
Docs: https://statamic.dev/extending/data and
https://statamic.dev/knowledge-base/tips/localizing-entries

**Claim 2.** Utilities register through
`Utility::extend(fn () => Utility::register('fallow')->view(...))`, which supplies the
route, nav item, and permission.
Docs: https://statamic.dev/control-panel/utilities

If either claim is false in the installed version, that changes section 4 or section 7
and you should raise it.

**Hard constraint, not negotiable:** never call augmentation (`augmentedValue`,
`toAugmentedArray`, property access that triggers augmentation) anywhere in the
analyzer. Augmentation is expensive and changes value shapes. Work on raw values only.

---

## 4. Normative definitions

These definitions are the specification. The acceptance tests in section 9 are derived
from them, so read them as rules, not description.

### 4.1 Field path grammar

Every field in a blueprint gets a unique, stable path string.

- Top-level field: the handle. Example: `subtitle`
- Child of a `grid` or `group` field: `parent.child`. Example: `links.url`
- Field inside a `replicator` or `bard` set: `parent[set_handle].child`.
  Example: `blocks[hero].heading`
- Nesting composes left to right. Example: `blocks[hero].links.url`

### 4.2 Walking the blueprint

Recurse into `grid`, `group`, `replicator`, and `bard` fields.

Statamic 4 and later nests replicator and bard sets inside **set groups**. The config
shape is `sets` -> group handle -> `sets` -> set handle -> `fields`. Handle both that
shape and the older flat `sets` -> set handle -> `fields` shape. Group handles never
appear in a field path.

`grid` and `group` fields hold their children under `fields`.

Two different sets may declare the same subfield handle. They are different fields and
get different paths.

### 4.3 Emptiness

A value is **filled** unless one of these makes it empty:

| Value | Result |
|---|---|
| `null`, or key absent | empty |
| string that is empty after trimming whitespace | empty |
| `"0"`, `0`, `0.0`, `false` | **filled** |
| array with zero elements | empty |
| array with one or more elements | filled, unless the bard rule below applies |

**Bard rule.** A `bard` value is an array of ProseMirror nodes. Recursively collect
every `text` property in the tree and concatenate. The value is filled if the
concatenation has non-whitespace content, **or** if any node anywhere in the tree has
`type` equal to `set`. Otherwise it is empty. A single empty paragraph is empty.

### 4.4 Disabled sets

A replicator or bard set instance whose data contains `enabled` set to boolean `false`
is skipped entirely. It contributes nothing to any denominator or numerator. A set with
`enabled` absent or `true` is counted.

### 4.5 Unit of analysis

The unit is the **entry localization**, not the logical entry. An entry that exists in
two sites is two units. Each unit is analyzed against the blueprint it declares. A
collection with multiple blueprints produces one report block per blueprint. Never merge
field statistics across blueprints.

### 4.6 Own versus inherited

For top-level fields, compute fill from `values()` (inheritance applied), and also split
the filled count:

- **filled_own**: the entry's own data contains the key.
- **filled_inherited**: the key is absent from own data and the filled value came from
  an origin.

An entry with no origin contributes only to `filled_own`.

For nested fields (anything with a `.` or `[` in its path) do not split. Aggregate rows
regardless of origin.

**Resolution rule, normative.** Presence of a key in an entry's own data wins, even when
the stored value is `null` or an empty string. Inheritance resolves to the **closest**
ancestor in the origin chain whose own data contains the key, not to the root. Origin
chains can be longer than one hop.

If the installed version's `values()` disagrees with this rule, follow this rule.
Resolve the chain yourself through `origin()` and `data()`. Do not assume one hop.

### 4.7 Denominators

- Top-level field: the number of entry localizations in scope using that blueprint.
- Child of a grid or group: the number of rows of that parent encountered across all
  entries in scope, using inherited values.
- Field in a replicator or bard set: the number of enabled instances of that specific
  set encountered across all entries in scope, using inherited values.

`fill_rate` is `filled / denominator`, rounded to 4 decimal places, or `null` when the
denominator is 0.

### 4.8 Status

- `denominator == 0` -> `never_instantiated`
- `fill_rate == 0` -> `dead`
- `0 < fill_rate < min_fill` -> `sparse`
- otherwise -> `healthy`

`min_fill` defaults to `0.05` and is settable per run.

### 4.9 Boolean flag

For fields of type `toggle`, add `"always_false": true` when the denominator is greater
than 0 and every counted value is boolean `false`. Otherwise `false`. Omit the key for
all other fieldtypes.

### 4.10 Orphan keys

An orphan key is a key present in an entry's **own** data (`data()`, not `values()`)
with no matching top-level blueprint field, and not in the ignore list. Nested orphan
detection is out of scope for v1: only inspect the top level of the entry's own data.

Ignore list, exact:

```
id, origin, published, blueprint, slug, date, order, parent, collection,
site, locale, template, layout, created_at, created_by, updated_at, updated_by
```

Also ignore any key beginning with two underscores. `__localized_fields` must not be
reported.

The list is extendable through `config/fallow.php` under `ignored_keys`, which is merged
with, and never replaces, the built-in list.

### 4.11 Determinism

Two runs over identical content must produce byte-identical JSON apart from
`generated_at`. Sort collections by handle, blueprints by handle, fields by path,
orphan keys by key, and sample IDs by ID, all ascending by byte order.

---

## 5. Report JSON schema

```json
{
  "fallow_version": "1.0",
  "generated_at": "2026-09-14T14:03:00+00:00",
  "scope": {
    "collections": ["pages"],
    "sites": ["en", "fr"],
    "min_fill": 0.05
  },
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

Rules:

- `depth` is 0 for top-level, incrementing by 1 per level of nesting.
- Nested field objects omit `filled_own` and `filled_inherited` entirely.
- `samples` holds at most 5 entry IDs.
- `summary` counts every field object across every blueprint.

---

## 6. Artisan command

```
php please fallow:audit
    [--collection=* ]      repeatable, default all collections
    [--site=* ]            repeatable, default all sites
    [--min-fill=0.05]
    [--json[=path] ]       write JSON to path, or stdout when no path given
    [--fail-on=]           comma list of: dead, orphans, sparse
```

Human output is a table per blueprint with columns Path, Type, Filled, Denominator,
Rate, Status, followed by an orphan key list and a summary line.

When `--json` is given with no path, **only** JSON goes to stdout. No table, no banner,
no progress output. It must parse as JSON on its own.

Exit codes:

- `0` completed, nothing in `--fail-on` was triggered
- `1` completed, at least one condition in `--fail-on` was found
- `2` invalid input, for example an unknown collection handle

An unknown collection or site handle is an error, not a silent empty result.

---

## 7. Control Panel utility

Register under `Utility::extend` with handle `fallow`, title "Fallow", nav title
"Fallow", description "Find blueprint fields your content never uses.", and a Statamic
built-in icon of your choice.

The view is Blade. It renders the summary counts, a per-blueprint table matching the
command output, and the orphan list. Add an optional `?collection=` query filter.

The utility must be gated by its own permission. The page is read-only: no buttons that
mutate anything.

Analysis runs synchronously on page load. Guard it: if the total entry count in scope
exceeds `config('fallow.cp_entry_limit')` (default 5000), render a notice telling the
user to run the Artisan command instead, and do not analyze.

---

## 8. Performance

- Iterate entries in chunks of 100. Never materialize the full entry set.
- Peak memory under 256 MB for a synthetic corpus of 10,000 entry localizations.
- One pass over the content. Do not re-query per field.
- Blueprint walking happens once per blueprint, not once per entry.

---

## 9. Acceptance tests

Use Pest with `statamic/cms` test helpers, or PHPUnit if Pest is unavailable. These
fixtures and expected numbers are normative. Your implementation must reproduce them.

### 9.1 Fixture

Sites: `en` (default) and `fr`.

Collection `pages`, one blueprint `default`:

```yaml
fields:
  - handle: title
    field: { type: text }
  - handle: subtitle
    field: { type: text }
  - handle: promo
    field: { type: text }
  - handle: featured
    field: { type: toggle }
  - handle: links
    field:
      type: grid
      fields:
        - handle: label
          field: { type: text }
        - handle: url
          field: { type: text }
  - handle: blocks
    field:
      type: replicator
      sets:
        main:
          sets:
            hero:
              fields:
                - handle: heading
                  field: { type: text }
                - handle: cta
                  field: { type: text }
            quote:
              fields:
                - handle: body
                  field: { type: textarea }
  - handle: body
    field: { type: bard }
```

Entries:

- **E1**, site `en`, no origin.
  `title: "Home"`, `subtitle: "Welcome"`, `featured: true`,
  `links: [{label: "A", url: "/a"}, {label: "B", url: ""}]`,
  `blocks: [{type: hero, heading: "Hi", cta: ""}]`,
  `body: [{type: paragraph, content: [{type: text, text: "hello"}]}]`
- **E2**, site `en`, no origin.
  `title: "About"`, `subtitle: ""`, `featured: false`, `links: []`,
  `blocks: [{type: quote, body: "Q"}, {type: hero, heading: "", cta: "Go"}]`,
  `body: []`, `legacy_teaser: "old"`
- **E3**, site `fr`, origin E1. Own data is exactly:
  `title: "Accueil"`, `__localized_fields: ["title"]`

### 9.2 Expected field results

`entries_analyzed` is 3.

| path | denominator | filled | own | inherited | rate | status |
|---|---|---|---|---|---|---|
| `title` | 3 | 3 | 3 | 0 | 1.0 | healthy |
| `subtitle` | 3 | 2 | 1 | 1 | 0.6667 | healthy |
| `promo` | 3 | 0 | 0 | 0 | 0.0 | dead |
| `featured` | 3 | 3 | 2 | 1 | 1.0 | healthy |
| `links` | 3 | 2 | 1 | 1 | 0.6667 | healthy |
| `blocks` | 3 | 3 | 2 | 1 | 1.0 | healthy |
| `body` | 3 | 2 | 1 | 1 | 0.6667 | healthy |
| `links.label` | 4 | 4 | n/a | n/a | 1.0 | healthy |
| `links.url` | 4 | 2 | n/a | n/a | 0.5 | healthy |
| `blocks[hero].heading` | 3 | 2 | n/a | n/a | 0.6667 | healthy |
| `blocks[hero].cta` | 3 | 1 | n/a | n/a | 0.3333 | healthy |
| `blocks[quote].body` | 1 | 1 | n/a | n/a | 1.0 | healthy |

Reasoning you must reproduce, not special-case:

- E3 inherits `links` and `blocks` from E1, so its rows and set instances count toward
  the nested denominators. `links` rows total 4, not 2. `hero` instances total 3.
- `featured: false` on E2 is filled, and `always_false` is `false` because E1 is `true`.
- E2's `body: []` is empty. E1's single paragraph with text is filled.
- `promo` has denominator 3, so it is `dead`, not `never_instantiated`.

### 9.3 Expected orphans

One entry: `{"key": "legacy_teaser", "entries": 1, "samples": ["<E2 id>"]}`.
`__localized_fields` on E3 must not appear.

### 9.4 Additional required tests

1. A set declared in the blueprint but never used anywhere: its subfields report
   `denominator: 0`, `fill_rate: null`, `status: "never_instantiated"`.
2. A replicator row with `enabled: false` is excluded from denominators and numerators.
3. A bard value of `[{"type":"paragraph"}]` with no content is empty; the same value
   with a nested `{"type":"set"}` node is filled.
4. A collection with two blueprints keeps their field statistics separate.
5. Two sets that both declare a `body` subfield produce two distinct paths.
6. `--json` output parses as JSON with no extra bytes on stdout.
7. `--fail-on=dead` exits 1 on the section 9.1 fixture; without it, exit 0.
8. An unknown `--collection` handle exits 2.
9. Two consecutive runs produce identical JSON apart from `generated_at`.

### 9.5 Origin chain fixture

This fixture is also normative. It tests section 4.6 resolution across two hops.

Sites: `en`, `fr`, `fr-CA`.

Collection `products`, blueprint `default`, with five text fields: `name`, `tagline`,
`badge`, `sku`, `color`.

Entries:

- **P1**, site `en`, no origin. Own data:
  `name: "Widget"`, `tagline: "Fast"`, `badge: "New"`, `sku: "A1"`
- **P2**, site `fr`, origin P1. Own data:
  `tagline: "Rapide"`, `badge: ""`, `sku: ""`, `color: "Bleu"`
- **P3**, site `fr-CA`, origin **P2**. Own data:
  `badge: "Nouveau"`

Expected, `entries_analyzed` 3:

| path | denominator | filled | own | inherited | rate |
|---|---|---|---|---|---|
| `name` | 3 | 3 | 1 | 2 | 1.0 |
| `tagline` | 3 | 3 | 2 | 1 | 1.0 |
| `badge` | 3 | 2 | 2 | 0 | 0.6667 |
| `sku` | 3 | 1 | 1 | 0 | 0.3333 |
| `color` | 3 | 2 | 1 | 1 | 0.6667 |

The load-bearing cases:

- `name` on P3 resolves through two hops, P3 to P2 to P1. It is inherited and filled.
- `sku` on P3 resolves to P2's empty override and is **empty**. It does not fall through
  to P1's `"A1"`. An implementation that walks to the root reports 3 filled here and is
  wrong.
- `badge` on P2 is present in own data and empty, so it is neither `filled_own` nor
  `filled_inherited`. P3 overrides it and counts as `filled_own`.
- `color` does not exist on P1 at all. P2 introduces it, P3 inherits it.

---

## 10. Deliverable

Working code in the repo, not a transcript. Build order, commit discipline, and handoff
format are in `fallow-build-brief.md`.

Expected files:

```
composer.json
README.md
config/fallow.php
src/ServiceProvider.php
src/Analysis/BlueprintWalker.php
src/Analysis/FieldPath.php
src/Analysis/ValueInspector.php
src/Analysis/Auditor.php
src/Analysis/Report.php
src/Commands/AuditCommand.php
resources/views/utility.blade.php
tests/
```

Class contracts:

- `ValueInspector::isFilled(mixed $value, string $fieldtype): bool` is pure and has no
  Statamic dependency. It is unit-testable on plain arrays and scalars.
- `BlueprintWalker` turns a blueprint into an ordered list of path, type, depth records
  and has no knowledge of entries.
- `Auditor` is the only class that touches Statamic repositories.
- `Report::toArray()` produces exactly the section 5 schema.
