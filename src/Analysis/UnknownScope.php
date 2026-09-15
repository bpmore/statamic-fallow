<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Analysis;

use InvalidArgumentException;

/**
 * A collection or site handle that does not exist. An error rather than an
 * empty result, because a misspelt handle in CI would otherwise pass.
 */
final class UnknownScope extends InvalidArgumentException {}
