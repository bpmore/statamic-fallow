<?php

declare(strict_types=1);

/*
 * Spec sections 9.1 to 9.3: the pages fixture. Every number here comes from
 * the table in 9.2 and is the specification. Do not edit an expectation to
 * match output; if one looks wrong, stop and ask.
 */

beforeEach(function () {
    $this->block = blueprintBlock(audit(['pages']), 'pages', 'default');
});

test('three entry localizations are analyzed', function () {
    expect($this->block['entries_analyzed'])->toBe(3);
});

test('top-level fields', function (string $path, int $denominator, int $filled, int $own, int $inherited, float $rate, string $status) {
    $row = fieldRow($this->block, $path);

    expect($row['denominator'])->toBe($denominator)
        ->and($row['filled'])->toBe($filled)
        ->and($row['filled_own'])->toBe($own)
        ->and($row['filled_inherited'])->toBe($inherited)
        ->and($row['fill_rate'])->toBe($rate)
        ->and($row['status'])->toBe($status)
        ->and($row['depth'])->toBe(0);
})->with([
    ['title', 3, 3, 3, 0, 1.0, 'healthy'],
    ['subtitle', 3, 2, 1, 1, 0.6667, 'healthy'],
    ['promo', 3, 0, 0, 0, 0.0, 'dead'],
    ['featured', 3, 3, 2, 1, 1.0, 'healthy'],
    ['links', 3, 2, 1, 1, 0.6667, 'healthy'],
    ['blocks', 3, 3, 2, 1, 1.0, 'healthy'],
    ['body', 3, 2, 1, 1, 0.6667, 'healthy'],
]);

test('nested fields', function (string $path, int $denominator, int $filled, float $rate, string $status) {
    $row = fieldRow($this->block, $path);

    expect($row['denominator'])->toBe($denominator)
        ->and($row['filled'])->toBe($filled)
        ->and($row['fill_rate'])->toBe($rate)
        ->and($row['status'])->toBe($status)
        ->and($row['depth'])->toBe(1)
        ->and($row)->not->toHaveKeys(['filled_own', 'filled_inherited']);
})->with([
    ['links.label', 4, 4, 1.0, 'healthy'],
    ['links.url', 4, 2, 0.5, 'healthy'],
    ['blocks[hero].heading', 3, 2, 0.6667, 'healthy'],
    ['blocks[hero].cta', 3, 1, 0.3333, 'healthy'],
    ['blocks[quote].body', 1, 1, 1.0, 'healthy'],
]);

test('the blueprint reports exactly the twelve fields in the table, sorted by path', function () {
    expect(array_column($this->block['fields'], 'path'))->toBe([
        'blocks',
        'blocks[hero].cta',
        'blocks[hero].heading',
        'blocks[quote].body',
        'body',
        'featured',
        'links',
        'links.label',
        'links.url',
        'promo',
        'subtitle',
        'title',
    ]);
});

test('field types are reported', function () {
    expect(fieldRow($this->block, 'featured')['type'])->toBe('toggle')
        ->and(fieldRow($this->block, 'links')['type'])->toBe('grid')
        ->and(fieldRow($this->block, 'blocks')['type'])->toBe('replicator')
        ->and(fieldRow($this->block, 'body')['type'])->toBe('bard')
        ->and(fieldRow($this->block, 'blocks[quote].body')['type'])->toBe('textarea');
});

test('featured is not always false because E1 is true', function () {
    expect(fieldRow($this->block, 'featured')['always_false'])->toBeFalse();
});

test('only toggles carry the always_false key', function () {
    foreach ($this->block['fields'] as $row) {
        if ($row['type'] === 'toggle') {
            continue;
        }

        expect($row)->not->toHaveKey('always_false');
    }
});

test('legacy_teaser is the one orphan key and __localized_fields is not reported', function () {
    expect($this->block['orphan_keys'])->toBe([
        ['key' => 'legacy_teaser', 'entries' => 1, 'samples' => ['pages-e2']],
    ]);
});

test('the summary counts every field in the block', function () {
    expect(audit(['pages'])['summary'])->toBe([
        'fields_total' => 12,
        'healthy' => 11,
        'sparse' => 0,
        'dead' => 1,
        'never_instantiated' => 0,
        'orphan_keys' => 1,
    ]);
});
