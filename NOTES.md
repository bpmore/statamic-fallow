# Notes: the Statamic API surface Fallow relies on

Verified against `statamic/cms` v6.32.0 (the version installed here and on the
greenhouse testbed). Line numbers are from that version.

## Entry data, values, and origin

`Statamic\Data\HasOrigin`:

- `values()` (line 34) is `getValues(false)` (line 39), which merges four layers in
  order: the collection cascade (`getOriginFallbackValues`, `Entry.php:1060`), the
  origin's own `values()` (recursive, so chains of any length resolve), the entry's
  own `data`, then computed data. Later layers win by key, using Laravel's collection
  merge, so a key present in own data with a `null` or `""` value does override the
  origin. That confirms spec 4.6: presence wins, and the closest ancestor with the
  key wins.
- `value($key)` (line 54) does the same for one key: `has($key)` on own data, else
  the origin's `value($key)`, else the cascade.
- `origin()` (line 69) returns the origin entry, resolved through
  `Entry::getOriginByString` (`Entry.php:1055`), which is a query on the collection
  by id, cached in Blink.

`Statamic\Data\ContainsData`:

- `data()` (line 56) returns only the entry's own data as a Collection.
- `has($key)` (line 16) is `$this->data->has($key)`, which is key presence, not
  truthiness. A stored `null` counts as present.

Spec claim 1 is confirmed. Two things `values()` does that the spec does not want:
it merges the collection cascade (`inject:` values from the collection file), and it
merges computed values. Neither comes from an origin, and counting either as
"inherited" would be wrong. Fallow therefore resolves inheritance itself, walking
`data()` up the `origin()` chain, exactly as spec 4.6 allows. The results agree with
`values()` whenever there is no cascade and no computed field.

## What an entry file puts in `data()`

`Statamic\Stache\Stores\CollectionEntriesStore::makeItemFromFile` (line 57) pulls
`id` (line 63), `origin` (line 75), and `published` (line 83) out of the parsed YAML
before the rest becomes `data()`. `blueprint`, `title`, `updated_by`, and everything
else stay in data. The spec's orphan ignore list covers those.

`Entry::fileData()` (line 719) strips null values from a root entry before writing,
but not from a localized one. A localized entry can therefore hold an explicit
`null`, which spec 4.6 says still counts as presence.

## The effective blueprint

`Entry::blueprint()` (line 186) resolves through
`Collection::entryBlueprint($handle, $entry)` (`Collection.php:344`), which returns
the blueprint after `ensureEntryBlueprintFields` (`Collection.php:391`) has added
fields the file may not declare: `title`, `slug` (when the collection requires slugs,
the default), `date` (dated collections), and one `terms` field per attached
taxonomy.

Two of those never live in `data()`: `slug` is the filename, and `date` in a dated
collection is in the filename too (`Entry::date`, line 617). Walking them would
report both as dead on every site. Fallow walks the effective blueprint, so taxonomy
fields are recognised, and skips top-level fields Statamic stores as entry
properties: any field of type `slug`, and a `date` field in a dated collection.
That is a rule about Statamic's storage, not about any fixture.

## Chunked iteration

`Statamic\Query\Builder::chunk($count, callable)` (line 848) pages with
`offset`/`limit` and `get()`, and `lazy($chunkSize)` (line 896) yields one entry at a
time from the same paging. Both exist on the Stache builder through inheritance.
Fallow uses `chunk(100, ...)` per spec 8.

## Field config shapes

`Statamic\Fields\Field::config()` (line 419) returns the raw config array as
declared, with no normalisation.

- `grid` and `group`: children under `config['fields']`, a list of
  `{handle, field}` items (`Grid.php:144`, `Group.php:97`).
- `replicator` and `bard`: `config['sets']`. `Replicator::flattenedSetsConfig()`
  (line 286) shows the two shapes: if the first entry of `sets` has no `sets` key
  it is the legacy flat shape and Statamic wraps it in a group called `main`
  (line 301). Otherwise it is `sets -> group -> sets -> set -> fields`. `Bard`
  extends `Replicator` (`Bard.php:33`) and uses the same method. Fallow applies the
  same test and never puts the group handle in a path, per spec 4.2.

Each `{handle, field}` item may hold `field` as an inline array or as a string
reference into a fieldset, and a list may hold `{import: fieldset}` items
(`Fields.php:238` to `Fields.php:275`). Fallow resolves every list through
`Statamic\Fields\Fields` so references and imports produce real `Field` objects
with their config. That is not fieldset support in the sense of spec 2 (fieldsets
are not audited); it is only how Statamic turns a field list into fields.

## Utilities and permissions

`Statamic\CP\Utilities\UtilityRepository`: `extend($callback)` (line 30) queues a
callback run at boot; `register($utility)` (line 40) accepts a handle string or a
`Utility`. `Utility::view($view, $data)` takes the Blade view name and a callback
given the request. `Statamic\Auth\CorePermissions` registers
`access {handle} utility` for every registered utility (line 240), and
`UtilityRepository::routes()` guards the route with that permission (line 78).
Spec claim 2 is confirmed. A Blade utility view is rendered server side and handed
to an Inertia page as HTML (`UtilitiesController::show`, line 24).
