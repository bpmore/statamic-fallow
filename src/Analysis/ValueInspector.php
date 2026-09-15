<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Analysis;

/**
 * Whether a stored value counts as filled, per spec 4.3. Pure: raw values
 * in, a boolean out, nothing from Statamic.
 */
final class ValueInspector
{
    public static function isFilled(mixed $value, string $fieldtype): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            if ($value === []) {
                return false;
            }

            return $fieldtype === 'bard' ? self::bardHasContent($value) : true;
        }

        // Numbers, booleans, and anything else that is not null. Zero and
        // false are values somebody chose.
        return true;
    }

    /**
     * A bard value is a tree of ProseMirror nodes. It has content when the
     * text in it is more than whitespace, or when a set sits anywhere in it.
     *
     * @param  array<mixed>  $nodes
     */
    private static function bardHasContent(array $nodes): bool
    {
        $text = '';

        return self::anySetNode($nodes, $text) || trim($text) !== '';
    }

    /**
     * Walks the tree once, collecting text as it goes, and stops early at the
     * first set node because nothing after it can change the answer.
     *
     * @param  array<mixed>  $nodes
     */
    private static function anySetNode(array $nodes, string &$text): bool
    {
        foreach ($nodes as $key => $node) {
            if ($key === 'text' && is_string($node)) {
                $text .= $node;

                continue;
            }

            if (! is_array($node)) {
                continue;
            }

            if (($node['type'] ?? null) === 'set') {
                return true;
            }

            if (self::anySetNode($node, $text)) {
                return true;
            }
        }

        return false;
    }
}
