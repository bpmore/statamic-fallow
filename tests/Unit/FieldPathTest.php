<?php

declare(strict_types=1);

use Bpmore\Fallow\Analysis\FieldPath;

/*
 * Spec section 4.1.
 */

test('a top-level field is its handle', function () {
    expect(FieldPath::top('subtitle'))->toBe('subtitle');
});

test('a grid or group child is parent.child', function () {
    expect(FieldPath::child('links', 'url'))->toBe('links.url');
});

test('a set field is parent[set].child', function () {
    expect(FieldPath::inSet('blocks', 'hero', 'heading'))->toBe('blocks[hero].heading');
});

test('nesting composes left to right', function () {
    expect(FieldPath::child(FieldPath::inSet('blocks', 'hero', 'links'), 'url'))->toBe('blocks[hero].links.url');
});

test('a path is nested when it has a dot or a bracket', function () {
    expect(FieldPath::isNested('subtitle'))->toBeFalse()
        ->and(FieldPath::isNested('links.url'))->toBeTrue()
        ->and(FieldPath::isNested('blocks[hero].heading'))->toBeTrue();
});
