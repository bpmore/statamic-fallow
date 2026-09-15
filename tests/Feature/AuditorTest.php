<?php

declare(strict_types=1);

use Bpmore\Fallow\Analysis\Auditor;
use Bpmore\Fallow\Analysis\Scope;
use Bpmore\Fallow\Analysis\UnknownScope;

test('keys listed in config are ignored on top of the built-in list', function () {
    expect(blueprintBlock(audit(['pages']), 'pages', 'default')['orphan_keys'])->toHaveCount(1);

    config()->set('fallow.ignored_keys', ['legacy_teaser']);

    expect(blueprintBlock(audit(['pages']), 'pages', 'default')['orphan_keys'])->toBe([]);
});

test('the built-in ignore list survives a config override', function () {
    config()->set('fallow.ignored_keys', ['legacy_teaser']);

    // `blueprint` sits in every articles entry's own data and is on the
    // built-in list, so it must not surface once the config list is set.
    expect(blueprintBlock(audit(['articles']), 'articles', 'post')['orphan_keys'])->toBe([]);
});

test('an empty scope means every collection and every site, sorted', function () {
    $report = audit();

    expect($report['scope']['collections'])->toBe(['articles', 'pages', 'products'])
        ->and($report['scope']['sites'])->toBe(['en', 'fr', 'fr-CA'])
        ->and(array_column($report['collections'], 'handle'))->toBe(['articles', 'pages', 'products']);
});

test('scoping to one site counts only that site\'s localizations', function () {
    $block = blueprintBlock(audit(['pages'], ['fr']), 'pages', 'default');

    expect($block['entries_analyzed'])->toBe(1)
        ->and(fieldRow($block, 'subtitle'))->toMatchArray(['filled' => 1, 'filled_own' => 0, 'filled_inherited' => 1])
        ->and($block['orphan_keys'])->toBe([]);
});

test('an unknown collection is an error, not an empty result', function () {
    app(Auditor::class)->audit(new Scope(['nope']));
})->throws(UnknownScope::class, 'nope');

test('an unknown site is an error, not an empty result', function () {
    app(Auditor::class)->audit(new Scope([], ['de']));
})->throws(UnknownScope::class, 'de');

test('countEntries reports the localizations a run would read', function () {
    $auditor = app(Auditor::class);

    expect($auditor->countEntries(new Scope))->toBe(9)
        ->and($auditor->countEntries(new Scope(['pages'])))->toBe(3)
        ->and($auditor->countEntries(new Scope(['pages'], ['fr'])))->toBe(1);
});

test('the JSON keeps whole-number rates as floats', function () {
    $json = app(Auditor::class)->audit(new Scope(['pages']))->toJson();

    expect($json)->toContain('"fill_rate": 1.0')
        ->and($json)->toContain('"fill_rate": 0.0')
        ->and($json)->toContain('"min_fill": 0.05');
});

test('the own and inherited split appears only on top-level rows', function () {
    $block = blueprintBlock(audit(['pages']), 'pages', 'default');

    foreach ($block['fields'] as $row) {
        $nested = $row['depth'] > 0;

        expect(array_key_exists('filled_own', $row))->toBe(! $nested, $row['path'])
            ->and(array_key_exists('filled_inherited', $row))->toBe(! $nested, $row['path']);
    }
});
