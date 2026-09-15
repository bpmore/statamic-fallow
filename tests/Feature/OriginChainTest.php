<?php

declare(strict_types=1);

/*
 * Spec section 9.5: a two-hop origin chain. P3 (fr-CA) originates from P2
 * (fr), which originates from P1 (en). The numbers are the specification.
 */

beforeEach(function () {
    $this->block = blueprintBlock(audit(['products']), 'products', 'default');
});

test('three entry localizations are analyzed', function () {
    expect($this->block['entries_analyzed'])->toBe(3);
});

test('fields resolve to the closest ancestor that has the key', function (string $path, int $denominator, int $filled, int $own, int $inherited, float $rate) {
    $row = fieldRow($this->block, $path);

    expect($row['denominator'])->toBe($denominator)
        ->and($row['filled'])->toBe($filled)
        ->and($row['filled_own'])->toBe($own)
        ->and($row['filled_inherited'])->toBe($inherited)
        ->and($row['fill_rate'])->toBe($rate);
})->with([
    ['name', 3, 3, 1, 2, 1.0],
    ['tagline', 3, 3, 2, 1, 1.0],
    ['badge', 3, 2, 2, 0, 0.6667],
    ['sku', 3, 1, 1, 0, 0.3333],
    ['color', 3, 2, 1, 1, 0.6667],
]);
