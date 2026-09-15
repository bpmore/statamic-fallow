<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Analysis;

/**
 * What one run looks at. Empty lists mean everything.
 */
final class Scope
{
    /**
     * @param  list<string>  $collections  collection handles
     * @param  list<string>  $sites  site handles
     */
    public function __construct(
        public readonly array $collections = [],
        public readonly array $sites = [],
        public readonly float $minFill = 0.05,
    ) {}
}
