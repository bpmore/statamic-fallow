<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Analysis;

/**
 * One blueprint field as the walker saw it: its path, type, and depth, plus
 * the fields under it when it is a container.
 *
 * A grid or group holds its children in `children`. A replicator or bard
 * holds them in `sets`, keyed by set handle, because a child there only
 * exists inside an instance of that set. A field is never both.
 */
final class FieldNode
{
    /**
     * @param  list<FieldNode>  $children
     * @param  array<string, list<FieldNode>>  $sets
     */
    public function __construct(
        public readonly string $path,
        public readonly string $handle,
        public readonly string $type,
        public readonly int $depth,
        public readonly array $children = [],
        public readonly array $sets = [],
    ) {}

    public function isGridOrGroup(): bool
    {
        return $this->type === 'grid' || $this->type === 'group';
    }

    public function isReplicatorOrBard(): bool
    {
        return $this->type === 'replicator' || $this->type === 'bard';
    }

    /**
     * This node and everything under it, in walk order.
     *
     * @return list<FieldNode>
     */
    public function flatten(): array
    {
        $out = [$this];

        foreach ($this->children as $child) {
            array_push($out, ...$child->flatten());
        }

        foreach ($this->sets as $fields) {
            foreach ($fields as $field) {
                array_push($out, ...$field->flatten());
            }
        }

        return $out;
    }
}
