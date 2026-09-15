<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Analysis;

use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;

/**
 * Turns a blueprint into the tree of fields the auditor counts, per spec
 * 4.1 and 4.2. It knows nothing about entries.
 *
 * Grid and group children come from `fields`. Replicator and bard children
 * come from `sets`, in either shape Statamic has used: the current one nests
 * sets under set groups (`sets -> group -> sets -> set -> fields`) and the
 * older one does not (`sets -> set -> fields`). Group handles never reach a
 * path.
 */
final class BlueprintWalker
{
    /**
     * @param  bool  $dated  whether the collection keeps entry dates in filenames
     * @return list<FieldNode>  the top-level fields, each with its subtree
     */
    public function walk(Blueprint $blueprint, bool $dated = false): array
    {
        $nodes = [];

        foreach ($blueprint->fields()->all() as $field) {
            if ($this->storedOutsideData($field, $dated)) {
                continue;
            }

            $nodes[] = $this->node($field, FieldPath::top($field->handle()), 0);
        }

        return $nodes;
    }

    /**
     * Fields whose value Statamic keeps as an entry property rather than in
     * the entry's data. Counting them would call them dead on every site.
     * The slug is the filename, and so is the date of a dated collection.
     * See NOTES.md.
     */
    private function storedOutsideData(Field $field, bool $dated): bool
    {
        if ($field->type() === 'slug') {
            return true;
        }

        return $dated && $field->type() === 'date' && $field->handle() === 'date';
    }

    private function node(Field $field, string $path, int $depth): FieldNode
    {
        $type = $field->type();

        if ($type === 'grid' || $type === 'group') {
            return new FieldNode($path, $field->handle(), $type, $depth, children: $this->children($field, $path, $depth));
        }

        if ($type === 'replicator' || $type === 'bard') {
            return new FieldNode($path, $field->handle(), $type, $depth, sets: $this->sets($field, $path, $depth));
        }

        return new FieldNode($path, $field->handle(), $type, $depth);
    }

    /**
     * @return list<FieldNode>
     */
    private function children(Field $field, string $path, int $depth): array
    {
        $nodes = [];

        foreach ($this->fields($field->get('fields')) as $child) {
            $nodes[] = $this->node($child, FieldPath::child($path, $child->handle()), $depth + 1);
        }

        return $nodes;
    }

    /**
     * @return array<string, list<FieldNode>>
     */
    private function sets(Field $field, string $path, int $depth): array
    {
        $nodes = [];

        foreach ($this->flattenSets($field->get('sets')) as $setHandle => $set) {
            $nodes[$setHandle] = [];

            foreach ($this->fields($set['fields'] ?? null) as $child) {
                $nodes[$setHandle][] = $this->node($child, FieldPath::inSet($path, $setHandle, $child->handle()), $depth + 1);
            }
        }

        return $nodes;
    }

    /**
     * Both set shapes, reduced to set handle => set config. The test for the
     * older shape is the one Statamic uses itself: the first entry has no
     * `sets` key of its own.
     *
     * @return array<string, array<string, mixed>>
     */
    private function flattenSets(mixed $sets): array
    {
        if (! is_array($sets) || $sets === []) {
            return [];
        }

        $first = reset($sets);

        if (! is_array($first) || ! array_key_exists('sets', $first)) {
            return $sets;
        }

        $flat = [];

        foreach ($sets as $group) {
            foreach ((array) ($group['sets'] ?? []) as $handle => $set) {
                $flat[$handle] = $set;
            }
        }

        return $flat;
    }

    /**
     * A field list as declared, resolved to Field objects the way Statamic
     * resolves it, so a reference into a fieldset yields a field with a type.
     *
     * @return list<Field>
     */
    private function fields(mixed $items): array
    {
        if (! is_array($items) || $items === []) {
            return [];
        }

        return (new Fields($items))->all()->values()->all();
    }
}
