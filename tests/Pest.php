<?php

declare(strict_types=1);

use Bpmore\Fallow\Tests\TestCase;

// Only the feature tests boot Statamic. FieldPath and ValueInspector take
// values and return values, and a suite that boots nothing for them cannot
// grow a dependency on something booted.
uses(TestCase::class)->in('Feature');
