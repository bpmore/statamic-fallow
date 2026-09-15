<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Analysis;

use Illuminate\Support\Carbon;

/**
 * The finished audit, and the one place that knows the JSON shape in spec
 * section 5. Everything is sorted by byte order on the way out so two runs
 * over the same content produce the same bytes apart from `generated_at`.
 */
final class Report
{
    public const VERSION = '1.0';

    public const HEALTHY = 'healthy';

    public const SPARSE = 'sparse';

    public const DEAD = 'dead';

    public const NEVER_INSTANTIATED = 'never_instantiated';

    /** @var array<string, list<BlueprintTally>> by collection handle */
    private array $collections = [];

    private Carbon $generatedAt;

    /**
     * @param  list<string>  $collectionHandles  the collections in scope, resolved
     * @param  list<string>  $siteHandles  the sites in scope, resolved
     */
    public function __construct(
        public readonly Scope $scope,
        private readonly array $collectionHandles,
        private readonly array $siteHandles,
    ) {
        $this->generatedAt = Carbon::now();
    }

    /**
     * @param  list<BlueprintTally>  $tallies
     */
    public function add(string $collection, array $tallies): void
    {
        $this->collections[$collection] = $tallies;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $collections = [];
        $summary = [
            'fields_total' => 0,
            self::HEALTHY => 0,
            self::SPARSE => 0,
            self::DEAD => 0,
            self::NEVER_INSTANTIATED => 0,
            'orphan_keys' => 0,
        ];

        $handles = array_keys($this->collections);
        usort($handles, 'strcmp');

        foreach ($handles as $handle) {
            $blueprints = [];

            foreach ($this->collections[$handle] as $tally) {
                $block = $this->blueprint($tally);
                $blueprints[] = $block;

                foreach ($block['fields'] as $field) {
                    $summary['fields_total']++;
                    $summary[$field['status']]++;
                }

                $summary['orphan_keys'] += count($block['orphan_keys']);
            }

            usort($blueprints, fn ($a, $b) => strcmp($a['handle'], $b['handle']));

            $collections[] = ['handle' => $handle, 'blueprints' => $blueprints];
        }

        return [
            'fallow_version' => self::VERSION,
            'generated_at' => $this->generatedAt->toIso8601String(),
            'scope' => [
                'collections' => $this->collectionHandles,
                'sites' => $this->siteHandles,
                'min_fill' => $this->scope->minFill,
            ],
            'collections' => $collections,
            'summary' => $summary,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /**
     * Whether any field or orphan in the report matches a `--fail-on`
     * condition: `dead`, `sparse`, or `orphans`.
     */
    public function has(string $condition): bool
    {
        $array = $this->toArray();

        if ($condition === 'orphans') {
            return $array['summary']['orphan_keys'] > 0;
        }

        return ($array['summary'][$condition] ?? 0) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function blueprint(BlueprintTally $tally): array
    {
        $fields = array_map(fn (FieldTally $f) => $this->field($f), array_values($tally->fields));
        usort($fields, fn ($a, $b) => strcmp($a['path'], $b['path']));

        $orphans = [];

        foreach ($tally->orphans as $key => $orphan) {
            $orphans[] = ['key' => (string) $key, 'entries' => $orphan['entries'], 'samples' => $orphan['samples']];
        }

        usort($orphans, fn ($a, $b) => strcmp($a['key'], $b['key']));

        return [
            'handle' => $tally->handle,
            'entries_analyzed' => $tally->entries,
            'fields' => $fields,
            'orphan_keys' => $orphans,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function field(FieldTally $tally): array
    {
        $rate = $tally->denominator === 0 ? null : round($tally->filled / $tally->denominator, 4);

        $row = [
            'path' => $tally->path,
            'type' => $tally->type,
            'depth' => $tally->depth,
            'denominator' => $tally->denominator,
            'filled' => $tally->filled,
        ];

        if (! FieldPath::isNested($tally->path)) {
            $row['filled_own'] = $tally->filledOwn;
            $row['filled_inherited'] = $tally->filledInherited;
        }

        $row['fill_rate'] = $rate;
        $row['status'] = $this->status($rate);

        if ($tally->type === 'toggle') {
            $row['always_false'] = $tally->denominator > 0 && ! $tally->sawNotFalse;
        }

        return $row;
    }

    private function status(?float $rate): string
    {
        if ($rate === null) {
            return self::NEVER_INSTANTIATED;
        }

        if ($rate == 0) {
            return self::DEAD;
        }

        if ($rate < $this->scope->minFill) {
            return self::SPARSE;
        }

        return self::HEALTHY;
    }
}
