<?php

declare(strict_types=1);

/*
 * Spec section 9.4, items 1, 2, 4 and 5, on the articles fixture. The
 * numbers here are derived from the fixture by hand, using the rules in
 * spec section 4:
 *
 * A1 (post): summary "", sections [intro disabled, intro "Yo", quote "Q",
 * callout ""], content one empty paragraph.
 * A2 (post): no summary, sections [intro ""], content one set (pull, "pulled").
 * N1 (note): summary "S".
 */

beforeEach(function () {
    $this->report = audit(['articles']);
    $this->post = blueprintBlock($this->report, 'articles', 'post');
    $this->note = blueprintBlock($this->report, 'articles', 'note');
});

test('a set never used anywhere is never_instantiated', function () {
    $row = fieldRow($this->post, 'sections[unused].label');

    expect($row['denominator'])->toBe(0)
        ->and($row['filled'])->toBe(0)
        ->and($row['fill_rate'])->toBeNull()
        ->and($row['status'])->toBe('never_instantiated');
});

test('a disabled replicator row counts toward nothing', function () {
    // A1 has two intro rows, one disabled, and A2 has one: two enabled
    // instances, of which only A1's "Yo" is filled.
    $row = fieldRow($this->post, 'sections[intro].heading');

    expect($row['denominator'])->toBe(2)
        ->and($row['filled'])->toBe(1)
        ->and($row['fill_rate'])->toBe(0.5);
});

test('two blueprints in one collection keep their statistics apart', function () {
    expect($this->post['entries_analyzed'])->toBe(2)
        ->and($this->note['entries_analyzed'])->toBe(1)
        ->and(fieldRow($this->post, 'summary')['filled'])->toBe(0)
        ->and(fieldRow($this->post, 'summary')['status'])->toBe('dead')
        ->and(fieldRow($this->note, 'summary')['filled'])->toBe(1)
        ->and(fieldRow($this->note, 'summary')['status'])->toBe('healthy');
});

test('two sets that both declare body get two distinct paths', function () {
    $quote = fieldRow($this->post, 'sections[quote].body');
    $callout = fieldRow($this->post, 'sections[callout].body');

    expect($quote['denominator'])->toBe(1)
        ->and($quote['filled'])->toBe(1)
        ->and($callout['denominator'])->toBe(1)
        ->and($callout['filled'])->toBe(0)
        ->and($callout['status'])->toBe('dead');
});

test('a bard set instance counts for the set fields declared in the flat sets shape', function () {
    $row = fieldRow($this->post, 'content[pull].body');

    expect($row['denominator'])->toBe(1)
        ->and($row['filled'])->toBe(1);
});

test('a bard value with one empty paragraph is empty and one with a set is filled', function () {
    $row = fieldRow($this->post, 'content');

    expect($row['denominator'])->toBe(2)
        ->and($row['filled'])->toBe(1);
});

test('the summary counts fields across both blueprints', function () {
    // post: title, summary, sections, content, sections[intro].heading,
    // sections[quote].body, sections[callout].body, sections[unused].label,
    // content[pull].body (9). note: title, summary (2).
    expect($this->report['summary'])->toBe([
        'fields_total' => 11,
        'healthy' => 8,
        'sparse' => 0,
        'dead' => 2,
        'never_instantiated' => 1,
        'orphan_keys' => 0,
    ]);
});
