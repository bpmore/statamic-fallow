<?php

declare(strict_types=1);

use Bpmore\Fallow\Support\VueSafe;

test('escapes HTML and turns braces into entities', function () {
    expect(VueSafe::text('{{ a }} <b>&'))->toBe('&#123;&#123; a &#125;&#125; &lt;b&gt;&amp;')
        ->and(VueSafe::text('links.url'))->toBe('links.url')
        ->and(VueSafe::text(null))->toBe('')
        ->and(VueSafe::text(3))->toBe('3');
});
