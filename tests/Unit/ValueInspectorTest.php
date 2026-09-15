<?php

declare(strict_types=1);

use Bpmore\Fallow\Analysis\ValueInspector;

/*
 * Spec section 4.3 and section 9.4 item 3. Pure: no Statamic here.
 */

test('empty values', function (mixed $value, string $type) {
    expect(ValueInspector::isFilled($value, $type))->toBeFalse();
})->with([
    'null' => [null, 'text'],
    'empty string' => ['', 'text'],
    'whitespace' => ["  \n\t ", 'text'],
    'empty array' => [[], 'grid'],
    'empty array as bard' => [[], 'bard'],
    'bard empty paragraph' => [[['type' => 'paragraph']], 'bard'],
    'bard paragraph with blank text' => [[['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '   ']]]], 'bard'],
    'bard two empty paragraphs' => [[['type' => 'paragraph'], ['type' => 'paragraph']], 'bard'],
]);

test('filled values', function (mixed $value, string $type) {
    expect(ValueInspector::isFilled($value, $type))->toBeTrue();
})->with([
    'string' => ['hello', 'text'],
    'string zero' => ['0', 'text'],
    'int zero' => [0, 'integer'],
    'float zero' => [0.0, 'float'],
    'false' => [false, 'toggle'],
    'true' => [true, 'toggle'],
    'array with an element' => [[['label' => 'A']], 'grid'],
    'array with an empty element' => [[[]], 'grid'],
    'bard paragraph with text' => [[['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'hello']]]], 'bard'],
    'bard with a set node' => [[['type' => 'set', 'attrs' => ['values' => ['type' => 'pull']]]], 'bard'],
    'bard with a nested set node' => [[['type' => 'paragraph', 'content' => [['type' => 'set']]]], 'bard'],
    'bard text deep in the tree' => [[['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x']]]]]]]], 'bard'],
]);

test('the bard rule only applies to bard', function () {
    expect(ValueInspector::isFilled([['type' => 'paragraph']], 'replicator'))->toBeTrue();
});

test('a bard value stored as a string follows the string rule', function () {
    expect(ValueInspector::isFilled('<p>hi</p>', 'bard'))->toBeTrue()
        ->and(ValueInspector::isFilled('  ', 'bard'))->toBeFalse();
});
