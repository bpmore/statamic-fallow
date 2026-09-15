<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/*
 * Spec section 6 and section 9.4 items 6 to 9.
 */

test('--json alone puts only JSON on stdout', function () {
    $code = Artisan::call('statamic:fallow:audit', ['--json' => true, '--collection' => ['pages']]);
    $output = Artisan::output();

    expect($code)->toBe(0)
        ->and(json_decode($output, true, 512, JSON_THROW_ON_ERROR))->toHaveKeys(['fallow_version', 'generated_at', 'scope', 'collections', 'summary'])
        ->and(trim($output))->toStartWith('{')
        ->and(trim($output))->toEndWith('}');
});

test('--json=path writes the report to the file and keeps stdout human', function () {
    $path = sys_get_temp_dir().'/fallow-'.uniqid().'.json';

    $code = Artisan::call('statamic:fallow:audit', ['--json' => $path, '--collection' => ['pages']]);
    $output = Artisan::output();

    expect($code)->toBe(0)
        ->and(file_exists($path))->toBeTrue()
        ->and(json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)['summary']['fields_total'])->toBe(12)
        ->and($output)->toContain('legacy_teaser')
        ->and(json_decode($output, true))->toBeNull();

    unlink($path);
});

test('--json=path creates the directory when it is not there yet', function () {
    $path = sys_get_temp_dir().'/fallow-'.uniqid().'/nested/audit.json';

    $code = Artisan::call('statamic:fallow:audit', ['--json' => $path, '--collection' => ['pages']]);

    expect($code)->toBe(0)
        ->and(file_exists($path))->toBeTrue();

    unlink($path);
    rmdir(dirname($path));
    rmdir(dirname($path, 2));
});

test('--fail-on=dead exits 1 on the pages fixture', function () {
    expect(Artisan::call('statamic:fallow:audit', ['--collection' => ['pages'], '--fail-on' => 'dead']))->toBe(1);
});

test('without --fail-on the pages fixture exits 0', function () {
    expect(Artisan::call('statamic:fallow:audit', ['--collection' => ['pages']]))->toBe(0);
});

test('--fail-on=orphans exits 1 when an orphan key exists and 0 when none does', function () {
    expect(Artisan::call('statamic:fallow:audit', ['--collection' => ['pages'], '--fail-on' => 'orphans']))->toBe(1)
        ->and(Artisan::call('statamic:fallow:audit', ['--collection' => ['products'], '--fail-on' => 'orphans']))->toBe(0);
});

test('--fail-on=sparse honours --min-fill', function () {
    // links.url is at 0.5, so a floor of 0.6 makes it sparse.
    expect(Artisan::call('statamic:fallow:audit', ['--collection' => ['pages'], '--fail-on' => 'sparse', '--min-fill' => '0.6']))->toBe(1)
        ->and(Artisan::call('statamic:fallow:audit', ['--collection' => ['pages'], '--fail-on' => 'sparse']))->toBe(0);
});

test('--fail-on takes a comma list', function () {
    // products has no orphans and nothing sparse, but the title field
    // Statamic adds to every entry blueprint is dead there.
    expect(Artisan::call('statamic:fallow:audit', ['--collection' => ['products'], '--fail-on' => 'orphans,sparse']))->toBe(0)
        ->and(Artisan::call('statamic:fallow:audit', ['--collection' => ['products'], '--fail-on' => 'orphans,dead']))->toBe(1);
});

test('an unknown collection handle exits 2', function () {
    expect(Artisan::call('statamic:fallow:audit', ['--collection' => ['nope']]))->toBe(2)
        ->and(Artisan::output())->toContain('nope');
});

test('an unknown site handle exits 2', function () {
    expect(Artisan::call('statamic:fallow:audit', ['--site' => ['de']]))->toBe(2)
        ->and(Artisan::output())->toContain('de');
});

test('an unknown --fail-on condition exits 2', function () {
    expect(Artisan::call('statamic:fallow:audit', ['--fail-on' => 'orphans,bogus']))->toBe(2);
});

test('two runs produce identical JSON apart from generated_at', function () {
    Artisan::call('statamic:fallow:audit', ['--json' => true]);
    $first = json_decode(Artisan::output(), true);
    Artisan::call('statamic:fallow:audit', ['--json' => true]);
    $second = json_decode(Artisan::output(), true);

    unset($first['generated_at'], $second['generated_at']);

    expect(json_encode($first))->toBe(json_encode($second));
});

test('the scope records what was asked for', function () {
    Artisan::call('statamic:fallow:audit', ['--json' => true, '--collection' => ['pages'], '--site' => ['fr'], '--min-fill' => '0.2']);
    $report = json_decode(Artisan::output(), true);

    expect($report['scope'])->toBe(['collections' => ['pages'], 'sites' => ['fr'], 'min_fill' => 0.2])
        ->and(blueprintBlock($report, 'pages', 'default')['entries_analyzed'])->toBe(1);
});

test('the human output has a table per blueprint, the orphan list, and a summary line', function () {
    Artisan::call('statamic:fallow:audit', ['--collection' => ['pages']]);
    $output = Artisan::output();

    expect($output)->toContain('pages / default')
        ->and($output)->toContain('blocks[hero].cta')
        ->and($output)->toContain('legacy_teaser')
        ->and($output)->toContain('12 fields');
});
