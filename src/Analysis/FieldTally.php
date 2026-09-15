<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Analysis;

/**
 * The running counts for one field path within one blueprint.
 */
final class FieldTally
{
    public int $denominator = 0;

    public int $filled = 0;

    public int $filledOwn = 0;

    public int $filledInherited = 0;

    /** Whether any counted value was something other than boolean false. */
    public bool $sawNotFalse = false;

    public function __construct(
        public readonly string $path,
        public readonly string $type,
        public readonly int $depth,
    ) {}

    /**
     * Count one occurrence of the field.
     *
     * @param  bool|null  $own  true when the entry's own data held the key,
     *                          false when an origin did, null for nested
     *                          fields where the split does not apply
     */
    public function count(mixed $value, ?bool $own): void
    {
        $this->denominator++;

        if ($value !== false) {
            $this->sawNotFalse = true;
        }

        if (! ValueInspector::isFilled($value, $this->type)) {
            return;
        }

        $this->filled++;

        if ($own === true) {
            $this->filledOwn++;
        } elseif ($own === false) {
            $this->filledInherited++;
        }
    }
}
