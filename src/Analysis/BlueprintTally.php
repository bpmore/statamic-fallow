<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Analysis;

/**
 * Everything counted for one blueprint of one collection: a tally per field
 * path, the orphan keys seen, and how many entry localizations were read.
 */
final class BlueprintTally
{
    public const SAMPLE_LIMIT = 5;

    public int $entries = 0;

    /** @var array<string, FieldTally> by path */
    public array $fields = [];

    /** @var array<string, array{entries: int, samples: list<string>}> by key */
    public array $orphans = [];

    /**
     * @param  list<FieldNode>  $tree  the walked top-level fields
     * @param  list<string>  $declared  every top-level handle the blueprint declares, walked or not
     */
    public function __construct(
        public readonly string $handle,
        public readonly array $tree,
        private readonly array $declared = [],
    ) {
        foreach ($tree as $top) {
            foreach ($top->flatten() as $node) {
                $this->fields[$node->path] = new FieldTally($node->path, $node->type, $node->depth);
            }
        }
    }

    public function field(string $path): FieldTally
    {
        return $this->fields[$path];
    }

    /**
     * Whether the blueprint has a top-level field by this handle, including
     * the ones the walker skips. A key with a field is never an orphan.
     */
    public function declares(string $handle): bool
    {
        return in_array($handle, $this->declared, true);
    }

    /**
     * Record an orphan key on an entry. Samples are the five smallest entry
     * ids by byte order, whatever order the entries were read in, so two
     * runs agree.
     */
    public function orphan(string $key, string $entryId): void
    {
        $orphan = &$this->orphans[$key];
        $orphan ??= ['entries' => 0, 'samples' => []];

        $orphan['entries']++;

        $samples = $orphan['samples'];
        $samples[] = $entryId;
        usort($samples, 'strcmp');
        $orphan['samples'] = array_slice($samples, 0, self::SAMPLE_LIMIT);
    }
}
