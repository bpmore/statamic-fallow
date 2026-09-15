<?php

declare(strict_types=1);

test('the addon boots with its config', function () {
    expect(config('fallow.min_fill'))->toBe(0.05)
        ->and(config('fallow.cp_entry_limit'))->toBe(5000)
        ->and(config('fallow.ignored_keys'))->toBe([]);
});
