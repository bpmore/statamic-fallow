<?php

declare(strict_types=1);

use Bpmore\Fallow\Analysis\Auditor;
use Bpmore\Fallow\Analysis\Scope;
use Bpmore\Fallow\Tests\TestCase;

// Only the feature tests boot Statamic. FieldPath and ValueInspector take
// values and return values, and a suite that boots nothing for them cannot
// grow a dependency on something booted.
uses(TestCase::class)->in('Feature');

/**
 * Run the audit and return the report as the array the JSON is made from.
 *
 * @param  list<string>  $collections  empty means every collection
 * @param  list<string>  $sites  empty means every site
 * @return array<string, mixed>
 */
function audit(array $collections = [], array $sites = [], float $minFill = 0.05): array
{
    return app(Auditor::class)->audit(new Scope($collections, $sites, $minFill))->toArray();
}

/**
 * One blueprint block out of a report.
 *
 * @param  array<string, mixed>  $report
 * @return array<string, mixed>
 */
function blueprintBlock(array $report, string $collection, string $blueprint): array
{
    foreach ($report['collections'] as $c) {
        if ($c['handle'] !== $collection) {
            continue;
        }

        foreach ($c['blueprints'] as $b) {
            if ($b['handle'] === $blueprint) {
                return $b;
            }
        }
    }

    throw new RuntimeException("No block for {$collection}/{$blueprint} in the report.");
}

/**
 * One field row out of a blueprint block, by path.
 *
 * @param  array<string, mixed>  $block
 * @return array<string, mixed>
 */
function fieldRow(array $block, string $path): array
{
    foreach ($block['fields'] as $field) {
        if ($field['path'] === $path) {
            return $field;
        }
    }

    throw new RuntimeException("No field {$path} in the block.");
}
