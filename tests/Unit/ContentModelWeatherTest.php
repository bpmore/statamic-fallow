<?php

declare(strict_types=1);

use Bpmore\Fallow\Weather\ContentModelWeather;

/**
 * The weather decision, with no Site Weather and no booted Statamic: the part
 * of the band that is this addon's own.
 */
function summary(int $total, int $dead = 0, int $sparse = 0, int $orphans = 0, int $neverInstantiated = 0): array
{
    return [
        'fields_total' => $total,
        'healthy' => $total - $dead - $sparse - $neverInstantiated,
        'sparse' => $sparse,
        'dead' => $dead,
        'never_instantiated' => $neverInstantiated,
        'orphan_keys' => $orphans,
    ];
}

test('clear when every judged field is in use and nothing is orphaned', function () {
    expect(ContentModelWeather::decide(summary(80)))
        ->toBe(['state' => 'clear', 'headline' => 'All 80 fields in use']);
});

test('fair for sparse fields alone', function () {
    $decision = ContentModelWeather::decide(summary(80, sparse: 5));

    expect($decision['state'])->toBe('fair')
        ->and($decision['headline'])->toBe('5 of 80 fields sparse');
});

test('overcast for any dead field, or a quarter sparse', function () {
    expect(ContentModelWeather::decide(summary(80, dead: 1))['state'])->toBe('overcast')
        ->and(ContentModelWeather::decide(summary(80, sparse: 20))['state'])->toBe('overcast')
        ->and(ContentModelWeather::decide(summary(80, sparse: 19))['state'])->toBe('fair');
});

test('rain for a tenth dead, or for orphan keys', function () {
    expect(ContentModelWeather::decide(summary(80, dead: 8))['state'])->toBe('rain')
        ->and(ContentModelWeather::decide(summary(80, dead: 7))['state'])->toBe('overcast')
        ->and(ContentModelWeather::decide(summary(80, orphans: 1))['state'])->toBe('rain');
});

test('storm for a quarter dead, or a tenth dead alongside orphan keys', function () {
    expect(ContentModelWeather::decide(summary(80, dead: 20))['state'])->toBe('storm')
        ->and(ContentModelWeather::decide(summary(80, dead: 8, orphans: 3))['state'])->toBe('storm')
        ->and(ContentModelWeather::decide(summary(80, dead: 19, orphans: 0))['state'])->toBe('rain');
});

test('fields the audit could not judge are left out of the share', function () {
    // 8 dead of 80 total is a tenth; of 40 judged it is a fifth, still rain -
    // but of 30 judged it is over a quarter.
    expect(ContentModelWeather::decide(summary(80, dead: 8, neverInstantiated: 40))['state'])->toBe('rain')
        ->and(ContentModelWeather::decide(summary(80, dead: 8, neverInstantiated: 50))['state'])->toBe('storm')
        ->and(ContentModelWeather::decide(summary(80, neverInstantiated: 40))['headline'])->toBe('All 40 fields in use');
});

test('the headline names what is wrong, in the audit\'s words', function () {
    expect(ContentModelWeather::decide(summary(80, dead: 12, sparse: 7, orphans: 3))['headline'])
        ->toBe('12 of 80 fields dead, 7 sparse, 3 orphan keys')
        ->and(ContentModelWeather::decide(summary(80, orphans: 1))['headline'])->toBe('1 orphan key')
        ->and(ContentModelWeather::decide(summary(1, dead: 1))['headline'])->toBe('1 of 1 field dead');
});
