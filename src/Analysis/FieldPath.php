<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Analysis;

/**
 * The path grammar from spec 4.1. A path names one blueprint field, and the
 * same handle under two different sets gets two different paths.
 *
 *   subtitle                  a top-level field
 *   links.url                 a child of a grid or group
 *   blocks[hero].heading      a field inside a replicator or bard set
 *   blocks[hero].links.url    nesting composes left to right
 */
final class FieldPath
{
    public static function top(string $handle): string
    {
        return $handle;
    }

    public static function child(string $parent, string $handle): string
    {
        return $parent.'.'.$handle;
    }

    public static function inSet(string $parent, string $set, string $handle): string
    {
        return $parent.'['.$set.'].'.$handle;
    }

    /** Anything below the top level: the own/inherited split does not apply. */
    public static function isNested(string $path): bool
    {
        return str_contains($path, '.') || str_contains($path, '[');
    }
}
