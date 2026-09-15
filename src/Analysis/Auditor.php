<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Analysis;

use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Fields\Blueprint;

/**
 * Reads the content and counts it. The only class here that touches
 * Statamic's repositories.
 *
 * One pass: every entry localization in scope is read once, in chunks, and
 * counted against the tree the walker built for its blueprint. Nothing is
 * augmented; every value is the raw stored one, resolved up the origin chain
 * by hand per spec 4.6.
 */
final class Auditor
{
    public const CHUNK = 100;

    /** Keys every entry may carry that are not content. Spec 4.10. */
    public const IGNORED_KEYS = [
        'id', 'origin', 'published', 'blueprint', 'slug', 'date', 'order', 'parent', 'collection',
        'site', 'locale', 'template', 'layout', 'created_at', 'created_by', 'updated_at', 'updated_by',
    ];

    /** @var array<string, true> */
    private array $ignoredKeys;

    /**
     * @param  list<string>  $extraIgnoredKeys  from config, merged with the built-in list
     */
    public function __construct(
        private readonly BlueprintWalker $walker,
        array $extraIgnoredKeys = [],
    ) {
        $this->ignoredKeys = array_fill_keys(array_merge(self::IGNORED_KEYS, array_values($extraIgnoredKeys)), true);
    }

    /**
     * @throws UnknownScope
     */
    public function audit(Scope $scope): Report
    {
        $collections = $this->collections($scope);
        $sites = $this->sites($scope);

        $report = new Report($scope, array_map(fn ($c) => $c->handle(), $collections), $sites);

        foreach ($collections as $collection) {
            $report->add($collection->handle(), $this->auditCollection($collection, $sites));
        }

        return $report;
    }

    /**
     * How many entry localizations a run would read. For the control panel
     * guard, which must know before analyzing.
     *
     * @throws UnknownScope
     */
    public function countEntries(Scope $scope): int
    {
        $sites = $this->sites($scope);
        $total = 0;

        foreach ($this->collections($scope) as $collection) {
            $total += $this->query($collection, $sites)->count();
        }

        return $total;
    }

    /**
     * @return list<CollectionContract>  sorted by handle
     *
     * @throws UnknownScope
     */
    private function collections(Scope $scope): array
    {
        if ($scope->collections === []) {
            $collections = Collection::all()->all();
        } else {
            $collections = [];

            foreach ($scope->collections as $handle) {
                $collections[] = Collection::findByHandle($handle)
                    ?? throw new UnknownScope("Unknown collection [{$handle}].");
            }
        }

        usort($collections, fn ($a, $b) => strcmp($a->handle(), $b->handle()));

        return array_values($collections);
    }

    /**
     * @return list<string>  site handles, sorted
     *
     * @throws UnknownScope
     */
    private function sites(Scope $scope): array
    {
        $handles = $scope->sites === []
            ? Site::all()->map->handle()->values()->all()
            : $scope->sites;

        foreach ($handles as $handle) {
            if (! Site::get($handle)) {
                throw new UnknownScope("Unknown site [{$handle}].");
            }
        }

        $handles = array_values(array_unique($handles));
        usort($handles, 'strcmp');

        return $handles;
    }

    /**
     * @param  list<string>  $sites
     */
    private function query(CollectionContract $collection, array $sites)
    {
        return Entry::query()
            ->where('collection', $collection->handle())
            ->whereIn('site', $sites);
    }

    /**
     * @param  list<string>  $sites
     * @return list<BlueprintTally>  sorted by blueprint handle
     */
    private function auditCollection(CollectionContract $collection, array $sites): array
    {
        // Every blueprint the collection has, walked once. A blueprint no
        // entry uses still reports, with every field never instantiated.
        $tallies = [];

        foreach ($collection->entryBlueprints() as $blueprint) {
            $tallies[$blueprint->handle()] = $this->tally($collection, $blueprint);
        }

        $this->query($collection, $sites)->chunk(self::CHUNK, function ($entries) use (&$tallies, $collection) {
            foreach ($entries as $entry) {
                $handle = $entry->blueprint()->handle();

                $tallies[$handle] ??= $this->tally($collection, $entry->blueprint());

                $this->auditEntry($entry, $tallies[$handle]);
            }
        });

        $tallies = array_values($tallies);
        usort($tallies, fn ($a, $b) => strcmp($a->handle, $b->handle));

        return $tallies;
    }

    private function tally(CollectionContract $collection, Blueprint $blueprint): BlueprintTally
    {
        return new BlueprintTally(
            $blueprint->handle(),
            $this->walker->walk($blueprint, $collection->dated()),
            $blueprint->fields()->all()->keys()->all(),
        );
    }

    private function auditEntry(EntryContract $entry, BlueprintTally $tally): void
    {
        $tally->entries++;

        $own = $entry->data()->all();
        $chain = $this->originChain($entry);

        foreach ($tally->tree as $node) {
            [$value, $isOwn] = $this->resolve($node->handle, $own, $chain);

            $tally->field($node->path)->count($value, $isOwn);

            $this->auditNested($node, $value, $tally);
        }

        foreach (array_keys($own) as $key) {
            $key = (string) $key;

            if ($tally->declares($key) || isset($this->ignoredKeys[$key]) || str_starts_with($key, '__')) {
                continue;
            }

            $tally->orphan($key, (string) $entry->id());
        }
    }

    /**
     * The own data of every ancestor, nearest first. Read once per entry so
     * every field resolves against the same chain.
     *
     * @return list<array<string, mixed>>
     */
    private function originChain(EntryContract $entry): array
    {
        $chain = [];
        $seen = [(string) $entry->id() => true];
        $origin = $entry->origin();

        while ($origin !== null && ! isset($seen[(string) $origin->id()])) {
            $seen[(string) $origin->id()] = true;
            $chain[] = $origin->data()->all();
            $origin = $origin->origin();
        }

        return $chain;
    }

    /**
     * Spec 4.6: presence in own data wins, even a null; otherwise the
     * closest ancestor whose own data has the key.
     *
     * @param  array<string, mixed>  $own
     * @param  list<array<string, mixed>>  $chain
     * @return array{mixed, bool|null}  the value and whether it was the entry's own; null when absent everywhere
     */
    private function resolve(string $handle, array $own, array $chain): array
    {
        if (array_key_exists($handle, $own)) {
            return [$own[$handle], true];
        }

        foreach ($chain as $data) {
            if (array_key_exists($handle, $data)) {
                return [$data[$handle], false];
            }
        }

        return [null, null];
    }

    /**
     * Count the rows and set instances inside a container value, using the
     * value the entry resolves to (its own or an inherited one), and recurse
     * into containers inside those.
     */
    private function auditNested(FieldNode $node, mixed $value, BlueprintTally $tally): void
    {
        if (! is_array($value)) {
            return;
        }

        if ($node->isGridOrGroup()) {
            foreach ($this->rows($node, $value) as $row) {
                $this->auditRow($node->children, $row, $tally);
            }

            return;
        }

        if ($node->isReplicatorOrBard()) {
            foreach ($this->setInstances($node, $value) as [$setHandle, $row]) {
                if (isset($node->sets[$setHandle])) {
                    $this->auditRow($node->sets[$setHandle], $row, $tally);
                }
            }
        }
    }

    /**
     * @param  list<FieldNode>  $fields
     * @param  array<string, mixed>  $row
     */
    private function auditRow(array $fields, array $row, BlueprintTally $tally): void
    {
        foreach ($fields as $field) {
            $value = $row[$field->handle] ?? null;

            $tally->field($field->path)->count($value, null);

            $this->auditNested($field, $value, $tally);
        }
    }

    /**
     * A grid holds a list of rows. A group holds one row: its own array.
     *
     * @param  array<mixed>  $value
     * @return list<array<string, mixed>>
     */
    private function rows(FieldNode $node, array $value): array
    {
        if ($node->type === 'group') {
            return $value === [] ? [] : [$value];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * Every enabled set instance in a replicator or bard value, as
     * [set handle, instance data].
     *
     * A replicator row is `{type, enabled?, ...fields}`. A bard set is a
     * node `{type: set, attrs: {enabled?, values: {type, ...fields}}}`, and
     * can sit anywhere in the node tree. Spec 4.4: an instance with
     * `enabled` false counts for nothing.
     *
     * @param  array<mixed>  $value
     * @return list<array{string, array<string, mixed>}>
     */
    private function setInstances(FieldNode $node, array $value): array
    {
        if ($node->type === 'replicator') {
            $instances = [];

            foreach ($value as $row) {
                if (! is_array($row) || ! is_string($row['type'] ?? null) || ($row['enabled'] ?? true) === false) {
                    continue;
                }

                $instances[] = [$row['type'], $row];
            }

            return $instances;
        }

        $instances = [];
        $this->collectBardSets($value, $instances);

        return $instances;
    }

    /**
     * @param  array<mixed>  $nodes
     * @param  list<array{string, array<string, mixed>}>  $instances
     */
    private function collectBardSets(array $nodes, array &$instances): void
    {
        foreach ($nodes as $child) {
            if (! is_array($child)) {
                continue;
            }

            if (($child['type'] ?? null) === 'set') {
                $values = $child['attrs']['values'] ?? null;

                if (is_array($values) && is_string($values['type'] ?? null)
                    && ($child['attrs']['enabled'] ?? true) !== false
                    && ($values['enabled'] ?? true) !== false) {
                    $instances[] = [$values['type'], $values];
                }

                continue;
            }

            $this->collectBardSets($child, $instances);
        }
    }
}
