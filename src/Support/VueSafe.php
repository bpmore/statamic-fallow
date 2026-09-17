<?php

declare(strict_types=1);

namespace Bpmore\Fallow\Support;

/**
 * Text for a view that Vue will compile.
 *
 * A utility view is not printed as HTML: Statamic hands the rendered markup
 * to Vue as a component template. So Blade's escaping is only half the job.
 * A value holding `{{` would be read by Vue as an expression, and the page
 * would either fail to compile, blank, or run whatever the braces held.
 * Every value here is a handle, a path, or an id, none of which should ever
 * hold a brace, and this is what makes sure of it. Vue finds its delimiters
 * in the raw source before it decodes entities, so a brace written as an
 * entity is inert.
 */
final class VueSafe
{
    public static function text(mixed $value): string
    {
        return str_replace(['{', '}'], ['&#123;', '&#125;'], e((string) $value));
    }
}
