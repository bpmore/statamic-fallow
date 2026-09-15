<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Commands;

use Bpmore\Fallow\Analysis\Auditor;
use Bpmore\Fallow\Analysis\Report;
use Bpmore\Fallow\Analysis\Scope;
use Bpmore\Fallow\Analysis\UnknownScope;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

/**
 * `php please fallow:audit`. Spec section 6.
 *
 * Exit codes: 0 done and nothing in --fail-on was found, 1 done and at
 * least one condition was found, 2 the input was wrong.
 */
class AuditCommand extends Command
{
    use RunsInPlease;

    public const FAIL_ON = ['dead', 'orphans', 'sparse'];

    public const EXIT_FOUND = 1;

    public const EXIT_INVALID = 2;

    protected $signature = 'statamic:fallow:audit
        {--collection=* : Collection handle to audit, repeatable, every collection when omitted}
        {--site=* : Site handle to audit, repeatable, every site when omitted}
        {--min-fill= : Fill rate below which a field is sparse, from the config file when omitted}
        {--json= : Write the report as JSON to this path, or to stdout with no path}
        {--fail-on= : Comma list of dead, orphans, sparse; exit 1 when any is found}';

    protected $description = 'Report which blueprint fields the content fills, which are dead, and which entry keys no field claims.';

    public function handle(Auditor $auditor): int
    {
        try {
            $scope = $this->scope();
            $failOn = $this->failOn();
            $report = $auditor->audit($scope);
        } catch (UnknownScope|\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::EXIT_INVALID;
        }

        $this->output($report);

        foreach ($failOn as $condition) {
            if ($report->has($condition)) {
                return self::EXIT_FOUND;
            }
        }

        return self::SUCCESS;
    }

    private function scope(): Scope
    {
        $minFill = $this->option('min-fill');

        if ($minFill !== null && ! is_numeric($minFill)) {
            throw new \InvalidArgumentException("--min-fill must be a number between 0 and 1, got [{$minFill}].");
        }

        $minFill = $minFill === null ? (float) config('fallow.min_fill', 0.05) : (float) $minFill;

        if ($minFill < 0 || $minFill > 1) {
            throw new \InvalidArgumentException("--min-fill must be between 0 and 1, got [{$minFill}].");
        }

        return new Scope(
            array_values(array_map('strval', (array) $this->option('collection'))),
            array_values(array_map('strval', (array) $this->option('site'))),
            $minFill,
        );
    }

    /**
     * @return list<string>
     */
    private function failOn(): array
    {
        $raw = trim((string) $this->option('fail-on'));

        if ($raw === '') {
            return [];
        }

        $conditions = array_values(array_unique(array_map('trim', explode(',', $raw))));

        foreach ($conditions as $condition) {
            if (! in_array($condition, self::FAIL_ON, true)) {
                throw new \InvalidArgumentException("--fail-on accepts ".implode(', ', self::FAIL_ON).", got [{$condition}].");
            }
        }

        return $conditions;
    }

    /**
     * `--json` alone means the JSON is the whole of stdout. `--json=path`
     * writes the file and prints the human report as usual.
     */
    private function output(Report $report): void
    {
        $path = $this->option('json');
        $jsonWanted = $this->input->hasParameterOption('--json') || $path !== null;

        // From the terminal, a bare `--json` arrives as null. Called from
        // code with `['--json' => true]`, it arrives as true.
        if ($jsonWanted && ($path === null || $path === '' || is_bool($path))) {
            $this->output->write($report->toJson()."\n", false, \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);

            return;
        }

        if ($jsonWanted) {
            // A scheduled audit writing to storage/app/fallow/ on a fresh site
            // should not fail on the directory not being there yet.
            if (! is_dir(dirname((string) $path))) {
                mkdir(dirname((string) $path), 0755, true);
            }

            file_put_contents((string) $path, $report->toJson()."\n");
        }

        $this->human($report);

        if ($jsonWanted) {
            $this->components->info("JSON written to {$path}");
        }
    }

    private function human(Report $report): void
    {
        $array = $report->toArray();

        foreach ($array['collections'] as $collection) {
            foreach ($collection['blueprints'] as $blueprint) {
                $this->newLine();
                $this->components->twoColumnDetail(
                    "<fg=yellow>{$collection['handle']} / {$blueprint['handle']}</>",
                    "{$blueprint['entries_analyzed']} entry localizations",
                );

                $this->table(
                    ['Path', 'Type', 'Filled', 'Denominator', 'Rate', 'Status'],
                    array_map(fn ($f) => [
                        $f['path'],
                        $f['type'],
                        $f['filled'],
                        $f['denominator'],
                        $f['fill_rate'] === null ? '-' : number_format($f['fill_rate'], 4),
                        $f['status'].(($f['always_false'] ?? false) ? ' (always false)' : ''),
                    ], $blueprint['fields']),
                );

                if ($blueprint['orphan_keys'] === []) {
                    $this->line('  No orphan keys.');
                } else {
                    $this->line('  Orphan keys:');

                    foreach ($blueprint['orphan_keys'] as $orphan) {
                        $this->line(sprintf(
                            '    %s in %d %s, e.g. %s',
                            $orphan['key'],
                            $orphan['entries'],
                            $orphan['entries'] === 1 ? 'entry' : 'entries',
                            implode(', ', $orphan['samples']),
                        ));
                    }
                }
            }
        }

        $s = $array['summary'];
        $this->newLine();
        $this->line(sprintf(
            '%d fields: %d healthy, %d sparse, %d dead, %d never instantiated. %d orphan %s.',
            $s['fields_total'],
            $s['healthy'],
            $s['sparse'],
            $s['dead'],
            $s['never_instantiated'],
            $s['orphan_keys'],
            $s['orphan_keys'] === 1 ? 'key' : 'keys',
        ));
    }
}
